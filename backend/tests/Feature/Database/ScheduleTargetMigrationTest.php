<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ScheduleTargetMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_copies_existing_target_relationships_and_keeps_personal_schedules(): void
    {
        // Arrange
        Schema::drop('schedule_target');
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $relatedSchedule = $this->createSchedule($user, $target);
        $personalSchedule = $this->createSchedule($user);

        // Act
        $this->migration()->up();

        // Assert
        $this->assertTrue(Schema::hasColumns('schedule_target', ['schedule_id', 'target_id']));
        $this->assertDatabaseCount('schedule_target', 1);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $relatedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $personalSchedule->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $relatedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $personalSchedule->id,
            'target_id' => null,
        ]);

        $indexNames = collect(DB::select("PRAGMA index_list('schedule_target')"))
            ->pluck('name');

        $this->assertTrue($indexNames->contains('schedule_target_schedule_id_target_id_unique'));
        $this->assertTrue($indexNames->contains('schedule_target_target_id_schedule_id_index'));
    }

    public function test_unique_constraint_rejects_duplicate_relationships(): void
    {
        // Arrange
        Schema::drop('schedule_target');
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, $target);
        $this->migration()->up();

        // Assert
        $this->expectException(QueryException::class);

        // Act
        DB::table('schedule_target')->insert([
            'schedule_id' => $schedule->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_foreign_keys_delete_only_pivot_rows(): void
    {
        // Arrange
        Schema::drop('schedule_target');
        $user = User::factory()->create();
        $category = $this->createCategory();
        $scheduleDeletedTarget = $this->createTarget($user, $category, '予定削除用Target');
        $targetDeletedTarget = $this->createTarget($user, $category, 'Target削除用Target');
        $deletedSchedule = $this->createSchedule($user, $scheduleDeletedTarget, '削除する予定');
        $keptSchedule = $this->createSchedule($user, $targetDeletedTarget, '保持する予定');
        $this->migration()->up();

        // Act
        $deletedSchedule->delete();
        $targetDeletedTarget->delete();

        // Assert
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $deletedSchedule->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $targetDeletedTarget->id,
        ]);
        $this->assertDatabaseHas('targets', ['id' => $scheduleDeletedTarget->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $keptSchedule->id,
            'target_id' => null,
        ]);
    }

    public function test_migration_stops_before_creating_table_when_owners_do_not_match(): void
    {
        // Arrange
        Schema::drop('schedule_target');
        $scheduleOwner = User::factory()->create();
        $targetOwner = User::factory()->create();
        $category = $this->createCategory();
        $otherUsersTarget = $this->createTarget($targetOwner, $category);
        $this->createSchedule($scheduleOwner, $otherUsersTarget);

        // Act
        try {
            $this->migration()->up();
            $this->fail('所有者不一致のMigrationが成功しました。');
        } catch (RuntimeException $exception) {
            // Assert
            $this->assertSame(
                'ScheduleとTargetの所有者が一致しないため、中間テーブルへ移行できません。',
                $exception->getMessage()
            );
            $this->assertFalse(Schema::hasTable('schedule_target'));
        }
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_11_01_170000_create_schedule_target_table.php'
        );
    }

    private function createCategory(): Category
    {
        return Category::query()->forceCreate(['name' => 'テストカテゴリ']);
    }

    private function createTarget(
        User $user,
        Category $category,
        string $name = 'テストターゲット'
    ): Target {
        return $user->targets()->create([
            'category_id' => $category->id,
            'name' => $name,
            'target_type' => 'テスト種別',
            'memo' => null,
        ]);
    }

    private function createSchedule(
        User $user,
        ?Target $target = null,
        string $title = 'テスト予定'
    ): Schedule {
        return $user->schedules()->create([
            'target_id' => $target?->id,
            'title' => $title,
            'schedule_type' => '通常予定',
            'is_all_day' => true,
            'starts_at' => '2026-12-01',
            'ends_at' => '2026-12-01',
            'location' => null,
            'url' => null,
            'memo' => null,
        ]);
    }
}
