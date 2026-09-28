<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_get_schedule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);

        // Act
        $response = $this->getJson("/api/schedules/{$schedule->id}");

        // Assert
        $response->assertUnauthorized();
    }

    public function test_owner_can_get_timed_schedule_with_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, [
            'target_id' => $target->id,
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/schedules/{$schedule->id}");

        // Assert
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $schedule->id,
                    'title' => 'テスト予定',
                    'schedule_type' => '配信',
                    'starts_at' => '2026-10-05T12:00:00+00:00',
                    'ends_at' => '2026-10-05T14:00:00+00:00',
                    'is_all_day' => false,
                    'location' => 'テスト会場',
                    'url' => 'https://example.com/schedule',
                    'memo' => 'テストメモ',
                    'target' => [
                        'id' => $target->id,
                        'name' => 'テストターゲット',
                    ],
                ],
            ]);
    }

    public function test_owner_can_get_all_day_schedule_without_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user, [
            'target_id' => null,
            'title' => 'テスト終日予定',
            'schedule_type' => '記念日',
            'is_all_day' => true,
            'starts_at' => '2026-10-05 00:00:00',
            'ends_at' => '2026-10-06 00:00:00',
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/schedules/{$schedule->id}");

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2026-10-05')
            ->assertJsonPath('data.ends_at', '2026-10-06')
            ->assertJsonPath('data.is_all_day', true)
            ->assertJsonPath('data.target', null);
    }

    public function test_user_cannot_get_another_users_schedule(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $schedule = $this->createSchedule($owner);
        $this->actingAs($otherUser);

        // Act
        $response = $this->getJson("/api/schedules/{$schedule->id}");

        // Assert
        $response
            ->assertForbidden()
            ->assertJsonMissing(['title' => 'テスト予定']);
    }

    public function test_nonexistent_schedule_returns_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules/999999');

        // Assert
        $response->assertNotFound();
    }

    private function createCategory(): Category
    {
        return Category::query()->forceCreate([
            'name' => 'テストカテゴリー',
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSchedule(User $user, array $attributes = []): Schedule
    {
        return Schedule::query()->forceCreate(array_merge([
            'user_id' => $user->id,
            'target_id' => null,
            'title' => 'テスト予定',
            'schedule_type' => '配信',
            'is_all_day' => false,
            'starts_at' => '2026-10-05 12:00:00',
            'ends_at' => '2026-10-05 14:00:00',
            'location' => 'テスト会場',
            'url' => 'https://example.com/schedule',
            'memo' => 'テストメモ',
        ], $attributes));
    }
}
