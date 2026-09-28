<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\AgreementCarRentalInvoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AgreementCarRentalInvoiceService
{
    public function __construct(
        private AgreementCarRentalInvoiceSerialService $serialService
    ) {}

    /**
     * @return Collection<int, array{
     *     payment_id: int,
     *     payment_no: string|null,
     *     payment_date: string,
     *     payment_method: string,
     *     bank_account_id: int|null,
     *     bank_display: string|null,
     *     rent_allocated_amount: float,
     *     posting_status: string|null
     * }>
     */
    public function eligibleRentPayments(Agreement $agreement): Collection
    {
        $allocations = PaymentAllocation::query()
            ->whereHas('invoice', fn ($q) => $q
                ->where('invoice_type', 'agreement')
                ->where('source_id', $agreement->id))
            ->with(['payment.bankAccount'])
            ->get();

        $byPayment = $allocations->groupBy('payment_id');

        return $byPayment->map(function ($group, $paymentId) {
            /** @var Payment|null $payment */
            $payment = $group->first()?->payment;
            if (! $payment) {
                return null;
            }

            $rentAmount = round((float) $group->sum('allocated_amount'), 2);
            if ($rentAmount <= 0) {
                return null;
            }

            return [
                'payment_id' => (int) $paymentId,
                'payment_no' => $payment->payment_no,
                'payment_date' => $payment->payment_date?->format('Y-m-d') ?? '',
                'payment_method' => (string) $payment->payment_method,
                'bank_account_id' => $payment->bank_account_id ? (int) $payment->bank_account_id : null,
                'bank_display' => $payment->bankAccount?->paymentDisplayName(),
                'rent_allocated_amount' => $rentAmount,
                'posting_status' => $payment->posting_status,
            ];
        })
            ->filter()
            ->filter(fn (array $row) => $row['posting_status'] === Payment::POSTING_STATUS_POSTED)
            ->sortBy('payment_date')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $eligible
     * @return list<int>
     */
    public function defaultSelectedPaymentIds(
        Collection $eligible,
        bool $includeCash,
        ?int $bankAccountFilterId
    ): array {
        return $eligible
            ->filter(function (array $row) use ($includeCash, $bankAccountFilterId) {
                $method = $row['payment_method'];

                if ($method === 'Cash') {
                    return $includeCash;
                }

                if (! in_array($method, Payment::METHODS_REQUIRING_BANK_ACCOUNT, true)) {
                    return false;
                }

                if ($bankAccountFilterId !== null) {
                    return (int) ($row['bank_account_id'] ?? 0) === $bankAccountFilterId;
                }

                return true;
            })
            ->pluck('payment_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $paymentIds
     * @return array<string, mixed>
     */
    public function calculateTotals(
        Agreement $agreement,
        Collection $eligible,
        array $paymentIds,
        float $weeklyInsuranceAmount,
        float $vatRate,
        ?Carbon $rentalStartOverride = null,
        ?Carbon $rentalEndOverride = null
    ): array {
        $selected = $eligible->whereIn('payment_id', $paymentIds)->values();

        if ($selected->count() !== count(array_unique($paymentIds))) {
            throw ValidationException::withMessages([
                'payment_ids' => 'One or more selected payments are not valid for this agreement.',
            ]);
        }

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_ids' => 'Select at least one payment.',
            ]);
        }

        $weekCount = $selected->count();
        $totalPaid = round((float) $selected->sum('rent_allocated_amount'), 2);
        $weeklyRate = round((float) $agreement->discounted_rent, 2);
        $insuranceTotal = round($weekCount * $weeklyInsuranceAmount, 2);
        $rentAfterInsurance = round($totalPaid - $insuranceTotal, 2);
        $divisor = 1 + ($vatRate / 100);
        $netExVat = $divisor > 0 ? round($rentAfterInsurance / $divisor, 2) : 0.0;
        $vatAmount = round($rentAfterInsurance - $netExVat, 2);

        $dates = $selected->pluck('payment_date')->filter()->sort()->values();
        $rentalStart = $rentalStartOverride ?? ($dates->isNotEmpty() ? Carbon::parse($dates->first()) : null);
        $rentalEnd = $rentalEndOverride ?? ($dates->isNotEmpty() ? Carbon::parse($dates->last()) : null);

        return [
            'week_count' => $weekCount,
            'weekly_rate' => $weeklyRate,
            'total_paid' => $totalPaid,
            'insurance_total' => $insuranceTotal,
            'rent_after_insurance' => $rentAfterInsurance,
            'net_ex_vat' => $netExVat,
            'vat_amount' => $vatAmount,
            'vat_rate' => round($vatRate, 2),
            'weekly_insurance_amount' => round($weeklyInsuranceAmount, 2),
            'rental_start_date' => $rentalStart?->format('Y-m-d'),
            'rental_end_date' => $rentalEnd?->format('Y-m-d'),
        ];
    }

    /**
     * @param  array{
     *     invoice_date: string,
     *     include_cash: bool,
     *     bank_account_filter_id?: int|null,
     *     weekly_insurance_amount: float,
     *     vat_rate: float,
     *     vat_registration_number?: string|null,
     *     rental_start_date?: string|null,
     *     rental_end_date?: string|null,
     *     payment_ids: list<int>
     * }  $input
     */
    public function upsert(
        Agreement $agreement,
        array $input,
        User $user
    ): AgreementCarRentalInvoice {
        $eligible = $this->eligibleRentPayments($agreement);
        $paymentIds = array_map('intval', $input['payment_ids']);

        $rentalStart = ! empty($input['rental_start_date'])
            ? Carbon::parse($input['rental_start_date'])
            : null;
        $rentalEnd = ! empty($input['rental_end_date'])
            ? Carbon::parse($input['rental_end_date'])
            : null;

        $totals = $this->calculateTotals(
            $agreement,
            $eligible,
            $paymentIds,
            (float) $input['weekly_insurance_amount'],
            (float) $input['vat_rate'],
            $rentalStart,
            $rentalEnd
        );

        $selectedRows = $eligible->whereIn('payment_id', $paymentIds)->values()->all();

        $company = $agreement->documentCompany() ?? $agreement->company;
        if (! $company) {
            throw ValidationException::withMessages([
                'agreement' => 'Agreement has no company for invoicing.',
            ]);
        }

        $existing = AgreementCarRentalInvoice::query()
            ->where('agreement_id', $agreement->id)
            ->first();

        $serial = $existing
            ? [
                'serial_prefix' => $existing->serial_prefix,
                'serial_number' => $existing->serial_number,
            ]
            : $this->serialService->assignSerial($agreement->tenant_id, (int) $company->id, $company);

        $attributes = [
            'tenant_id' => $agreement->tenant_id,
            'company_id' => $company->id,
            'serial_prefix' => $serial['serial_prefix'],
            'serial_number' => $serial['serial_number'],
            'invoice_date' => $input['invoice_date'],
            'include_cash' => (bool) $input['include_cash'],
            'bank_account_filter_id' => $input['bank_account_filter_id'] ?? null,
            'weekly_insurance_amount' => $input['weekly_insurance_amount'],
            'vat_rate' => $input['vat_rate'],
            'vat_registration_number' => $input['vat_registration_number'] ?? null,
            'rental_start_date' => $totals['rental_start_date'],
            'rental_end_date' => $totals['rental_end_date'],
            'payment_snapshot' => $selectedRows,
            'totals_snapshot' => $totals,
            'generated_by' => $user->id,
        ];

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh(['company', 'agreement.driver', 'agreement.car.carModel']);
        }

        $attributes['agreement_id'] = $agreement->id;

        return AgreementCarRentalInvoice::query()->create($attributes)
            ->fresh(['company', 'agreement.driver', 'agreement.car.carModel']);
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfViewData(AgreementCarRentalInvoice $record): array
    {
        $agreement = $record->agreement;
        $agreement?->loadMissing(['driver', 'car.carModel', 'company', 'car.company']);
        $company = $record->company ?? $agreement?->documentCompany();

        return [
            'record' => $record,
            'agreement' => $agreement,
            'company' => $company,
            'driver' => $agreement?->driver,
            'car' => $agreement?->car,
            'totals' => $record->totals_snapshot ?? [],
            'payments' => $record->payment_snapshot ?? [],
        ];
    }
}
