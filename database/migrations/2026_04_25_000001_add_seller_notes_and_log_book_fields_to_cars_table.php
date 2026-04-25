<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->text('seller_notes')->nullable()->after('seller_name');
            $table->boolean('log_book_applied')->default(false)->after('seller_notes');
            $table->date('log_book_applied_date')->nullable()->after('log_book_applied');
            $table->string('old_log_book')->nullable()->after('log_book_applied_date');
            $table->foreignId('log_book_applied_by')->nullable()->after('old_log_book')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['log_book_applied_by']);
            $table->dropColumn([
                'seller_notes',
                'log_book_applied',
                'log_book_applied_date',
                'old_log_book',
                'log_book_applied_by',
            ]);
        });
    }
};
