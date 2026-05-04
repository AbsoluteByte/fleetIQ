<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_insurance_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_insurance_coverage_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->index(['car_id', 'created_at']);
        });

        foreach (DB::table('car_insurances')->whereNotNull('insurance_document')->get() as $row) {
            DB::table('car_insurance_documents')->insert([
                'tenant_id' => $row->tenant_id ?? null,
                'car_id' => $row->car_id,
                'car_insurance_coverage_period_id' => null,
                'insurance_provider_id' => $row->insurance_provider_id ?? null,
                'document' => $row->insurance_document,
                'original_name' => null,
                'created_at' => $row->updated_at ?? $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('car_insurance_documents');
    }
};
