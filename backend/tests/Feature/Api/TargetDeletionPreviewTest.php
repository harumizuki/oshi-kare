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
            ->assertJsonPath('data.single_schedule_count', 0)
            ->assertJsonPath('data.shared_schedule_count', 0)
            ->assertJsonPath('data.expected_schedule_state', fn ($state) => is_string($state) && strlen($state) === 64)
            ->assertJsonCount(0, 'data.schedules');
    }

    public function test_schedule_without_pivot_is_not_included_even_when_legacy_target_matches(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $schedule = $this->createSchedule($user, $target, syncPivot: false);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}/deletion-preview");

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.schedule_count', 0)
            ->assertJsonPath('data.single_schedule_count', 0)
            ->assertJsonPath('data.shared_schedule_count', 0)
            ->assertJsonCount(0, 'data.schedules');
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => $target->id,
        ]);
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
            ->assertJsonPath('data.single_schedule_count', 2)
            ->assertJsonPath('data.shared_schedule_count', 0)
            ->assertJsonCount(2, 'data.schedules')
            ->assertJsonPath('data.schedules.0.id', $earlierSchedule->id)
            ->assertJsonPath('data.schedules.0.title', '先の時間指定予定')
            ->assertJsonPath('data.schedules.0.schedule_type', '配信')
            ->assertJsonPath('data.schedules.0.starts_at', '2026-11-01T12:00:00+00:00')
            ->assertJsonPath('data.schedules.0.ends_at', null)
            ->assertJsonPath('data.schedules.0.is_all_day', false)
            ->assertJsonPath('data.schedules.0.is_shared', false)
            ->assertJsonPath('data.schedules.0.target_count', 1)
            ->assertJsonPath('data.schedules.1.id', $laterSchedule->id)
            ->assertJsonPath('data.schedules.1.starts_at', '2026-11-03')
            ->assertJsonPath('data.schedules.1.ends_at', '2026-11-03')
            ->assertJsonPath('data.schedules.1.is_all_day', true)
            ->assertJsonPath('data.schedules.1.is_shared', false)
            ->assertJsonPath('data.schedules.1.target_count', 1)
            ->assertJsonMissing(['title' => '他ユーザーの予定']);
    }

    public function test_shared_schedule_is_classified_from_pivot_count(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $schedule = $this->createSchedule($user, $target, [
            'title' => '共有予定',
        ]);
        $schedule->targets()->attach($otherTarget->id);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}/deletion-preview");

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.schedule_count', 1)
            ->assertJsonPath('data.single_schedule_count', 0)
            ->assertJsonPath('data.shared_schedule_count', 1)
            ->assertJsonPath('data.schedules.0.id', $schedule->id)
            ->assertJsonPath('data.schedules.0.is_shared', true)
            ->assertJsonPath('data.schedules.0.target_count', 2);
    }

    public function test_preview_uses_pivot_when_legacy_target_is_different(): void
    {
        // Arrange
        $user = User::factory()->create();
        $legacyTarget = $this->createTarget($user, '旧カラム');
        $pivotTarget = $this->createTarget($user, 'Pivot');
        $schedule = $this->createSchedule($user, $legacyTarget);
        $schedule->targets()->sync([$pivotTarget->id]);
        $this->actingAs($user);

        // Act
        $legacyResponse = $this->getJson("/api/targets/{$legacyTarget->id}/deletion-preview");
        $pivotResponse = $this->getJson("/api/targets/{$pivotTarget->id}/deletion-preview");

        // Assert
        $legacyResponse
            ->assertOk()
            ->assertJsonPath('data.schedule_count', 0)
            ->assertJsonCount(0, 'data.schedules');
        $pivotResponse
            ->assertOk()
            ->assertJsonPath('data.schedule_count', 1)
            ->assertJsonPath('data.single_schedule_count', 1)
            ->assertJsonPath('data.schedules.0.id', $schedule->id);
    }

    public function test_expected_schedule_state_changes_when_target_composition_changes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);

        $firstResponse = $this->getJson("/api/targets/{$target->id}/deletion-preview")
            ->assertOk();
        $firstState = $firstResponse->json('data.expected_schedule_state');
        $unchangedState = $this->getJson("/api/targets/{$target->id}/deletion-preview")
            ->assertOk()
            ->json('data.expected_schedule_state');

        // Act
        $schedule->targets()->attach($otherTarget->id);
        $secondResponse = $this->getJson("/api/targets/{$target->id}/deletion-preview")
            ->assertOk();
        $secondState = $secondResponse->json('data.expected_schedule_state');

        // Assert
        $this->assertSame($firstState, $unchangedState);
        $this->assertNotSame($firstState, $secondState);
        $this->assertSame(
            $firstResponse->json('data.schedules.0.id'),
            $secondResponse->json('data.schedules.0.id')
        );
        $secondResponse
            ->assertJsonPath('data.schedules.0.is_shared', true)
            ->assertJsonPath('data.schedules.0.target_count', 2);
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

    private function createTarget(User $user, string $name = 'テストターゲット'): Target
    {
        $category = Category::query()->forceCreate([
            'name' => 'テストカテゴリ',
        ]);

        return Target::query()->forceCreate([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => $name,
            'target_type' => '個人',
            'memo' => null,
        ]);
    }

    private function createSchedule(
        User $user,
        Target $target,
        array $attributes = [],
        bool $syncPivot = true
    ): Schedule {
        $schedule = Schedule::query()->forceCreate(array_merge([
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

        if ($syncPivot) {
            $schedule->targets()->sync([$target->id]);
        }

        return $schedule;
    }
}
