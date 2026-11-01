<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_delete_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_delete_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response
            ->assertNoContent()
            ->assertContent('');
        $this->assertDatabaseMissing('targets', [
            'id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => null,
        ]);
    }

    public function test_user_cannot_delete_another_users_target(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($owner, $category);
        $this->actingAs($otherUser);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $owner->id,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::query()->forceCreate([
            'name' => 'VTuber',
        ]);
    }

    private function createTarget(User $user, Category $category): Target
    {
        return Target::query()->forceCreate([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'テストターゲット',
            'target_type' => '個人',
            'memo' => null,
        ]);
    }

    private function createSchedule(User $user, Target $target): Schedule
    {
        return Schedule::query()->forceCreate([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'title' => 'テスト予定',
            'schedule_type' => '個人予定',
            'is_all_day' => true,
            'starts_at' => '2026-11-01 00:00:00',
            'ends_at' => '2026-11-01 00:00:00',
            'location' => null,
            'url' => null,
            'memo' => null,
        ]);
    }
}
