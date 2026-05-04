<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('insurance_providers', 'notify_before_expiry_days')) {
                $table->unsignedInteger('notify_before_expiry_days')->nullable()->after('expiry_date');
            }
        });

        Schema::create('car_insurance_coverage_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('activated_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->index(['car_id', 'deactivated_at']);
        });

        // Car coverage status: Active + Inactive (for assigning to vehicles)
        $existsInactive = DB::table('statuses')
            ->where('type', 'insurance')
            ->where('name', 'Inactive')
            ->exists();
        if (! $existsInactive) {
            DB::table('statuses')->insert([
                'name' => 'Inactive',
                'type' => 'insurance',
                'color' => '#6c757d',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('car_insurances', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('expiry_date')->nullable()->change();
            $table->integer('notify_before_expiry')->nullable()->change();
            $table->unsignedBigInteger('insurance_provider_id')->nullable()->change();
        });

        // One current row per vehicle: merge duplicates into newest id
        foreach (DB::table('car_insurances')->select('car_id')->groupBy('car_id')->havingRaw('COUNT(*) > 1')->pluck('car_id') as $carId) {
            $keepId = DB::table('car_insurances')->where('car_id', $carId)->max('id');
            DB::table('car_insurances')->where('car_id', $carId)->where('id', '!=', $keepId)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('car_insurance_coverage_periods');

        Schema::table('insurance_providers', function (Blueprint $table) {
            if (Schema::hasColumn('insurance_providers', 'notify_before_expiry_days')) {
                $table->dropColumn('notify_before_expiry_days');
            }
        });
    }
};
