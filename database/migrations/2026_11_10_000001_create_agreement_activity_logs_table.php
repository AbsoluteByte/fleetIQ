<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agreement_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->text('summary');
            $table->json('changes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_activity_logs');
    }
};
