<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetDeletionPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_get_deletion_preview(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}/deletion-preview");

        // Assert
        $response->assertUnauthorized();
    }

    public function test_owner_can_get_deletion_preview_without_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}/deletion-preview");

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.target.id', $target->id)
            ->assertJsonPath('data.target.name', 'テストターゲット')
            ->assertJsonPath('data.schedule_count', 0)
            ->assertJsonCount(0, 'data.schedules');
    }

    public function test_owner_gets_only_their_related_schedules_in_start_order(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($user);
        $laterSchedule = $this->createSchedule($user, $target, [
            'title' => '後の終日予定',
            'schedule_type' => '記念日',
            'is_all_day' => true,
            'starts_at' => '2026-11-03 00:00:00',
            'ends_at' => '2026-11-03 00:00:00',
        ]);
        $earlierSchedule = $this->createSchedule($user, $target, [
            'title' => '先の時間指定予定',
            'schedule_type' => '配信',
            'is_all_day' => false,
            'starts_at' => '2026-11-01 12:00:00',
            'ends_at' => null,
        ]);
        $this->createSchedule($otherUser, $target, [
            'title' => '他ユーザーの予定',
            'starts_at' => '2026-10-01 00:00:00',
            'ends_at' => null,
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}/deletion-preview");

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.schedule_count', 2)
            ->assertJsonCount(2, 'data.schedules')
            ->assertJsonPath('data.schedules.0.id', $earlierSchedule->id)
            ->assertJsonPath('data.schedules.0.title', '先の時間指定予定')
            ->assertJsonPath('data.schedules.0.schedule_type', '配信')
            ->assertJsonPath('data.schedules.0.starts_at', '2026-11-01T12:00:00+00:00')
            ->assertJsonPath('data.schedules.0.ends_at', null)
            ->assertJsonPath('data.schedules.0.is_all_day', false)
            ->assertJsonPath('data.schedules.1.id', $laterSchedule->id)
            ->assertJsonPath('data.schedules.1.starts_at', '2026-11-03')
            ->assertJsonPath('data.schedules.1.ends_at', '2026-11-03')
            ->assertJsonPath('data.schedules.1.is_all_day', true)
            ->assertJsonMissing(['title' => '他ユーザーの予定']);
    }

    public function test_user_cannot_get_another_users_deletion_preview(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($owner);
        $this->actingAs($otherUser);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}/deletion-preview");

        // Assert
        $response->assertForbidden();
    }

    public function test_nonexistent_target_returns_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/targets/999999/deletion-preview');

        // Assert
        $response->assertNotFound();
    }

    private function createTarget(User $user): Target
    {
        $category = Category::query()->forceCreate([
            'name' => 'テストカテゴリ',
        ]);

        return Target::query()->forceCreate([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'テストターゲット',
            'target_type' => '個人',
            'memo' => null,
        ]);
    }

    private function createSchedule(User $user, Target $target, array $attributes = []): Schedule
    {
        return Schedule::query()->forceCreate(array_merge([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'title' => 'テスト予定',
            'schedule_type' => '個人予定',
            'is_all_day' => false,
            'starts_at' => '2026-11-01 00:00:00',
            'ends_at' => null,
            'location' => null,
            'url' => null,
            'memo' => null,
        ], $attributes));
    }
}
