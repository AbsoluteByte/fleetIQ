<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_insurance_coverage_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('car_insurance_coverage_periods', 'activated_by_user_id')) {
                $table->foreignId('activated_by_user_id')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('car_insurance_coverage_periods', 'deactivated_by_user_id')) {
                $table->foreignId('deactivated_by_user_id')->nullable()->after('activated_by_user_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('car_insurance_coverage_periods', function (Blueprint $table) {
            if (Schema::hasColumn('car_insurance_coverage_periods', 'deactivated_by_user_id')) {
                $table->dropConstrainedForeignId('deactivated_by_user_id');
            }
            if (Schema::hasColumn('car_insurance_coverage_periods', 'activated_by_user_id')) {
                $table->dropConstrainedForeignId('activated_by_user_id');
            }
        });
    }
};
