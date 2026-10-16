<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\DailyFinancialSheetService;
use App\Services\PaymentAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    private const INVOICE_MANAGER_EMAIL = 'jawad@samoretraders.com';

    public function __construct()
    {
        $this->middleware('role:admin|manager|user');
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless($this->canManageInvoices(), 403);
        $this->authorizeInvoice($invoice);

        abort_unless(in_array($invoice->invoice_type, ['agreement', 'agreement_deposit', 'agreement_additional_charge'], true), 422);

        $validated = $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
        ]);

        $allocated = round((float) $invoice->paymentAllocations()->sum('allocated_amount'), 2);
        $newTotal = round((float) $validated['total_amount'], 2);

        if ($newTotal < $allocated) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'total_amount' => 'Invoice total cannot be less than the amount already paid or allocated (£'.number_format($allocated, 2).').',
                ]);
        }

        $subtotal = isset($validated['subtotal'])
            ? round((float) $validated['subtotal'], 2)
            : round((float) $invoice->subtotal, 2);
        $discountAmount = round(max($subtotal - $newTotal, 0), 2);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $newTotal,
        ])->save();
        $invoice->refreshPaymentTotals();

        return redirect()->back()->with('success', 'Invoice updated successfully.');
    }

    public function destroy(
        Request $request,
        Invoice $invoice,
        PaymentAllocationService $payments,
        DailyFinancialSheetService $sheetService
    ) {
        abort_unless($this->canManageInvoices(), 403);
        $this->authorizeInvoice($invoice);

        abort_unless(in_array($invoice->invoice_type, ['agreement', 'agreement_deposit', 'agreement_additional_charge'], true), 422);

        $disposition = $request->input('payment_disposition');

        try {
            $payments->deleteInvoice(
                $invoice,
                is_string($disposition) ? $disposition : null,
                $sheetService,
                (int) Auth::id()
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Choose whether to delete the payment or add it to driver credit.';

            return redirect()->back()->with('error', $message);
        }

        $message = match ($disposition) {
            'credit' => 'Invoice deleted. The paid amount was added to driver credit.',
            'delete' => 'Invoice deleted and the payment was removed.',
            default => 'Invoice deleted successfully.',
        };

        return redirect()->back()->with('success', $message);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        $tenant = Auth::user()->currentTenant();
        abort_unless($tenant, 403);
        $invoice->loadMissing('driver');
        abort_unless($invoice->driver && (int) $invoice->driver->tenant_id === (int) $tenant->id, 403);
    }

    private function canManageInvoices(): bool
    {
        return strtolower(trim((string) Auth::user()?->email)) === self::INVOICE_MANAGER_EMAIL;
    }
}
