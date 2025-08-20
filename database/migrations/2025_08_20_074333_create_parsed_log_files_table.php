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
        Schema::create('parsed_log_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_hash', 32);
            $table->timestamp('parsed_at');

            $table->unique('file_hash');
            $table->index('file_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parsed_log_files');
    }
};
