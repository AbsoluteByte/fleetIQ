<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('phv_status')->default('need_to_apply')->after('damaged_notes');
            $table->date('phv_applied_date')->nullable()->after('phv_status');
            $table->foreignId('phv_applied_by')->nullable()->after('phv_applied_date')->constrained('users')->nullOnDelete();
        });

        DB::table('cars')->orderBy('id')->chunkById(100, function ($cars) {
            foreach ($cars as $car) {
                $latestPhv = DB::table('car_phvs')
                    ->where('car_id', $car->id)
                    ->orderByDesc('expiry_date')
                    ->orderByDesc('id')
                    ->first();

                if (! $latestPhv) {
                    continue;
                }

                $status = $latestPhv->expiry_date && $latestPhv->expiry_date >= now()->toDateString()
                    ? 'phv_active'
                    : ($latestPhv->phv_applied ? 'applied' : 'need_to_apply');

                DB::table('cars')
                    ->where('id', $car->id)
                    ->update([
                        'phv_status' => $status,
                        'phv_applied_date' => $latestPhv->phv_applied_date,
                        'phv_applied_by' => $latestPhv->phv_applied_by,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['phv_applied_by']);
            $table->dropColumn(['phv_status', 'phv_applied_date', 'phv_applied_by']);
        });
    }
};
