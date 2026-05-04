<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->text('old_log_book_backup')->nullable()->after('old_log_book');
        });

        DB::statement('UPDATE cars SET old_log_book_backup = old_log_book WHERE old_log_book IS NOT NULL AND old_log_book != \'\'');

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('old_log_book');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->json('old_log_book')->nullable()->after('log_book_applied_date');
        });

        foreach (DB::table('cars')->select('id', 'old_log_book_backup')->cursor() as $row) {
            $payload = empty($row->old_log_book_backup) ? null : [$row->old_log_book_backup];
            DB::table('cars')->where('id', $row->id)->update(['old_log_book' => $payload]);
        }

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('old_log_book_backup');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->text('old_log_book_backup')->nullable()->after('old_log_book');
        });

        foreach (DB::table('cars')->select('id', 'old_log_book')->cursor() as $row) {
            $first = null;
            if ($row->old_log_book) {
                $decoded = json_decode($row->old_log_book, true);
                if (is_array($decoded) && $decoded !== []) {
                    $first = $decoded[0];
                }
            }
            DB::table('cars')->where('id', $row->id)->update(['old_log_book_backup' => $first]);
        }

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('old_log_book');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->string('old_log_book')->nullable()->after('log_book_applied_date');
        });

        DB::statement('UPDATE cars SET old_log_book = old_log_book_backup WHERE old_log_book_backup IS NOT NULL');

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('old_log_book_backup');
        });
    }
};
