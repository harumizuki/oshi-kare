<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $copySourceCount = DB::table('schedules')
            ->whereNotNull('target_id')
            ->count();

        $hasMissingTarget = DB::table('schedules')
            ->leftJoin('targets', 'targets.id', '=', 'schedules.target_id')
            ->whereNotNull('schedules.target_id')
            ->whereNull('targets.id')
            ->exists();

        if ($hasMissingTarget) {
            throw new RuntimeException('Targetが存在しないScheduleがあるため、中間テーブルへ移行できません。');
        }

        $hasOwnerMismatch = DB::table('schedules')
            ->join('targets', 'targets.id', '=', 'schedules.target_id')
            ->whereColumn('schedules.user_id', '!=', 'targets.user_id')
            ->exists();

        if ($hasOwnerMismatch) {
            throw new RuntimeException('ScheduleとTargetの所有者が一致しないため、中間テーブルへ移行できません。');
        }

        Schema::create('schedule_target', function (Blueprint $table) {
            $table->foreignId('schedule_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('target_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unique(['schedule_id', 'target_id']);
            $table->index(['target_id', 'schedule_id']);
        });

        if ($copySourceCount > 0) {
            DB::table('schedule_target')->insertUsing(
                ['schedule_id', 'target_id'],
                DB::table('schedules')
                    ->select(['id', 'target_id'])
                    ->whereNotNull('target_id')
            );
        }

        $copiedCount = DB::table('schedule_target')->count();

        if ($copiedCount !== $copySourceCount) {
            throw new RuntimeException('中間テーブルへのコピー件数が移行元と一致しません。');
        }

        $hasCopiedOwnerMismatch = DB::table('schedule_target')
            ->join('schedules', 'schedules.id', '=', 'schedule_target.schedule_id')
            ->join('targets', 'targets.id', '=', 'schedule_target.target_id')
            ->whereColumn('schedules.user_id', '!=', 'targets.user_id')
            ->exists();

        if ($hasCopiedOwnerMismatch) {
            throw new RuntimeException('コピー後のScheduleとTargetの所有者が一致しません。');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_target');
    }
};
