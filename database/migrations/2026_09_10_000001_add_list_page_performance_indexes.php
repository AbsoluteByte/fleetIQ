<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->index(['tenant_id', 'status_id'], 'agreements_tenant_status_index');
            $table->index(['tenant_id', 'car_id'], 'agreements_tenant_car_index');
            $table->index('driver_id', 'agreements_driver_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['driver_id', 'status', 'balance_amount'], 'invoices_driver_status_balance_index');
            $table->index('invoice_date', 'invoices_invoice_date_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['driver_id', 'posting_status', 'payment_date'], 'payments_driver_posting_date_index');
        });

        if (Schema::hasTable('payment_allocations')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                if (! $this->hasIndex('payment_allocations', 'payment_allocations_payment_id_index')) {
                    $table->index('payment_id', 'payment_allocations_payment_id_index');
                }
                if (! $this->hasIndex('payment_allocations', 'payment_allocations_invoice_id_index')) {
                    $table->index('invoice_id', 'payment_allocations_invoice_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropIndex('agreements_tenant_status_index');
            $table->dropIndex('agreements_tenant_car_index');
            $table->dropIndex('agreements_driver_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_driver_status_balance_index');
            $table->dropIndex('invoices_invoice_date_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_driver_posting_date_index');
        });

        if (Schema::hasTable('payment_allocations')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->dropIndex('payment_allocations_payment_id_index');
                $table->dropIndex('payment_allocations_invoice_id_index');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => ($index->name ?? '') === $indexName);
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }
};
