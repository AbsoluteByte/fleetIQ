<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarInsurance;
use App\Models\CarMot;
use App\Models\CarPhv;
use App\Models\CarReservationPayment;
use App\Models\CarRoadTax;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OtherPayment;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class VehicleProfitLossReportService
{
    public const POSTING_ALL = 'all';

    public const POSTING_POSTED = 'posted';

    public const POSTING_PENDING = 'pending';

    private const INSURANCE_COUNT_FROM = '2026-10-01';

    private const INSURANCE_DAYS_IN_YEAR = 365;

    /**
     * @return array{
     *     car: Car,
     *     from: ?Carbon,
     *     to: ?Carbon,
     *     posting_filter: string,
     *     lines: list<array<string, mixed>>,
     *     summary: array<string, float>,
     *     error: ?string
     * }
     */
    public function build(
        Car $car,
        int $tenantId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $postingFilter = self::POSTING_ALL
    ): array {
        if ((int) $car->tenant_id !== $tenantId) {
            return $this->emptyResult($car, $from, $to, $postingFilter, 'Vehicle not found for this company.');
        }

        $lines = collect()
            ->merge($this->rentalInvoicedLines($car, $tenantId))
            ->merge($this->rentalCollectedLines($car, $tenantId))
            ->merge($this->damageInvoicedLines($car, $tenantId))
            ->merge($this->damageCollectedLines($car, $tenantId))
            ->merge($this->otherIncomeLines($car, $tenantId))
            ->merge($this->purchaseLine($car))
            ->merge($this->motLines($car))
            ->merge($this->phvLines($car))
            ->merge($this->roadTaxLines($car))
            ->merge($this->insuranceLines($car, $from, $to))
            ->merge($this->expenseLines($car, $tenantId));

        $lines = $lines
            ->filter(fn (array $line) => $this->dateInRange($line['date'] ?? null, $from, $to))
            ->filter(fn (array $line) => $this->matchesPostingFilter($line['posting_status'] ?? null, $postingFilter))
            ->sortByDesc(fn (array $line) => [$line['date'] ?? '', $line['sort_key'] ?? ''])
            ->values()
            ->all();

        $summary = $this->summarize($lines);

        return [
            'car' => $car,
            'from' => $from,
            'to' => $to,
            'posting_filter' => $postingFilter,
            'lines' => $lines,
            'summary' => $summary,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(Car $car, ?Carbon $from, ?Carbon $to, string $postingFilter, string $error): array
    {
        return [
            'car' => $car,
            'from' => $from,
            'to' => $to,
            'posting_filter' => $postingFilter,
            'lines' => [],
            'summary' => $this->summarize([]),
            'error' => $error,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, float>
     */
    public function summarize(array $lines): array
    {
        $keys = [
            'rental_invoiced',
            'rental_collected',
            'damage_invoiced',
            'damage_collected',
            'sale_income',
            'other_income',
            'purchase',
            'insurance',
            'mot_phv',
            'tax',
            'repairs',
            'recovery',
            'other_expense',
        ];

        $totals = array_fill_keys($keys, 0.0);

        foreach ($lines as $line) {
            $bucket = (string) ($line['summary_bucket'] ?? '');
            if (isset($totals[$bucket])) {
                $totals[$bucket] += (float) ($line['amount'] ?? 0);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        $totalExpenses = round(
            $totals['purchase']
            + $totals['insurance']
            + $totals['mot_phv']
            + $totals['tax']
            + $totals['repairs']
            + $totals['recovery']
            + $totals['other_expense'],
            2
        );

        $incomeCollected = round(
            $totals['rental_collected']
            + $totals['damage_collected']
            + $totals['sale_income']
            + $totals['other_income'],
            2
        );

        $incomeInvoiced = round(
            $totals['rental_invoiced']
            + $totals['damage_invoiced']
            + $totals['sale_income']
            + $totals['other_income'],
            2
        );

        $totals['total_expenses'] = $totalExpenses;
        $totals['total_income_collected'] = $incomeCollected;
        $totals['total_income_invoiced'] = $incomeInvoiced;
        $totals['net_profit_collected'] = round($incomeCollected - $totalExpenses, 2);
        $totals['net_profit_invoiced'] = round($incomeInvoiced - $totalExpenses, 2);

        return $totals;
    }

    private function rentalInvoicedLines(Car $car, int $tenantId): Collection
    {
        $invoices = Invoice::query()
            ->where('invoice_type', 'agreement')
            ->whereHas('sourceAgreement', fn ($q) => $q
                ->where('tenant_id', $tenantId)
                ->where('car_id', $car->id))
            ->with('sourceAgreement.driver')
            ->get();

        return $invoices->map(function (Invoice $invoice) use ($car) {
            $driver = $invoice->sourceAgreement?->driver?->full_name ?? 'Driver';

            return $this->line(
                date: $invoice->invoice_date?->format('Y-m-d'),
                direction: 'in',
                summaryBucket: 'rental_invoiced',
                categoryLabel: 'Rental income (invoiced)',
                description: 'Rent invoice '.$invoice->invoice_no.' — '.$driver,
                amount: (float) $invoice->total_amount,
                postingStatus: null,
                sourceType: 'invoice',
                sourceId: $invoice->id,
                sourceLabel: $invoice->invoice_no,
                sortKey: 'inv-'.$invoice->id,
            );
        });
    }

    private function rentalCollectedLines(Car $car, int $tenantId): Collection
    {
        $allocations = PaymentAllocation::query()
            ->whereHas('invoice', function ($q) use ($car, $tenantId) {
                $q->where('invoice_type', 'agreement')
                    ->whereHas('sourceAgreement', fn ($a) => $a
                        ->where('tenant_id', $tenantId)
                        ->where('car_id', $car->id));
            })
            ->with(['payment', 'invoice.sourceAgreement.driver'])
            ->get();

        return $allocations->map(function (PaymentAllocation $allocation) {
            $payment = $allocation->payment;
            $invoice = $allocation->invoice;
            $driver = $invoice?->sourceAgreement?->driver?->full_name ?? 'Driver';

            return $this->line(
                date: $payment?->payment_date?->format('Y-m-d'),
                direction: 'in',
                summaryBucket: 'rental_collected',
                categoryLabel: 'Rental income (collected)',
                description: 'Payment '.$payment?->payment_no.' → '.$invoice?->invoice_no.' — '.$driver,
                amount: (float) $allocation->allocated_amount,
                postingStatus: $payment?->posting_status,
                sourceType: 'payment',
                sourceId: $payment?->id,
                sourceLabel: $payment?->payment_no,
                sortKey: 'alloc-'.$allocation->id,
            );
        });
    }

    private function damageInvoicedLines(Car $car, int $tenantId): Collection
    {
        $invoices = Invoice::query()
            ->where('invoice_type', 'agreement_additional_charge')
            ->whereHas('sourceAgreement', fn ($q) => $q
                ->where('tenant_id', $tenantId)
                ->where('car_id', $car->id))
            ->with(['sourceAgreement.driver', 'sourceAgreement.additionalCharges'])
            ->get();

        return $invoices->map(function (Invoice $invoice) {
            $charge = $invoice->sourceAgreement?->additionalCharges
                ?->firstWhere('invoice_id', $invoice->id);
            $typeLabel = $charge?->typeLabel() ?? 'Damage / excess';

            return $this->line(
                date: $invoice->invoice_date?->format('Y-m-d'),
                direction: 'in',
                summaryBucket: 'damage_invoiced',
                categoryLabel: 'Claims / damage / excess (invoiced)',
                description: $typeLabel.' — invoice '.$invoice->invoice_no,
                amount: (float) $invoice->total_amount,
                postingStatus: null,
                sourceType: 'invoice',
                sourceId: $invoice->id,
                sourceLabel: $invoice->invoice_no,
                sortKey: 'dinv-'.$invoice->id,
            );
        });
    }

    private function damageCollectedLines(Car $car, int $tenantId): Collection
    {
        $allocations = PaymentAllocation::query()
            ->whereHas('invoice', function ($q) use ($car, $tenantId) {
                $q->where('invoice_type', 'agreement_additional_charge')
                    ->whereHas('sourceAgreement', fn ($a) => $a
                        ->where('tenant_id', $tenantId)
                        ->where('car_id', $car->id));
            })
            ->with(['payment', 'invoice'])
            ->get();

        return $allocations->map(function (PaymentAllocation $allocation) {
            $payment = $allocation->payment;
            $invoice = $allocation->invoice;

            return $this->line(
                date: $payment?->payment_date?->format('Y-m-d'),
                direction: 'in',
                summaryBucket: 'damage_collected',
                categoryLabel: 'Claims / damage / excess (collected)',
                description: 'Payment '.$payment?->payment_no.' → '.$invoice?->invoice_no,
                amount: (float) $allocation->allocated_amount,
                postingStatus: $payment?->posting_status,
                sourceType: 'payment',
                sourceId: $payment?->id,
                sourceLabel: $payment?->payment_no,
                sortKey: 'dalloc-'.$allocation->id,
            );
        });
    }

    private function otherIncomeLines(Car $car, int $tenantId): Collection
    {
        $lines = collect();

        if (Schema::hasTable('other_payments')) {
            $otherPayments = OtherPayment::query()
                ->where('tenant_id', $tenantId)
                ->where('car_id', $car->id)
                ->get();
        } else {
            $otherPayments = collect();
        }

        foreach ($otherPayments as $otherPayment) {
            $isSale = $this->isSaleOtherPayment($otherPayment);
            $lines->push($this->line(
                date: $otherPayment->payment_date?->format('Y-m-d'),
                direction: 'in',
                summaryBucket: $isSale ? 'sale_income' : 'other_income',
                categoryLabel: $isSale ? 'Sale income' : 'Other income',
                description: trim((string) $otherPayment->title),
                amount: (float) $otherPayment->amount,
                postingStatus: $otherPayment->posting_status,
                sourceType: 'other_payment',
                sourceId: $otherPayment->id,
                sourceLabel: $otherPayment->title,
                sortKey: 'op-'.$otherPayment->id,
            ));
        }

        if (Schema::hasTable('car_reservation_payments') && Schema::hasTable('car_reservations')) {
            $reservationPayments = CarReservationPayment::query()
                ->whereHas('reservation', fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->where('car_id', $car->id))
                ->with('reservation')
                ->get();

            foreach ($reservationPayments as $reservationPayment) {
                $lines->push($this->line(
                    date: $reservationPayment->created_at?->format('Y-m-d'),
                    direction: 'in',
                    summaryBucket: 'other_income',
                    categoryLabel: 'Other income',
                    description: 'Reservation payment #'.$reservationPayment->id,
                    amount: (float) $reservationPayment->amount,
                    postingStatus: $reservationPayment->posting_status,
                    sourceType: 'reservation_payment',
                    sourceId: $reservationPayment->id,
                    sourceLabel: 'Reservation #'.($reservationPayment->reservation_id ?? ''),
                    sortKey: 'rp-'.$reservationPayment->id,
                ));
            }
        }

        return $lines;
    }

    private function purchaseLine(Car $car): Collection
    {
        $price = (float) ($car->purchase_price ?? 0);
        if ($price <= 0) {
            return collect();
        }

        return collect([
            $this->line(
                date: $car->purchase_date?->format('Y-m-d'),
                direction: 'out',
                summaryBucket: 'purchase',
                categoryLabel: 'Purchase cost',
                description: 'Vehicle purchase',
                amount: $price,
                postingStatus: null,
                sourceType: 'car',
                sourceId: $car->id,
                sourceLabel: $car->registration,
                sortKey: 'purchase',
            ),
        ]);
    }

    private function motLines(Car $car): Collection
    {
        if (! Schema::hasTable('car_mots')) {
            return collect();
        }

        return CarMot::query()
            ->where('car_id', $car->id)
            ->get()
            ->filter(fn (CarMot $mot) => (float) ($mot->amount ?? 0) > 0)
            ->map(fn (CarMot $mot) => $this->line(
                date: ($mot->test_date ?? $mot->expiry_date)?->format('Y-m-d'),
                direction: 'out',
                summaryBucket: 'mot_phv',
                categoryLabel: 'MOT',
                description: 'MOT'.($mot->term ? ' — '.$mot->term : ''),
                amount: (float) $mot->amount,
                postingStatus: null,
                sourceType: 'car_mot',
                sourceId: $mot->id,
                sourceLabel: 'MOT #'.$mot->id,
                sortKey: 'mot-'.$mot->id,
            ));
    }

    private function phvLines(Car $car): Collection
    {
        if (! Schema::hasTable('car_phvs')) {
            return collect();
        }

        return CarPhv::query()
            ->where('car_id', $car->id)
            ->get()
            ->filter(fn (CarPhv $phv) => (float) ($phv->amount ?? 0) > 0)
            ->map(fn (CarPhv $phv) => $this->line(
                date: $phv->start_date?->format('Y-m-d'),
                direction: 'out',
                summaryBucket: 'mot_phv',
                categoryLabel: 'PHV licence',
                description: 'PHV licence fee',
                amount: (float) $phv->amount,
                postingStatus: null,
                sourceType: 'car_phv',
                sourceId: $phv->id,
                sourceLabel: 'PHV #'.$phv->id,
                sortKey: 'phv-'.$phv->id,
            ));
    }

    private function roadTaxLines(Car $car): Collection
    {
        if (! Schema::hasTable('car_road_taxes')) {
            return collect();
        }

        return CarRoadTax::query()
            ->where('car_id', $car->id)
            ->get()
            ->filter(fn (CarRoadTax $tax) => (float) ($tax->amount ?? 0) > 0)
            ->map(fn (CarRoadTax $tax) => $this->line(
                date: $tax->start_date?->format('Y-m-d'),
                direction: 'out',
                summaryBucket: 'tax',
                categoryLabel: 'Road tax',
                description: 'Road tax'.($tax->term ? ' — '.$tax->term : ''),
                amount: (float) $tax->amount,
                postingStatus: null,
                sourceType: 'car_road_tax',
                sourceId: $tax->id,
                sourceLabel: 'Tax #'.$tax->id,
                sortKey: 'tax-'.$tax->id,
            ));
    }

    private function insuranceLines(Car $car, ?Carbon $from, ?Carbon $to): Collection
    {
        if (! Schema::hasTable('car_insurances')
            || ! Schema::hasTable('insurance_providers')
            || ! Schema::hasColumn('car_insurances', 'start_date')
            || ! Schema::hasColumn('car_insurances', 'insurance_provider_id')
            || ! Schema::hasColumn('insurance_providers', 'amount')) {
            return collect();
        }

        $today = now()->startOfDay();
        $cutoff = Carbon::parse(self::INSURANCE_COUNT_FROM)->startOfDay();

        return CarInsurance::query()
            ->where('car_id', $car->id)
            ->with('insuranceProvider')
            ->get()
            ->map(function (CarInsurance $policy) use ($from, $to, $today, $cutoff) {
                $annual = (float) ($policy->insuranceProvider?->amount ?? 0);
                if ($annual <= 0 || ! $policy->start_date) {
                    return null;
                }

                $coverStart = $policy->start_date->copy()->startOfDay();
                $coverEnd = $this->insuranceCoverEnd($policy, $today);
                if ($coverEnd->lt($coverStart)) {
                    return null;
                }

                $billStart = $coverStart->copy();
                if ($billStart->lt($cutoff)) {
                    $billStart = $cutoff->copy();
                }
                if ($from && $billStart->lt($from->copy()->startOfDay())) {
                    $billStart = $from->copy()->startOfDay();
                }

                $billEnd = $coverEnd->copy();
                if ($billEnd->gt($today)) {
                    $billEnd = $today->copy();
                }
                if ($to && $billEnd->gt($to->copy()->startOfDay())) {
                    $billEnd = $to->copy()->startOfDay();
                }

                if ($billEnd->lt($billStart)) {
                    return null;
                }

                $days = (int) $billStart->diffInDays($billEnd) + 1;
                $amount = round($annual / self::INSURANCE_DAYS_IN_YEAR * $days, 2);
                $providerName = trim((string) ($policy->insuranceProvider?->provider_name ?: 'Insurance'));
                $annualLabel = abs($annual - round($annual)) < 0.001
                    ? number_format($annual, 0)
                    : number_format($annual, 2);

                return $this->line(
                    date: $billStart->toDateString(),
                    direction: 'out',
                    summaryBucket: 'insurance',
                    categoryLabel: 'Insurance',
                    description: $providerName.' — '.$days.' days ('.$billStart->format('d M Y').' to '.$billEnd->format('d M Y').'), £'.$annualLabel.' per year',
                    amount: $amount,
                    postingStatus: null,
                    sourceType: 'car_insurance',
                    sourceId: $policy->id,
                    sourceLabel: 'Insurance #'.$policy->id,
                    sortKey: 'ins-'.$policy->id,
                );
            })
            ->filter()
            ->values();
    }

    private function insuranceCoverEnd(CarInsurance $policy, Carbon $today): Carbon
    {
        $ends = collect([
            $policy->canceled_date?->copy()->startOfDay(),
            $policy->expiry_date?->copy()->startOfDay(),
        ])->filter();

        if ($ends->isEmpty()) {
            return $today->copy();
        }

        return $ends->sortBy(fn (Carbon $date) => $date->timestamp)->first();
    }

    private function expenseLines(Car $car, int $tenantId): Collection
    {
        if (! Schema::hasTable('expenses')) {
            return collect();
        }

        return Expense::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->get()
            ->map(function (Expense $expense) {
                $bucket = $this->expenseSummaryBucket((string) $expense->type);
                $label = $expense->isDailyExpense()
                    ? 'Daily vehicle expense'
                    : ((string) ($expense->type ?: 'Expense'));

                $description = $expense->isDailyExpense()
                    ? trim((string) ($expense->title ?: $expense->description))
                    : trim((string) $expense->description);

                return $this->line(
                    date: $expense->date?->format('Y-m-d'),
                    direction: 'out',
                    summaryBucket: $bucket,
                    categoryLabel: $label,
                    description: $description,
                    amount: (float) $expense->amount,
                    postingStatus: $expense->posting_status,
                    sourceType: 'expense',
                    sourceId: $expense->id,
                    sourceLabel: 'Expense #'.$expense->id,
                    sortKey: 'exp-'.$expense->id,
                );
            });
    }

    private function expenseSummaryBucket(string $type): string
    {
        if ($type === 'Insurance') {
            return 'insurance';
        }

        if ($type === 'Road Tax') {
            return 'tax';
        }

        if ($type === 'MOT') {
            return 'mot_phv';
        }

        if (in_array($type, ['Breakdown Recovery', 'Parking'], true)) {
            return 'recovery';
        }

        if (in_array($type, [
            'Maintenance',
            'Repair',
            'Service',
            'Tyres',
            'Oil Change',
            'Brake Service',
            'Accident Damage',
            'Replacement Parts',
            'Labour Charges',
            'Emergency Repair',
            'Annual Service',
        ], true)) {
            return 'repairs';
        }

        return 'other_expense';
    }

    private function isSaleOtherPayment(OtherPayment $otherPayment): bool
    {
        $title = trim((string) $otherPayment->title);

        return str_starts_with($title, 'Sale —')
            || str_starts_with($title, 'Sale -');
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        ?string $date,
        string $direction,
        string $summaryBucket,
        string $categoryLabel,
        string $description,
        float $amount,
        ?string $postingStatus,
        string $sourceType,
        ?int $sourceId,
        ?string $sourceLabel,
        string $sortKey,
    ): array {
        return [
            'date' => $date,
            'direction' => $direction,
            'summary_bucket' => $summaryBucket,
            'category_label' => $categoryLabel,
            'description' => $description,
            'amount' => round($amount, 2),
            'posting_status' => $postingStatus,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_label' => $sourceLabel,
            'sort_key' => $sortKey,
            'registration' => null,
        ];
    }

    private function dateInRange(?string $date, ?Carbon $from, ?Carbon $to): bool
    {
        if ($date === null || $date === '') {
            return $from === null && $to === null;
        }

        try {
            $day = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        if ($from && $day->lt($from->copy()->startOfDay())) {
            return false;
        }

        if ($to && $day->gt($to->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    private function matchesPostingFilter(?string $postingStatus, string $filter): bool
    {
        if ($filter === self::POSTING_ALL) {
            return true;
        }

        if ($filter === self::POSTING_PENDING) {
            return $postingStatus === Expense::POSTING_STATUS_PENDING
                || $postingStatus === OtherPayment::POSTING_STATUS_PENDING
                || $postingStatus === \App\Models\Payment::POSTING_STATUS_PENDING
                || $postingStatus === CarReservationPayment::POSTING_STATUS_PENDING;
        }

        // Posted: explicit posted or fleet records without posting status
        if ($postingStatus === null) {
            return true;
        }

        return in_array($postingStatus, [
            Expense::POSTING_STATUS_POSTED,
            OtherPayment::POSTING_STATUS_POSTED,
            \App\Models\Payment::POSTING_STATUS_POSTED,
            CarReservationPayment::POSTING_STATUS_POSTED,
        ], true);
    }

    public function parseDateRange(?string $from, ?string $to): array
    {
        $fromCarbon = filled($from) ? Carbon::parse($from)->startOfDay() : null;
        $toCarbon = filled($to) ? Carbon::parse($to)->startOfDay() : null;

        if ($fromCarbon && $toCarbon && $fromCarbon->gt($toCarbon)) {
            return [null, null, 'From date must be on or before To date.'];
        }

        return [$fromCarbon, $toCarbon, null];
    }
}
