<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_insurance_coverage_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('car_insurance_coverage_periods', 'end_date_pending')) {
                $table->boolean('end_date_pending')->default(false)->after('deactivated_by_user_id');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE car_insurance_coverage_periods MODIFY activated_at TIMESTAMP NULL');
        }
    }

    public function down(): void
    {
        Schema::table('car_insurance_coverage_periods', function (Blueprint $table) {
            if (Schema::hasColumn('car_insurance_coverage_periods', 'end_date_pending')) {
                $table->dropColumn('end_date_pending');
            }
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE car_insurance_coverage_periods MODIFY activated_at TIMESTAMP NOT NULL');
        }
    }
};
