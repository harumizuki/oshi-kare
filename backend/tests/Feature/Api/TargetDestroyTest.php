<?php

namespace Tests\Feature\Api;

use App\Http\Requests\DestroyTargetRequest;
use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_delete_all_mode_deletes_target_and_all_owned_related_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = $this->createTarget($user);
        $otherTarget = $this->createTarget($user);
        $firstSchedule = $this->createSchedule($user, $target, ['title' => '削除予定1']);
        $secondSchedule = $this->createSchedule($user, $target, ['title' => '削除予定2']);
        $unrelatedSchedule = $this->createSchedule($user, $otherTarget, ['title' => '無関係な予定']);
        $otherUsersSchedule = $this->createSchedule($otherUser, $target, ['title' => '他ユーザーの予定']);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_ALL,
            'expected_schedule_ids' => [$secondSchedule->id, $firstSchedule->id],
        ]);

        // Assert
        $response->assertNoContent()->assertContent('');
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $firstSchedule->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $secondSchedule->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $unrelatedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $otherUsersSchedule->id,
            'user_id' => $otherUser->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $unrelatedSchedule->id,
            'target_id' => $otherTarget->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $target->id,
        ]);
    }

    public function test_keep_all_mode_deletes_only_target_and_keeps_all_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $firstSchedule = $this->createSchedule($user, $target);
        $secondSchedule = $this->createSchedule($user, $target);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_KEEP_ALL,
            'expected_schedule_ids' => [$firstSchedule->id, $secondSchedule->id],
        ]);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $firstSchedule->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $secondSchedule->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $target->id,
        ]);
    }

    public function test_delete_selected_mode_deletes_only_selected_schedules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $selectedSchedule = $this->createSchedule($user, $target);
        $keptSchedule = $this->createSchedule($user, $target);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_SELECTED,
            'expected_schedule_ids' => [$selectedSchedule->id, $keptSchedule->id],
            'selected_schedule_ids' => [$selectedSchedule->id],
        ]);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $selectedSchedule->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $keptSchedule->id,
            'target_id' => null,
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

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_SELECTED,
            'expected_schedule_ids' => [$schedule->id],
            'selected_schedule_ids' => [],
        ]);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'target_id' => $target->id,
        ]);
    }

    public function test_delete_selected_mode_requires_selected_schedule_ids(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_SELECTED,
            'expected_schedule_ids' => [$schedule->id],
        ]);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_schedule_ids');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_target_without_schedules_can_be_deleted(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_ALL,
            'expected_schedule_ids' => [],
        ]);

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
    }

    public function test_deletion_mode_and_expected_schedule_ids_are_required(): void
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
            ->assertJsonValidationErrors(['deletion_mode', 'expected_schedule_ids']);
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_invalid_deletion_mode_is_rejected(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => 'invalid_mode',
            'expected_schedule_ids' => [],
        ]);

        // Assert
        $response->assertUnprocessable()->assertJsonValidationErrors('deletion_mode');
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

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_SELECTED,
            'expected_schedule_ids' => [$relatedSchedule->id],
            'selected_schedule_ids' => [$unrelatedSchedule->id],
        ]);

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

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_SELECTED,
            'expected_schedule_ids' => [$relatedSchedule->id],
            'selected_schedule_ids' => [$otherUsersSchedule->id],
        ]);

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

    public function test_changed_related_schedule_state_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $existingSchedule = $this->createSchedule($user, $target);
        $this->actingAs($user);
        $preview = $this->getJson("/api/targets/{$target->id}/deletion-preview")
            ->assertOk();
        $expectedScheduleIds = collect($preview->json('data.schedules'))->pluck('id')->all();
        $newSchedule = $this->createSchedule($user, $target);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_DELETE_ALL,
            'expected_schedule_ids' => $expectedScheduleIds,
        ]);

        // Assert
        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'target_schedule_state_changed');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $existingSchedule->id,
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $newSchedule->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_detached_related_schedule_state_rejects_entire_deletion(): void
    {
        // Arrange
        $user = User::factory()->create();
        $target = $this->createTarget($user);
        $schedule = $this->createSchedule($user, $target);
        $this->actingAs($user);
        $preview = $this->getJson("/api/targets/{$target->id}/deletion-preview")
            ->assertOk();
        $expectedScheduleIds = collect($preview->json('data.schedules'))->pluck('id')->all();
        $schedule->update(['target_id' => null]);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}", [
            'deletion_mode' => DestroyTargetRequest::MODE_KEEP_ALL,
            'expected_schedule_ids' => $expectedScheduleIds,
        ]);

        // Assert
        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'target_schedule_state_changed');
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => null,
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
        ]);

        // Assert
        $response->assertNotFound();
    }

    private function createTarget(User $user): Target
    {
        $category = Category::query()->first()
            ?? Category::query()->forceCreate([
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
