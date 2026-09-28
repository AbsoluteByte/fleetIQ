<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_car_rental_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('agreement_id');
            $table->unsignedBigInteger('company_id');
            $table->string('serial_prefix', 16);
            $table->string('serial_number', 32);
            $table->date('invoice_date');
            $table->boolean('include_cash')->default(false);
            $table->unsignedBigInteger('bank_account_filter_id')->nullable();
            $table->decimal('weekly_insurance_amount', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->string('vat_registration_number')->nullable();
            $table->date('rental_start_date')->nullable();
            $table->date('rental_end_date')->nullable();
            $table->json('payment_snapshot');
            $table->json('totals_snapshot');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamps();

            $table->unique('agreement_id');
            $table->index(['tenant_id', 'company_id', 'serial_prefix'], 'cri_tenant_co_prefix_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_car_rental_invoices');
    }
};
