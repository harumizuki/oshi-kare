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
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['target_id']);
            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['target_id']);
            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->cascadeOnDelete();
        });
    }
};
