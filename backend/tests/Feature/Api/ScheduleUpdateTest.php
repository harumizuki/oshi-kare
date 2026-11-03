<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_schedule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);

        // Act
        $response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            $this->timedPayload()
        );

        // Assert
        $response->assertUnauthorized();
        $this->assertScheduleIsUnchanged($schedule, $user);
    }

    public function test_owner_can_update_timed_schedule_with_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);
        $payload = array_merge($this->timedPayload(), [
            'target_id' => $target->id,
            'ends_at' => '2026-10-10T23:00:00+09:00',
        ]);

        // Act
        $response = $this->patchJson("/api/schedules/{$schedule->id}", $payload);

        // Assert
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $schedule->id,
                    'title' => '更新後のテスト予定',
                    'schedule_type' => 'イベント',
                    'starts_at' => '2026-10-10T12:00:00+00:00',
                    'ends_at' => '2026-10-10T14:00:00+00:00',
                    'is_all_day' => false,
                    'location' => '更新後の会場',
                    'url' => 'https://example.com/updated',
                    'memo' => '更新後のメモ',
                    'target' => [
                        'id' => $target->id,
                        'name' => 'テストターゲット',
                    ],
                ],
            ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $user->id,
            'target_id' => $target->id,
            'title' => '更新後のテスト予定',
            'starts_at' => '2026-10-10 12:00:00',
            'ends_at' => '2026-10-10 14:00:00',
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_owner_can_update_to_all_day_schedule_without_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, ['target_id' => $target->id]);
        $schedule->targets()->sync([$target->id]);
        $this->actingAs($user);

        // Act
        $response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            $this->allDayPayload()
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2026-10-10')
            ->assertJsonPath('data.ends_at', '2026-10-12')
            ->assertJsonPath('data.is_all_day', true)
            ->assertJsonPath('data.target', null);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => null,
            'is_all_day' => true,
            'starts_at' => '2026-10-10 00:00:00',
            'ends_at' => '2026-10-12 00:00:00',
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $schedule->id,
        ]);
    }

    public function test_owner_can_change_target_and_keep_columns_in_sync(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $oldTarget = $this->createTarget($user, $category);
        $newTarget = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, ['target_id' => $oldTarget->id]);
        $schedule->targets()->sync([$oldTarget->id]);
        $this->actingAs($user);

        // Act
        $response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['target_id' => $newTarget->id])
        );

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'target_id' => $newTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $newTarget->id,
        ]);
        $this->assertDatabaseMissing('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $oldTarget->id,
        ]);
    }

    public function test_schedule_update_rolls_back_when_pivot_sync_fails(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $oldTarget = $this->createTarget($user, $category);
        $newTarget = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, ['target_id' => $oldTarget->id]);
        $schedule->targets()->sync([$oldTarget->id]);
        $this->actingAs($user);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_schedule_target_insert
            BEFORE INSERT ON schedule_target
            BEGIN
                SELECT RAISE(ABORT, 'pivot sync failed');
            END
            SQL);
        $this->withoutExceptionHandling();

        // Act
        try {
            $this->patchJson(
                "/api/schedules/{$schedule->id}",
                array_merge($this->timedPayload(), ['target_id' => $newTarget->id])
            );
            $this->fail('Pivot同期に失敗したSchedule更新が成功しました。');
        } catch (QueryException) {
            // Assert
            $this->assertDatabaseHas('schedules', [
                'id' => $schedule->id,
                'target_id' => $oldTarget->id,
                'title' => '更新前のテスト予定',
            ]);
            $this->assertDatabaseHas('schedule_target', [
                'schedule_id' => $schedule->id,
                'target_id' => $oldTarget->id,
            ]);
            $this->assertDatabaseMissing('schedule_target', [
                'schedule_id' => $schedule->id,
                'target_id' => $newTarget->id,
            ]);
        }
    }

    public function test_legacy_update_rejects_shared_schedule_without_changing_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $firstTarget = $this->createTarget($user, $category);
        $secondTarget = $this->createTarget($user, $category);
        $schedule = $this->createSchedule($user, ['target_id' => $firstTarget->id]);
        $schedule->targets()->sync([$firstTarget->id, $secondTarget->id]);
        $this->actingAs($user);

        // Act
        $response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['target_id' => $firstTarget->id])
        );

        // Assert
        $response
            ->assertConflict()
            ->assertExactJson([
                'message' => '複数の登録情報に紐づく予定は、現在の編集画面から更新できません。',
                'code' => 'shared_schedule_requires_target_ids',
            ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $user->id,
            'target_id' => $firstTarget->id,
            'title' => '更新前のテスト予定',
            'starts_at' => '2026-10-05 12:00:00',
            'ends_at' => '2026-10-05 14:00:00',
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $firstTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $secondTarget->id,
        ]);
        $this->assertDatabaseCount('schedule_target', 2);
    }

    public function test_user_cannot_update_another_users_schedule(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $firstTarget = $this->createTarget($owner, $category);
        $secondTarget = $this->createTarget($owner, $category);
        $schedule = $this->createSchedule($owner, ['target_id' => $firstTarget->id]);
        $schedule->targets()->sync([$firstTarget->id, $secondTarget->id]);
        $this->actingAs($otherUser);

        // Act
        $response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            $this->timedPayload()
        );

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $owner->id,
            'target_id' => $firstTarget->id,
            'title' => '更新前のテスト予定',
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $firstTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $schedule->id,
            'target_id' => $secondTarget->id,
        ]);
        $this->assertDatabaseCount('schedule_target', 2);
    }

    public function test_nonexistent_schedule_returns_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->patchJson('/api/schedules/999999', $this->timedPayload());

        // Assert
        $response->assertNotFound();
    }

    public function test_client_supplied_user_id_cannot_change_schedule_owner(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $schedule = $this->createSchedule($owner);
        $this->actingAs($owner);
        $payload = array_merge($this->timedPayload(), [
            'user_id' => $otherUser->id,
        ]);

        // Act
        $response = $this->patchJson("/api/schedules/{$schedule->id}", $payload);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $owner->id,
            'title' => '更新後のテスト予定',
        ]);
        $this->assertDatabaseMissing('schedules', [
            'id' => $schedule->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_other_users_target_and_nonexistent_target_are_rejected_without_update(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $otherTarget = $this->createTarget($otherUser, $category);
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);

        // Act
        $otherTargetResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['target_id' => $otherTarget->id])
        );
        $missingTargetResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['target_id' => $otherTarget->id + 999])
        );

        // Assert
        $otherTargetResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
        $missingTargetResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
        $this->assertScheduleIsUnchanged($schedule, $user);
    }

    public function test_title_and_schedule_type_are_required_without_update(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);
        $payload = $this->timedPayload();
        unset($payload['title'], $payload['schedule_type']);

        // Act
        $response = $this->patchJson("/api/schedules/{$schedule->id}", $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'schedule_type']);
        $this->assertScheduleIsUnchanged($schedule, $user);
    }

    public function test_title_and_schedule_type_length_boundaries(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);

        // Act
        $title255Response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['title' => str_repeat('a', 255)])
        );
        $title256Response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['title' => str_repeat('a', 256)])
        );
        $type100Response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['schedule_type' => str_repeat('a', 100)])
        );
        $type101Response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['schedule_type' => str_repeat('a', 101)])
        );

        // Assert
        $title255Response->assertOk();
        $title256Response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
        $type100Response->assertOk();
        $type101Response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_type');
    }

    public function test_timed_schedule_end_boundaries(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);

        // Act
        $withoutEndResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            $this->timedPayload()
        );
        $sameTimeResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['ends_at' => '2026-10-10T21:00:00+09:00'])
        );
        $beforeTimeResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['ends_at' => '2026-10-10T20:00:00+09:00'])
        );

        // Assert
        $withoutEndResponse
            ->assertOk()
            ->assertJsonPath('data.ends_at', null);
        $sameTimeResponse->assertOk();
        $beforeTimeResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-10-10 12:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);
    }

    public function test_all_day_schedule_end_boundaries(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);
        $withoutEnd = $this->allDayPayload();
        unset($withoutEnd['ends_at']);

        // Act
        $withoutEndResponse = $this->patchJson("/api/schedules/{$schedule->id}", $withoutEnd);
        $sameDayResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->allDayPayload(), ['ends_at' => '2026-10-10'])
        );
        $multipleDaysResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            $this->allDayPayload()
        );
        $reverseResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->allDayPayload(), ['ends_at' => '2026-10-09'])
        );

        // Assert
        $withoutEndResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $sameDayResponse->assertOk();
        $multipleDaysResponse->assertOk();
        $reverseResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-10-10 00:00:00',
            'ends_at' => '2026-10-12 00:00:00',
        ]);
    }

    public function test_past_schedule_and_nullable_optional_fields_are_allowed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);
        $payload = array_merge($this->timedPayload(), [
            'starts_at' => '2020-01-01T10:00:00+09:00',
            'ends_at' => null,
            'location' => null,
            'url' => null,
            'memo' => null,
        ]);

        // Act
        $response = $this->patchJson("/api/schedules/{$schedule->id}", $payload);

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2020-01-01T01:00:00+00:00')
            ->assertJsonPath('data.ends_at', null)
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.memo', null);
    }

    public function test_url_format_and_length_validation(): void
    {
        // Arrange
        $user = User::factory()->create();
        $schedule = $this->createSchedule($user);
        $this->actingAs($user);

        // Act
        $url2048Response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), [
                'url' => 'https://example.com/'.str_repeat('a', 2028),
            ])
        );
        $url2049Response = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), [
                'url' => 'https://example.com/'.str_repeat('a', 2029),
            ])
        );
        $invalidUrlResponse = $this->patchJson(
            "/api/schedules/{$schedule->id}",
            array_merge($this->timedPayload(), ['url' => 'invalid-url'])
        );

        // Assert
        $url2048Response->assertOk();
        $url2049Response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
        $invalidUrlResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
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
            'title' => '更新前のテスト予定',
            'schedule_type' => '配信',
            'is_all_day' => false,
            'starts_at' => '2026-10-05 12:00:00',
            'ends_at' => '2026-10-05 14:00:00',
            'location' => '更新前の会場',
            'url' => 'https://example.com/original',
            'memo' => '更新前のメモ',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function timedPayload(): array
    {
        return [
            'target_id' => null,
            'title' => '更新後のテスト予定',
            'schedule_type' => 'イベント',
            'is_all_day' => false,
            'starts_at' => '2026-10-10T21:00:00+09:00',
            'ends_at' => null,
            'location' => '更新後の会場',
            'url' => 'https://example.com/updated',
            'memo' => '更新後のメモ',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function allDayPayload(): array
    {
        return [
            'target_id' => null,
            'title' => '更新後の終日予定',
            'schedule_type' => '記念日',
            'is_all_day' => true,
            'starts_at' => '2026-10-10',
            'ends_at' => '2026-10-12',
            'location' => null,
            'url' => null,
            'memo' => null,
        ];
    }

    private function assertScheduleIsUnchanged(Schedule $schedule, User $user): void
    {
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'user_id' => $user->id,
            'target_id' => null,
            'title' => '更新前のテスト予定',
            'schedule_type' => '配信',
            'is_all_day' => false,
            'starts_at' => '2026-10-05 12:00:00',
            'ends_at' => '2026-10-05 14:00:00',
            'location' => '更新前の会場',
            'url' => 'https://example.com/original',
            'memo' => '更新前のメモ',
        ]);
    }
}
