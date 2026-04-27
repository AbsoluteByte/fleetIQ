<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->boolean('sorn_applied')->default(false)->after('log_book_applied_by');
            $table->timestamp('sorn_applied_at')->nullable()->after('sorn_applied');
            $table->foreignId('sorn_applied_by')->nullable()->after('sorn_applied_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['sorn_applied_by']);
            $table->dropColumn(['sorn_applied', 'sorn_applied_at', 'sorn_applied_by']);
        });
    }
};
