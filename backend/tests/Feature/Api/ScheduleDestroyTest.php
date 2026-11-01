<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_delete_schedule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);

        // Act
        $response = $this->deleteJson("/api/schedules/{$schedule->id}");

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_delete_schedule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $schedule = $this->createSchedule($user, $target);
        $schedule->targets()->sync([$target->id]);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/schedules/{$schedule->id}");

        // Assert
        $response
            ->assertNoContent()
            ->assertContent('');
        $this->assertDatabaseMissing('schedules', [
            'id' => $schedule->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $schedule->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_schedule(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $schedule = $this->createSchedule($owner);
        $this->actingAs($otherUser);

        // Act
        $response = $this->deleteJson("/api/schedules/{$schedule->id}");

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_nonexistent_schedule_returns_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson('/api/schedules/999999');

        // Assert
        $response->assertNotFound();
    }

    private function createTarget(User $user): Target
    {
        $category = Category::query()->forceCreate(['name' => 'テストカテゴリー']);

        return Target::query()->forceCreate([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'テストターゲット',
            'target_type' => '個人',
            'memo' => null,
        ]);
    }

    private function createSchedule(User $user, ?Target $target = null): Schedule
    {
        return Schedule::query()->forceCreate([
            'user_id' => $user->id,
            'target_id' => $target?->id,
            'title' => 'テスト予定',
            'schedule_type' => '配信',
            'is_all_day' => false,
            'starts_at' => '2026-10-01 12:00:00',
            'ends_at' => '2026-10-01 14:00:00',
            'location' => null,
            'url' => null,
            'memo' => null,
        ]);
    }
}
