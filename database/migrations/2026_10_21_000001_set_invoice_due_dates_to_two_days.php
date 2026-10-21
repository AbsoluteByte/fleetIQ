<?php

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $agreementInvoiceTypes = [
        'agreement',
        'agreement_deposit',
        'agreement_additional_charge',
    ];

    public function up(): void
    {
        $this->applyDueDates(Invoice::DUE_AFTER_DAYS);
    }

    public function down(): void
    {
        $this->applyDueDates(5);
    }

    private function applyDueDates(int $days): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'invoice_date') || ! Schema::hasColumn('invoices', 'due_date')) {
            return;
        }

        $today = now()->startOfDay();

        DB::table('invoices')
            ->whereIn('invoice_type', $this->agreementInvoiceTypes)
            ->whereNotNull('invoice_date')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($days, $today) {
                foreach ($invoices as $invoice) {
                    $dueDate = Carbon::parse($invoice->invoice_date)->startOfDay()->addDays($days);
                    $update = ['due_date' => $dueDate->toDateString()];

                    if (in_array($invoice->status, ['pending', 'overdue'], true)
                        && (float) $invoice->paid_amount <= 0
                        && (float) $invoice->balance_amount > 0) {
                        $update['status'] = $dueDate->lt($today) ? 'overdue' : 'pending';
                    }

                    DB::table('invoices')->where('id', $invoice->id)->update($update);
                }
            });
    }
};
