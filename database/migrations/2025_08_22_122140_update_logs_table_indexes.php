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
        Schema::table('logs', function (Blueprint $table) {
            if (Schema::hasIndex('logs', 'ip')) {
                $table->dropIndex('ip');
            }

            $table->index('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            if (Schema::hasIndex('logs', 'url')) {
                $table->dropIndex('url');
            }

            $table->index('ip');
        });
    }
};
