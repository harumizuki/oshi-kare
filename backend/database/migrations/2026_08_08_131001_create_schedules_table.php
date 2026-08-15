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
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
            ->constrained()
            ->onDelete('cascade');

        $table->foreignId('target_id')
            ->nullable()
            ->constrained()
            ->onDelete('cascade');

        $table->string('title');

        $table->string('schedule_type');

        $table->timestamp('starts_at');

        $table->timestamp('ends_at')
            ->nullable();

        $table->string('location')
            ->nullable();

        $table->string('url')
            ->nullable();

        $table->text('memo')
            ->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
