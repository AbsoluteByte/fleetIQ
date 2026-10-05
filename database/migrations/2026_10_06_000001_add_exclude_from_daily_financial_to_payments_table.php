<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'exclude_from_daily_financial')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('exclude_from_daily_financial')->default(false)->after('posting_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'exclude_from_daily_financial')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('exclude_from_daily_financial');
        });
    }
};
