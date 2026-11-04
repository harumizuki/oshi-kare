<?php

namespace Tests\Feature\Api;

use App\Http\Requests\DestroyTargetRequest;
use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TargetDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_delete_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_KEEP_ALL,
            'expected_schedule_ids' => [],
            'expected_schedule_state' => str_repeat('0', 64),
        ]);

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_delete_all_mode_deletes_single_schedules_and_detaches_shared_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $singleSchedule = $this->createSchedule($user, $target, ['title' => '単独予定']);
        $sharedSchedule = $this->createSchedule($user, $target, ['title' => '共有予定']);
        $sharedSchedule->targets()->attach($otherTarget->id);
        $unrelatedSchedule = $this->createSchedule($user, $otherTarget, ['title' => '無関係な予定']);
        $otherUsersSchedule = $this->createSchedule($otherUser, $target, ['title' => '他ユーザーの予定']);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_DELETE_ALL);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response->assertNoContent()->assertContent('');
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $singleSchedule->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sharedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $unrelatedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $otherUsersSchedule->id,
            'user_id' => $otherUser->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $target->id,
        ]);
    }

    public function test_keep_all_mode_keeps_single_and_shared_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $firstRemainingTarget = $this->createTarget($user, '共有先1');
        $secondRemainingTarget = $this->createTarget($user, '共有先2');
        $singleSchedule = $this->createSchedule($user, $target, ['title' => '単独予定']);
        $sharedSchedule = $this->createSchedule($user, $target, ['title' => '共有予定']);
        $sharedSchedule->targets()->attach([
            $secondRemainingTarget->id,
            $firstRemainingTarget->id,
        ]);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_KEEP_ALL);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $singleSchedule->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $singleSchedule->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sharedSchedule->id,
            'target_id' => $firstRemainingTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $firstRemainingTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $secondRemainingTarget->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $target->id,
        ]);
    }

    public function test_delete_selected_mode_deletes_selected_single_and_keeps_other_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $selectedSchedule = $this->createSchedule($user, $target, ['title' => '選択した単独予定']);
        $keptSchedule = $this->createSchedule($user, $target, ['title' => '残す単独予定']);
        $sharedSchedule = $this->createSchedule($user, $target, ['title' => '共有予定']);
        $sharedSchedule->targets()->attach($otherTarget->id);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData(
            $target,
            DestroyTargetRequest::MODE_DELETE_SELECTED,
            [$selectedSchedule->id]
        );

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $selectedSchedule->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $keptSchedule->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sharedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $target->id,
        ]);
    }

    public function test_delete_selected_mode_with_empty_selection_keeps_all_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData(
            $target,
            DestroyTargetRequest::MODE_DELETE_SELECTED,
            []
        );

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $schedule->id,
        ]);
    }

    public function test_delete_selected_mode_rejects_shared_schedule_id(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $sharedSchedule = $this->createSchedule($user, $target);
        $sharedSchedule->targets()->attach($otherTarget->id);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData(
            $target,
            DestroyTargetRequest::MODE_DELETE_SELECTED,
            [$sharedSchedule->id]
        );

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_schedule_ids');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sharedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
    }

    public function test_delete_selected_mode_requires_selected_schedule_ids(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->createSchedule($user, $target);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData(
            $target,
            DestroyTargetRequest::MODE_DELETE_SELECTED
        );
        unset($deletionData['selected_schedule_ids']);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_schedule_ids');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_target_without_schedules_can_be_deleted(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_DELETE_ALL);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
    }

    public function test_deletion_mode_expected_ids_and_state_are_required(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'deletion_mode',
                'expected_schedule_ids',
                'expected_schedule_state',
            ]);
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_invalid_deletion_mode_is_rejected(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_KEEP_ALL);
        $deletionData['deletion_mode'] = 'invalid_mode';

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response->assertUnprocessable()->assertJsonValidationErrors('deletion_mode');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_invalid_expected_schedule_state_is_rejected(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_KEEP_ALL,
            'expected_schedule_ids' => [],
            'expected_schedule_state' => 'invalid',
        ]);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_schedule_state');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_user_cannot_delete_another_users_target(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($owner);
        $this->actingAs($otherUser);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_KEEP_ALL,
            'expected_schedule_ids' => [],
            'expected_schedule_state' => str_repeat('0', 64),
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_unrelated_schedule_id_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $otherTarget = $this->createTarget($user);
        $relatedSchedule = $this->createSchedule($user, $target);
        $unrelatedSchedule = $this->createSchedule($user, $otherTarget);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData(
            $target,
            DestroyTargetRequest::MODE_DELETE_SELECTED,
            [$unrelatedSchedule->id]
        );

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_schedule_ids');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $relatedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $unrelatedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
    }

    public function test_other_users_schedule_id_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($user);
        $relatedSchedule = $this->createSchedule($user, $target);
        $otherUsersSchedule = $this->createSchedule($otherUser, $target);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData(
            $target,
            DestroyTargetRequest::MODE_DELETE_SELECTED,
            [$otherUsersSchedule->id]
        );

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_schedule_ids');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $relatedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $otherUsersSchedule->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_added_related_schedule_state_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $existingSchedule = $this->createSchedule($user, $target);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_DELETE_ALL);
        $newSchedule = $this->createSchedule($user, $target);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'target_schedule_state_changed');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', ['id' => $existingSchedule->id]);
        $this->assertDatabaseHas('schedules', ['id' => $newSchedule->id]);
    }

    public function test_detached_related_schedule_state_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_KEEP_ALL);
        $schedule->targets()->detach($target->id);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'target_schedule_state_changed');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_changed_target_composition_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_DELETE_ALL);
        $schedule->targets()->attach($otherTarget->id);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'target_schedule_state_changed');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $otherTarget->id,
        ]);
    }

    public function test_target_owner_mismatch_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherUsersTarget = $this->createTarget($otherUser, '他ユーザーのTarget');
        $schedule = $this->createSchedule($user, $target);
        $schedule->targets()->attach($otherUsersTarget->id);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_KEEP_ALL);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);

        // Assert
        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'target_schedule_state_changed');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $otherUsersTarget->id,
        ]);
    }

    public function test_failure_rolls_back_schedule_pivot_and_target_changes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user, '削除対象');
        $otherTarget = $this->createTarget($user, '共有先');
        $singleSchedule = $this->createSchedule($user, $target, ['title' => '単独予定']);
        $sharedSchedule = $this->createSchedule($user, $target, ['title' => '共有予定']);
        $sharedSchedule->targets()->attach($otherTarget->id);
        $this->actingAs($user);
        $deletionData = $this->makeDeletionData($target, DestroyTargetRequest::MODE_DELETE_ALL);
        DB::unprepared(<<<SQL
            CREATE TRIGGER fail_target_delete
            BEFORE DELETE ON targets
            WHEN OLD.id = {$target->id}
            BEGIN
                SELECT RAISE(FAIL, 'forced target delete failure');
            END;
            SQL);

        // Act
        try {
            $response = $this->deleteJson("/api/targets/{$target->id}", $deletionData);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_target_delete');
        }

        // Assert
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $singleSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sharedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $singleSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $sharedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
    }

    public function test_nonexistent_target_returns_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson('/api/targets/999999', [
            'deletion_mode' => DestroyTargetRequest::MODE_KEEP_ALL,
            'expected_schedule_ids' => [],
            'expected_schedule_state' => str_repeat('0', 64),
        ]);

        // Assert
        $response->assertNotFound();
    }

    /**
     * @param  array<int, int>  $selectedScheduleIds
     * @return array<string, mixed>
     */
    private function makeDeletionData(
        Target $target,
        string $deletionMode,
        array $selectedScheduleIds = []
    ): array {
        $preview = $this->getJson("/api/targets/{$target->id}/deletion-preview")
            ->assertOk()
            ->json('data');

        $data = [
            'deletion_mode' => $deletionMode,
            'expected_schedule_ids' => collect($preview['schedules'])->pluck('id')->all(),
            'expected_schedule_state' => $preview['expected_schedule_state'],
        ];

        if ($deletionMode === DestroyTargetRequest::MODE_DELETE_SELECTED) {
            $data['selected_schedule_ids'] = $selectedScheduleIds;
        }

        return $data;
    }

    private function createTarget(User $user, string $name = 'テストターゲット'): Target
    {
        $category = Category::query()->first()
            ?? Category::query()->forceCreate([
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

    private function createSchedule(User $user, Target $target, array $attributes = []): Schedule
    {
        $schedule = Schedule::query()->forceCreate(array_merge([
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
        ], $attributes));

        $schedule->targets()->sync([$target->id]);

        return $schedule;
    }
}
