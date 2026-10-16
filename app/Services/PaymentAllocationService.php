<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Driver;
use App\Models\DriverCreditTransactionLine;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, float>
     */
    public function pendingDfsAmountsForInvoices(Collection $invoices): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        $targetInvoiceIds = $invoices
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $targetInvoiceIdSet = array_flip($targetInvoiceIds);

        $driverIds = $invoices
            ->pluck('driver_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($driverIds === []) {
            return [];
        }

        $openInvoicesByDriver = Invoice::query()
            ->whereIn('driver_id', $driverIds)
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->groupBy('driver_id');

        $pendingPayments = Payment::query()
            ->pending()
            ->whereIn('driver_id', $driverIds)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $amounts = [];

        foreach ($pendingPayments as $payment) {
            $driverInvoices = $openInvoicesByDriver->get($payment->driver_id, collect());
            $projected = $this->projectPendingPaymentAllocations($payment, $driverInvoices);

            foreach ($projected as $invoiceId => $amount) {
                if (! isset($targetInvoiceIdSet[$invoiceId])) {
                    continue;
                }

                $amounts[$invoiceId] = round(($amounts[$invoiceId] ?? 0) + $amount, 2);
            }
        }

        return $amounts;
    }

    public function clearOutstandingInvoices(Driver $driver): Payment
    {
        return DB::transaction(function () use ($driver) {
            $invoices = Invoice::query()
                ->where('driver_id', $driver->id)
                ->where('balance_amount', '>', 0)
                ->where('status', '!=', 'cancelled')
                ->orderBy('invoice_date')
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $allocations = [];
            foreach ($invoices as $invoice) {
                $available = round(max((float) $invoice->balance_amount - $invoice->reserved_credit_amount, 0), 2);
                if ($available <= 0) {
                    continue;
                }

                $allocations[$invoice->id] = $available;
            }

            $amount = round(array_sum($allocations), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'driver' => 'This driver has no unpaid invoices to clear.',
                ]);
            }

            $payment = $this->createPayment($driver, [
                'payment_method' => 'Cash',
                'bank_account_id' => null,
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'notes' => Payment::MANUAL_CLEAR_NOTE,
                'exclude_from_daily_financial' => true,
            ], false, $allocations, false);

            return $this->postPayment($payment);
        });
    }

    public function createPayment(
        Driver $driver,
        array $paymentData,
        bool $autoManageInvoices,
        array $manualAllocations = [],
        bool $postImmediately = false
    ): Payment {
        return DB::transaction(function () use ($driver, $paymentData, $autoManageInvoices, $manualAllocations, $postImmediately) {
            $payment = $driver->payments()->create(array_merge([
                'posting_status' => $postImmediately ? Payment::POSTING_STATUS_POSTED : Payment::POSTING_STATUS_PENDING,
                'created_by' => Auth::id(),
                'auto_allocate' => $autoManageInvoices,
                'pending_manual_allocations' => $autoManageInvoices ? null : $manualAllocations,
            ], $paymentData));

            if ($postImmediately) {
                $this->postPayment($payment);
            }

            return $payment->fresh(['driver', 'allocations.invoice']);
        });
    }

    /**
     * @param  list<string>  $invoiceTypes
     */
    public function createPaymentForInvoices(
        Driver $driver,
        array $paymentData,
        int $sourceId,
        array $invoiceTypes,
        bool $postImmediately = false
    ): Payment {
        return DB::transaction(function () use ($driver, $paymentData, $sourceId, $invoiceTypes, $postImmediately) {
            $payment = $driver->payments()->create(array_merge([
                'posting_status' => $postImmediately ? Payment::POSTING_STATUS_POSTED : Payment::POSTING_STATUS_PENDING,
                'created_by' => Auth::id(),
                'auto_allocate' => false,
                'allocation_source_id' => $sourceId,
                'allocation_invoice_types' => $invoiceTypes,
            ], $paymentData));

            if ($postImmediately) {
                $this->postPayment($payment);
            }

            return $payment->fresh(['driver', 'allocations.invoice']);
        });
    }

    public function postPayment(Payment $payment): Payment
    {
        if ($payment->isPosted()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment) {
            $payment->update(['posting_status' => Payment::POSTING_STATUS_POSTED]);

            $this->allocatePostedPayment($payment);

            return $payment->fresh(['driver', 'allocations.invoice']);
        });
    }

    public function updatePendingPayment(
        Payment $payment,
        array $data,
        bool $autoManageInvoices,
        array $manualAllocations = []
    ): Payment {
        return DB::transaction(function () use ($payment, $data, $autoManageInvoices, $manualAllocations) {
            $payment->update(array_merge($data, [
                'auto_allocate' => $autoManageInvoices,
                'pending_manual_allocations' => $autoManageInvoices ? null : $manualAllocations,
            ]));

            return $payment->fresh(['driver', 'allocations.invoice']);
        });
    }

    public function updatePostedPayment(
        Payment $payment,
        array $data,
        DailyFinancialSheetService $sheetService,
        int $actorId
    ): Payment {
        return DB::transaction(function () use ($payment, $data, $sheetService, $actorId) {
            $payment->load(['driver', 'allocations.invoice']);
            $oldSnapshot = $this->paymentSnapshot($payment);

            $this->clearPaymentAllocations($payment);

            $payment->update($data);
            $payment->refresh();

            $this->allocatePostedPayment($payment);

            $sheetService->recordPaymentCorrection($payment->fresh(['driver']), $oldSnapshot, $actorId);

            return $payment->fresh(['driver', 'allocations.invoice']);
        });
    }

    public function deletePayment(Payment $payment, DailyFinancialSheetService $sheetService, int $actorId): void
    {
        if (Schema::hasTable('driver_credit_transaction_lines') && $payment->creditTransactionLines()->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'This payment is linked to a driver credit transaction and cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($payment, $sheetService, $actorId) {
            $payment->load(['driver', 'allocations.invoice']);

            if ($payment->isPosted()) {
                $tenantId = (int) $payment->driver->tenant_id;
                $paymentDate = $payment->payment_date?->toDateString();

                if ($paymentDate && $sheetService->isDateApproved($tenantId, $paymentDate)) {
                    $sheetService->recordPaymentReversal($payment, $actorId);
                }
            }

            $this->clearPaymentAllocations($payment);
            $payment->delete();
        });
    }

    public function deleteInvoice(
        Invoice $invoice,
        ?string $disposition,
        DailyFinancialSheetService $sheetService,
        int $actorId
    ): void {
        DB::transaction(function () use ($invoice, $disposition, $sheetService, $actorId) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->clearPendingInvoicePaymentLinks($invoice);

            $allocations = $invoice->paymentAllocations()->get();
            if ($allocations->isEmpty()) {
                $this->releaseOneTimeDiscountConsumedBy($invoice);
                $invoice->delete();

                return;
            }

            if (! in_array($disposition, ['credit', 'delete'], true)) {
                throw ValidationException::withMessages([
                    'payment_disposition' => 'Choose whether to delete the payment or add it to driver credit.',
                ]);
            }

            foreach ($allocations->groupBy('payment_id') as $invoiceAllocations) {
                $payment = Payment::query()
                    ->whereKey($invoiceAllocations->first()->payment_id)
                    ->lockForUpdate()
                    ->first();
                $released = round((float) $invoiceAllocations->sum(fn ($row) => (float) $row->allocated_amount), 2);

                if ($payment) {
                    $this->releaseCreditLinesForAllocations($invoiceAllocations, $disposition);
                }

                $oldSnapshot = null;
                if ($payment && $disposition === 'delete') {
                    $payment->load(['driver', 'allocations']);
                    $oldSnapshot = $this->paymentSnapshot($payment);
                }

                foreach ($invoiceAllocations as $allocation) {
                    $allocation->delete();
                }

                if (! $payment || $disposition === 'credit') {
                    continue;
                }

                $payment->refresh();
                $otherAllocated = round((float) $payment->allocations()->sum('allocated_amount'), 2);
                $remainingAmount = round(max((float) $payment->amount - $released, 0), 2);
                $excludedFromSheet = Schema::hasColumn('payments', 'exclude_from_daily_financial')
                    && (bool) $payment->exclude_from_daily_financial;
                $stillLinkedToCredit = Schema::hasTable('driver_credit_transaction_lines')
                    && $payment->creditTransactionLines()->exists();

                if ($otherAllocated <= 0.009 && $remainingAmount <= 0.009 && ! $stillLinkedToCredit) {
                    if ($excludedFromSheet) {
                        $payment->delete();
                    } else {
                        $this->deletePayment($payment, $sheetService, $actorId);
                    }

                    continue;
                }

                $payment->update(['amount' => max($remainingAmount, $otherAllocated)]);

                if (! $excludedFromSheet && $oldSnapshot && Schema::hasTable('daily_financial_sheets')) {
                    $sheetService->recordPaymentCorrection($payment->fresh(['driver']), $oldSnapshot, $actorId);
                }
            }

            $this->releaseOneTimeDiscountConsumedBy($invoice);
            $invoice->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentSnapshot(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'payment_no' => $payment->payment_no,
            'amount' => (float) $payment->amount,
            'payment_date' => $payment->payment_date?->toDateString(),
            'payment_method' => $payment->payment_method,
            'bank_account_id' => $payment->bank_account_id,
            'driver_name' => trim(($payment->driver->first_name ?? '').' '.($payment->driver->last_name ?? '')),
            'allocations' => $payment->allocations
                ->map(fn ($allocation) => [
                    'invoice_id' => $allocation->invoice_id,
                    'allocated_amount' => (float) $allocation->allocated_amount,
                ])
                ->all(),
        ];
    }

    private function clearPaymentAllocations(Payment $payment): void
    {
        $invoices = $payment->allocations->pluck('invoice')->filter();

        foreach ($payment->allocations as $allocation) {
            $allocation->delete();
        }

        foreach ($invoices as $invoice) {
            $invoice->refreshPaymentTotals();
        }
    }

    /**
     * @param  Collection<int, Invoice>  $driverOpenInvoices
     * @return array<int, float>
     */
    private function projectPendingPaymentAllocations(Payment $payment, Collection $driverOpenInvoices): array
    {
        if ($payment->allocation_source_id && is_array($payment->allocation_invoice_types)) {
            return $this->projectAutoAllocateForSource(
                (float) $payment->amount,
                $driverOpenInvoices,
                (int) $payment->allocation_source_id,
                $payment->allocation_invoice_types
            );
        }

        if ($payment->auto_allocate) {
            return $this->projectAutoAllocate((float) $payment->amount, $driverOpenInvoices);
        }

        if (is_array($payment->pending_manual_allocations)) {
            return $this->projectManualAllocate($payment->pending_manual_allocations);
        }

        return [];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, float>
     */
    private function projectAutoAllocate(float $paymentAmount, Collection $invoices): array
    {
        $remainingAmount = $paymentAmount;
        $result = [];

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $invoiceBalance = max((float) $invoice->balance_amount - $invoice->reserved_credit_amount, 0);
            $allocatedAmount = min($remainingAmount, $invoiceBalance);

            if ($allocatedAmount <= 0) {
                continue;
            }

            $invoiceId = (int) $invoice->id;
            $result[$invoiceId] = round(($result[$invoiceId] ?? 0) + $allocatedAmount, 2);
            $remainingAmount = round($remainingAmount - $allocatedAmount, 2);
        }

        return $result;
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  list<string>  $invoiceTypes
     * @return array<int, float>
     */
    private function projectAutoAllocateForSource(
        float $paymentAmount,
        Collection $invoices,
        int $sourceId,
        array $invoiceTypes
    ): array {
        $scopedInvoices = $invoices
            ->filter(fn (Invoice $invoice) => (int) $invoice->source_id === $sourceId
                && in_array($invoice->invoice_type, $invoiceTypes, true));

        return $this->projectAutoAllocate($paymentAmount, $scopedInvoices->values());
    }

    /**
     * @param  array<int|string, mixed>  $manualAllocations
     * @return array<int, float>
     */
    private function projectManualAllocate(array $manualAllocations): array
    {
        $result = [];

        foreach ($manualAllocations as $invoiceId => $amount) {
            $amount = round((float) $amount, 2);

            if ($amount <= 0) {
                continue;
            }

            $id = (int) $invoiceId;
            $result[$id] = round(($result[$id] ?? 0) + $amount, 2);
        }

        return $result;
    }

    private function allocatePostedPayment(Payment $payment): void
    {
        if ($payment->allocation_source_id && is_array($payment->allocation_invoice_types)) {
            $this->autoAllocateForSource($payment, $payment->allocation_source_id, $payment->allocation_invoice_types);
        } elseif ($payment->auto_allocate) {
            $this->autoAllocate($payment);
        } elseif (is_array($payment->pending_manual_allocations)) {
            $this->manualAllocate($payment, $payment->pending_manual_allocations);
        }
    }

    private function autoAllocate(Payment $payment): void
    {
        $remainingAmount = (float) $payment->amount;

        $invoices = Invoice::query()
            ->where('driver_id', $payment->driver_id)
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date')
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $invoiceBalance = max((float) $invoice->balance_amount - $invoice->reserved_credit_amount, 0);
            $allocatedAmount = min($remainingAmount, $invoiceBalance);

            if ($allocatedAmount <= 0) {
                continue;
            }

            $this->createAllocation($payment, $invoice, $allocatedAmount);
            $remainingAmount = round($remainingAmount - $allocatedAmount, 2);
        }
    }

    /**
     * @param  list<string>  $invoiceTypes
     */
    private function autoAllocateForSource(Payment $payment, int $sourceId, array $invoiceTypes): void
    {
        $remainingAmount = (float) $payment->amount;

        $invoices = Invoice::query()
            ->where('driver_id', $payment->driver_id)
            ->where('source_id', $sourceId)
            ->whereIn('invoice_type', $invoiceTypes)
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date')
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $invoiceBalance = max((float) $invoice->balance_amount - $invoice->reserved_credit_amount, 0);
            $allocatedAmount = min($remainingAmount, $invoiceBalance);

            if ($allocatedAmount <= 0) {
                continue;
            }

            $this->createAllocation($payment, $invoice, $allocatedAmount);
            $remainingAmount = round($remainingAmount - $allocatedAmount, 2);
        }
    }

    private function manualAllocate(Payment $payment, array $manualAllocations): void
    {
        $paymentAmount = (float) $payment->amount;
        $totalAllocated = 0;

        foreach ($manualAllocations as $invoiceId => $amount) {
            $amount = round((float) $amount, 2);

            if ($amount <= 0) {
                continue;
            }

            $totalAllocated = round($totalAllocated + $amount, 2);

            if ($totalAllocated > $paymentAmount) {
                throw ValidationException::withMessages([
                    'allocations' => 'Total allocated amount cannot be greater than payment amount.',
                ]);
            }

            $invoice = Invoice::query()
                ->where('driver_id', $payment->driver_id)
                ->where('balance_amount', '>', 0)
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'allocations' => 'Selected invoice is not available for allocation.',
                ]);
            }

            $availableInvoiceBalance = max(
                (float) $invoice->balance_amount - $invoice->reserved_credit_amount,
                0
            );
            if ($amount > $availableInvoiceBalance) {
                throw ValidationException::withMessages([
                    "allocations.{$invoiceId}" => 'Allocation cannot be greater than invoice balance.',
                ]);
            }

            $this->createAllocation($payment, $invoice, $amount);
        }
    }

    public function allocateAvailableCreditToInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->where('balance_amount', '>', 0)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                return;
            }

            $remainingBalance = max(
                (float) $invoice->balance_amount - $invoice->reserved_credit_amount,
                0
            );

            $payments = Payment::query()
                ->where('driver_id', $invoice->driver_id)
                ->posted()
                ->orderBy('payment_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                if ($remainingBalance <= 0) {
                    break;
                }

                $availableCredit = $payment->spendable_credit_amount;

                if ($availableCredit <= 0) {
                    continue;
                }

                $amountToAllocate = min($availableCredit, $remainingBalance);
                $this->createAllocation($payment, $invoice, $amountToAllocate);
                $remainingBalance = round($remainingBalance - $amountToAllocate, 2);
                $invoice->refresh();
            }
        });
    }

    private function createAllocation(Payment $payment, Invoice $invoice, float $allocatedAmount): void
    {
        $payment->allocations()->create([
            'invoice_id' => $invoice->id,
            'allocated_amount' => round($allocatedAmount, 2),
        ]);

        $invoice->refreshPaymentTotals();
    }

    private function clearPendingInvoicePaymentLinks(Invoice $invoice): void
    {
        $this->releasePendingCreditReservations($invoice);
        $this->removePendingManualAllocationTarget($invoice);
    }

    private function releasePendingCreditReservations(Invoice $invoice): void
    {
        if (! Schema::hasTable('driver_credit_transaction_lines')) {
            return;
        }

        $lines = DriverCreditTransactionLine::query()
            ->where('target_invoice_id', $invoice->id)
            ->where('status', DriverCreditTransactionLine::STATUS_RESERVED)
            ->whereHas('transaction', fn ($query) => $query->pending())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lines->groupBy('driver_credit_transaction_id') as $transactionLines) {
            $transaction = $transactionLines->first()->transaction;
            foreach ($transactionLines as $line) {
                $line->delete();
            }

            $remainingAmount = round((float) $transaction->lines()->sum('amount'), 2);
            if ($remainingAmount <= 0) {
                $transaction->delete();
            } else {
                $transaction->update(['amount' => $remainingAmount]);
            }
        }
    }

    private function removePendingManualAllocationTarget(Invoice $invoice): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'pending_manual_allocations')) {
            return;
        }

        Payment::query()
            ->where('driver_id', $invoice->driver_id)
            ->pending()
            ->whereNotNull('pending_manual_allocations')
            ->lockForUpdate()
            ->get()
            ->each(function (Payment $payment) use ($invoice) {
                $allocations = $payment->pending_manual_allocations ?? [];
                if (! array_key_exists((string) $invoice->id, $allocations)
                    && ! array_key_exists($invoice->id, $allocations)) {
                    return;
                }

                unset($allocations[(string) $invoice->id], $allocations[$invoice->id]);
                $payment->update([
                    'pending_manual_allocations' => $allocations === [] ? null : $allocations,
                ]);
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PaymentAllocation>  $allocations
     */
    private function releaseCreditLinesForAllocations($allocations, string $disposition): void
    {
        if (! Schema::hasTable('driver_credit_transaction_lines')
            || ! Schema::hasColumn('payment_allocations', 'driver_credit_transaction_line_id')) {
            return;
        }

        foreach ($allocations as $allocation) {
            $lineId = $allocation->driver_credit_transaction_line_id ?? null;
            if (! $lineId) {
                continue;
            }

            $line = DriverCreditTransactionLine::query()->whereKey($lineId)->lockForUpdate()->first();
            if (! $line) {
                continue;
            }

            $released = (float) $allocation->allocated_amount;
            $lineAmount = (float) $line->amount;
            if ($released + 0.001 >= $lineAmount) {
                if ($disposition === 'delete') {
                    $line->delete();
                } else {
                    $line->update([
                        'amount' => 0,
                        'status' => DriverCreditTransactionLine::STATUS_REVERSED,
                        'target_invoice_id' => null,
                    ]);
                }

                continue;
            }

            $line->update(['amount' => round($lineAmount - $released, 2)]);
        }
    }

    private function releaseOneTimeDiscountConsumedBy(Invoice $invoice): void
    {
        if (! Schema::hasTable('agreements')
            || ! Schema::hasColumn('agreements', 'discount_is_one_time')
            || ! Schema::hasColumn('agreements', 'discount_consumed_invoice_id')
            || ! $invoice->source_id) {
            return;
        }

        $agreement = Agreement::query()->find($invoice->source_id);
        if (! $agreement
            || ! $agreement->discount_is_one_time
            || (int) $agreement->discount_consumed_invoice_id !== (int) $invoice->id) {
            return;
        }

        $agreement->forceFill([
            'discount_consumed_at' => null,
            'discount_consumed_invoice_id' => null,
        ])->save();
    }
}
