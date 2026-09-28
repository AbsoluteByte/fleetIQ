<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\BankAccount;
use App\Services\AgreementCarRentalInvoiceService;
use App\Services\AgreementPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AgreementCarRentalInvoiceController extends Controller
{
    public function show(Agreement $agreement, AgreementCarRentalInvoiceService $service)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $agreement->tenant_id !== $tenant->id) {
            abort(403, 'Unauthorized access');
        }

        $agreement->loadMissing(['carRentalInvoice', 'driver', 'car.carModel', 'company', 'car.company']);
        $eligible = $service->eligibleRentPayments($agreement);
        $existing = $agreement->carRentalInvoice;

        $includeCash = (bool) old('include_cash', $existing?->include_cash ?? false);
        $bankFilterId = old('bank_account_filter_id', $existing?->bank_account_filter_id);
        $bankFilterId = $bankFilterId !== null && $bankFilterId !== '' ? (int) $bankFilterId : null;

        $defaultSelected = $existing
            ? collect($existing->payment_snapshot ?? [])->pluck('payment_id')->map(fn ($id) => (int) $id)->all()
            : $service->defaultSelectedPaymentIds($eligible, $includeCash, $bankFilterId);

        $bankAccounts = BankAccount::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('short_name')
            ->orderBy('bank_name')
            ->get();

        return view('backend.agreements.car_rental_invoice_form', [
            'agreement' => $agreement,
            'eligiblePayments' => $eligible,
            'bankAccounts' => $bankAccounts,
            'existingRecord' => $existing,
            'defaultSelectedPaymentIds' => $defaultSelected,
            'includeCash' => $includeCash,
            'bankAccountFilterId' => $bankFilterId,
        ]);
    }

    public function store(Request $request, Agreement $agreement, AgreementCarRentalInvoiceService $service)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $agreement->tenant_id !== $tenant->id) {
            abort(403, 'Unauthorized access');
        }

        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'include_cash' => 'nullable|boolean',
            'bank_account_filter_id' => [
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
            ],
            'weekly_insurance_amount' => 'required|numeric|min:0',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'vat_registration_number' => 'nullable|string|max:64',
            'rental_start_date' => 'nullable|date',
            'rental_end_date' => 'nullable|date|after_or_equal:rental_start_date',
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'integer',
        ]);

        $validated['include_cash'] = $request->boolean('include_cash');
        $validated['bank_account_filter_id'] = $validated['bank_account_filter_id'] ?? null;
        $validated['payment_ids'] = array_values(array_unique(array_map('intval', $validated['payment_ids'])));

        try {
            $record = $service->upsert($agreement, $validated, Auth::user());
            [$pdf, $filename] = app(AgreementPdfService::class)->makeCarRentalInvoicePdf($record);

            return $pdf->stream($filename);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()
                ->route('agreements.car-rental-invoice.show', $agreement)
                ->withInput()
                ->with('error', 'Failed to generate car rental invoice: '.$e->getMessage());
        }
    }
}
