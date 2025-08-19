<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('request_time')->nullable();
            $table->string('url')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('architecture')->nullable();

            $table->timestamps();

            // Индексы
            $table->index('request_time');
            $table->index('os');
            $table->index('architecture');
            $table->index('browser');
            $table->index('ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
