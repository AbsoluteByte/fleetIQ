<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('fleet_status')->default('available_for_rent')->after('sorn_applied_by');
            $table->date('available_from_date')->nullable()->after('fleet_status');
        });

        Schema::table('car_phvs', function (Blueprint $table) {
            $table->boolean('phv_applied')->default(false)->after('document');
            $table->date('phv_applied_date')->nullable()->after('phv_applied');
            $table->foreignId('phv_applied_by')->nullable()->after('phv_applied_date')->constrained('users')->nullOnDelete();
        });

        Schema::table('agreements', function (Blueprint $table) {
            $table->date('termination_notice_date')->nullable()->after('esign_document_path');
            $table->date('termination_available_from_date')->nullable()->after('termination_notice_date');
            $table->text('termination_notes')->nullable()->after('termination_available_from_date');
            $table->foreignId('termination_recorded_by')->nullable()->after('termination_notes')->constrained('users')->nullOnDelete();
        });

        Schema::create('car_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->date('service_date');
            $table->unsignedInteger('mileage')->nullable();
            $table->text('notes')->nullable();
            $table->string('document')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('car_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->date('reservation_date');
            $table->date('available_from_date')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_reservations');
        Schema::dropIfExists('car_services');

        Schema::table('agreements', function (Blueprint $table) {
            $table->dropForeign(['termination_recorded_by']);
            $table->dropColumn([
                'termination_notice_date',
                'termination_available_from_date',
                'termination_notes',
                'termination_recorded_by',
            ]);
        });

        Schema::table('car_phvs', function (Blueprint $table) {
            $table->dropForeign(['phv_applied_by']);
            $table->dropColumn(['phv_applied', 'phv_applied_date', 'phv_applied_by']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['fleet_status', 'available_from_date']);
        });
    }
};
