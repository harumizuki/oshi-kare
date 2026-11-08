<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_schedule(): void
    {
        // Act
        $response = $this->postJson('/api/schedules', $this->timedPayload());

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_user_can_create_timed_schedule_with_their_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        $payload = array_merge($this->timedPayload(), [
            'target_id' => $target->id,
            'ends_at' => '2026-09-25T23:00:00+09:00',
        ]);

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'テスト予定')
            ->assertJsonPath('data.schedule_type', '配信')
            ->assertJsonPath('data.starts_at', '2026-09-25T12:00:00+00:00')
            ->assertJsonPath('data.ends_at', '2026-09-25T14:00:00+00:00')
            ->assertJsonPath('data.is_all_day', false)
            ->assertJsonPath('data.target.id', $target->id)
            ->assertJsonPath('data.target.name', 'テストターゲット')
            ->assertJsonCount(1, 'data.targets')
            ->assertJsonPath('data.targets.0.id', $target->id)
            ->assertJsonPath('data.targets.0.name', 'テストターゲット');

        $this->assertDatabaseHas('schedules', [
            'user_id' => $user->id,
            'target_id' => $target->id,
            'title' => 'テスト予定',
            'starts_at' => '2026-09-25 12:00:00',
            'ends_at' => '2026-09-25 14:00:00',
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $response->json('data.id'),
            'target_id' => $target->id,
        ]);
    }

    public function test_user_can_create_schedule_with_multiple_targets(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $firstTarget = $this->createTarget($user, $category, 'ターゲット1');
        $secondTarget = $this->createTarget($user, $category, 'ターゲット2');
        $this->actingAs($user);
        $payload = $this->timedPayload();
        unset($payload['target_id']);
        $payload['target_ids'] = [$secondTarget->id, $firstTarget->id];

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response
            ->assertCreated()
            ->assertJsonPath('data.target.id', $firstTarget->id)
            ->assertJsonPath('data.target.name', 'ターゲット1')
            ->assertJsonCount(2, 'data.targets')
            ->assertJsonPath('data.targets.0.id', $firstTarget->id)
            ->assertJsonPath('data.targets.0.name', 'ターゲット1')
            ->assertJsonPath('data.targets.1.id', $secondTarget->id)
            ->assertJsonPath('data.targets.1.name', 'ターゲット2');
        $this->assertDatabaseHas('schedules', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'target_id' => $firstTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $response->json('data.id'),
            'target_id' => $firstTarget->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $response->json('data.id'),
            'target_id' => $secondTarget->id,
        ]);
        $this->assertDatabaseCount('schedule_target', 2);
    }

    public function test_user_can_create_schedule_with_single_target_ids_entry(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);
        $payload = $this->timedPayload();
        unset($payload['target_id']);
        $payload['target_ids'] = [$target->id];

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response
            ->assertCreated()
            ->assertJsonPath('data.target.id', $target->id)
            ->assertJsonCount(1, 'data.targets')
            ->assertJsonPath('data.targets.0.id', $target->id);
        $this->assertDatabaseHas('schedules', [
            'id' => $response->json('data.id'),
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('schedule_target', [
            'schedule_id' => $response->json('data.id'),
            'target_id' => $target->id,
        ]);
    }

    public function test_empty_target_ids_creates_personal_schedule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);
        $payload = $this->timedPayload();
        unset($payload['target_id']);
        $payload['target_ids'] = [];

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response
            ->assertCreated()
            ->assertJsonPath('data.target', null)
            ->assertJsonCount(0, 'data.targets');
        $this->assertDatabaseHas('schedules', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'target_id' => null,
        ]);
        $this->assertDatabaseCount('schedule_target', 0);
    }

    public function test_user_can_create_schedule_without_target_and_end_time(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->postJson('/api/schedules', $this->timedPayload());

        // Assert
        $response
            ->assertCreated()
            ->assertJsonPath('data.target', null)
            ->assertJsonCount(0, 'data.targets')
            ->assertJsonPath('data.ends_at', null);

        $this->assertDatabaseHas('schedules', [
            'user_id' => $user->id,
            'target_id' => null,
            'ends_at' => null,
        ]);
        $this->assertDatabaseCount('schedule_target', 0);
    }

    public function test_schedule_creation_rolls_back_when_pivot_sync_fails(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
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
            $this->postJson('/api/schedules', array_merge(
                $this->timedPayload(),
                ['target_id' => $target->id]
            ));
            $this->fail('Pivot同期に失敗したSchedule登録が成功しました。');
        } catch (QueryException) {
            // Assert
            $this->assertDatabaseCount('schedules', 0);
            $this->assertDatabaseCount('schedule_target', 0);
        }
    }

    public function test_multiple_target_creation_rolls_back_when_pivot_sync_fails(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $firstTarget = $this->createTarget($user, $category, 'ターゲット1');
        $secondTarget = $this->createTarget($user, $category, 'ターゲット2');
        $this->actingAs($user);
        $payload = $this->timedPayload();
        unset($payload['target_id']);
        $payload['target_ids'] = [$firstTarget->id, $secondTarget->id];
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
            $this->postJson('/api/schedules', $payload);
            $this->fail('Pivot同期に失敗した複数Target Schedule登録が成功しました。');
        } catch (QueryException) {
            // Assert
            $this->assertDatabaseCount('schedules', 0);
            $this->assertDatabaseCount('schedule_target', 0);
        }
    }

    public function test_client_supplied_user_id_cannot_assign_schedule_to_another_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        $payload = array_merge($this->timedPayload(), [
            'user_id' => $otherUser->id,
        ]);

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('schedules', [
            'user_id' => $user->id,
            'title' => 'テスト予定',
        ]);
        $this->assertDatabaseMissing('schedules', [
            'user_id' => $otherUser->id,
            'title' => 'テスト予定',
        ]);
    }

    public function test_other_users_target_and_nonexistent_target_are_rejected(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $otherTarget = $this->createTarget($otherUser, $category);
        $this->actingAs($user);

        // Act
        $otherTargetResponse = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['target_id' => $otherTarget->id]
        ));
        $missingTargetResponse = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['target_id' => $otherTarget->id + 999]
        ));

        // Assert
        $otherTargetResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
        $missingTargetResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_invalid_target_ids_are_rejected_without_creating_schedule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $ownedTarget = $this->createTarget($user, $category, '所有Target');
        $otherTarget = $this->createTarget($otherUser, $category, '他ユーザーTarget');
        $this->actingAs($user);

        $duplicatePayload = $this->timedPayload();
        unset($duplicatePayload['target_id']);
        $duplicatePayload['target_ids'] = [$ownedTarget->id, $ownedTarget->id];

        $otherUserPayload = $this->timedPayload();
        unset($otherUserPayload['target_id']);
        $otherUserPayload['target_ids'] = [$otherTarget->id];

        $missingTargetPayload = $this->timedPayload();
        unset($missingTargetPayload['target_id']);
        $missingTargetPayload['target_ids'] = [$otherTarget->id + 999];

        // Act
        $duplicateResponse = $this->postJson('/api/schedules', $duplicatePayload);
        $otherUserResponse = $this->postJson('/api/schedules', $otherUserPayload);
        $missingTargetResponse = $this->postJson('/api/schedules', $missingTargetPayload);

        // Assert
        $duplicateResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_ids.1');
        $otherUserResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_ids.0');
        $missingTargetResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_ids.0');
        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseCount('schedule_target', 0);
    }

    public function test_target_id_and_target_ids_cannot_be_sent_together(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        $targetInputs = [
            ['target_id' => null, 'target_ids' => []],
            ['target_id' => null, 'target_ids' => [$target->id]],
            ['target_id' => $target->id, 'target_ids' => []],
            ['target_id' => $target->id, 'target_ids' => [$target->id]],
        ];

        // Act
        $responses = collect($targetInputs)
            ->map(fn (array $targetInput) => $this->postJson(
                '/api/schedules',
                array_merge($this->timedPayload(), $targetInput)
            ));

        // Assert
        $responses->each(fn ($response) => $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_id', 'target_ids']));
        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseCount('schedule_target', 0);
    }

    public function test_title_and_schedule_type_are_required(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);
        $payload = $this->timedPayload();
        unset($payload['title'], $payload['schedule_type']);

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'schedule_type']);
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_title_and_schedule_type_length_boundaries(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $title255Response = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['title' => str_repeat('a', 255)]
        ));
        $title256Response = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['title' => str_repeat('a', 256)]
        ));
        $type100Response = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['schedule_type' => str_repeat('a', 100)]
        ));
        $type101Response = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['schedule_type' => str_repeat('a', 101)]
        ));

        // Assert
        $title255Response->assertCreated();
        $title256Response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
        $type100Response->assertCreated();
        $type101Response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_type');
    }

    public function test_timed_schedule_accepts_end_after_or_equal_to_start(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $sameTimeResponse = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['ends_at' => '2026-09-25T21:00:00+09:00']
        ));
        $laterTimeResponse = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['ends_at' => '2026-09-25T23:00:00+09:00']
        ));

        // Assert
        $sameTimeResponse->assertCreated();
        $laterTimeResponse->assertCreated();
    }

    public function test_end_before_start_is_rejected(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);
        $payload = array_merge($this->timedPayload(), [
            'ends_at' => '2026-09-25T20:00:00+09:00',
        ]);

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_past_timed_schedule_is_allowed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);
        $payload = array_merge($this->timedPayload(), [
            'starts_at' => '2020-01-01T10:00:00+09:00',
            'ends_at' => '2020-01-01T11:00:00+09:00',
        ]);

        // Act
        $response = $this->postJson('/api/schedules', $payload);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('schedules', [
            'user_id' => $user->id,
            'starts_at' => '2020-01-01 01:00:00',
        ]);
    }

    public function test_all_day_schedule_accepts_single_and_multiple_inclusive_dates(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $singleDayResponse = $this->postJson('/api/schedules', $this->allDayPayload());
        $multipleDaysResponse = $this->postJson('/api/schedules', array_merge(
            $this->allDayPayload(),
            [
                'starts_at' => '2026-09-24',
                'ends_at' => '2026-09-26',
            ]
        ));

        // Assert
        $singleDayResponse
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-09-25')
            ->assertJsonPath('data.ends_at', '2026-09-25')
            ->assertJsonPath('data.is_all_day', true);
        $multipleDaysResponse
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-09-24')
            ->assertJsonPath('data.ends_at', '2026-09-26');
    }

    public function test_all_day_schedule_requires_end_date_and_rejects_reverse_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);
        $withoutEnd = $this->allDayPayload();
        unset($withoutEnd['ends_at']);
        $reverseRange = array_merge($this->allDayPayload(), [
            'starts_at' => '2026-09-26',
            'ends_at' => '2026-09-24',
        ]);

        // Act
        $withoutEndResponse = $this->postJson('/api/schedules', $withoutEnd);
        $reverseRangeResponse = $this->postJson('/api/schedules', $reverseRange);

        // Assert
        $withoutEndResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $reverseRangeResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_nullable_optional_fields_and_url_validation(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $nullableResponse = $this->postJson('/api/schedules', $this->timedPayload());
        $url2048Response = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['url' => 'https://example.com/'.str_repeat('a', 2028)]
        ));
        $url2049Response = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['url' => 'https://example.com/'.str_repeat('a', 2029)]
        ));
        $invalidUrlResponse = $this->postJson('/api/schedules', array_merge(
            $this->timedPayload(),
            ['url' => 'invalid-url']
        ));

        // Assert
        $nullableResponse
            ->assertCreated()
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.memo', null);
        $url2048Response->assertCreated();
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

    private function createTarget(
        User $user,
        Category $category,
        string $name = 'テストターゲット'
    ): Target {
        return Target::query()->forceCreate([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => $name,
            'target_type' => '個人',
            'memo' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function timedPayload(): array
    {
        return [
            'target_id' => null,
            'title' => 'テスト予定',
            'schedule_type' => '配信',
            'is_all_day' => false,
            'starts_at' => '2026-09-25T21:00:00+09:00',
            'ends_at' => null,
            'location' => null,
            'url' => null,
            'memo' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function allDayPayload(): array
    {
        return [
            'target_id' => null,
            'title' => 'テスト終日予定',
            'schedule_type' => '記念日',
            'is_all_day' => true,
            'starts_at' => '2026-09-25',
            'ends_at' => '2026-09-25',
            'location' => null,
            'url' => null,
            'memo' => null,
        ];
    }
}
