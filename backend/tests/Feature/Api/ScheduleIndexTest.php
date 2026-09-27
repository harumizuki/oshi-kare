<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_get_schedules(): void
    {
        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response->assertUnauthorized();
    }

    public function test_start_and_end_are_required_valid_dates_in_order(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $missingResponse = $this->getJson('/api/schedules');
        $formatResponse = $this->getJson('/api/schedules?start=2026/10/01&end=October-31');
        $reverseResponse = $this->getJson('/api/schedules?start=2026-10-31&end=2026-10-01');

        // Assert
        $missingResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start', 'end']);
        $formatResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start', 'end']);
        $reverseResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end');
    }

    public function test_user_gets_only_their_schedules_with_and_without_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);

        $withTarget = $this->createSchedule($user, [
            'target_id' => $target->id,
            'title' => 'Targetあり予定',
        ]);
        $withoutTarget = $this->createSchedule($user, [
            'title' => 'Targetなし予定',
            'starts_at' => '2026-10-06 00:00:00',
        ]);
        $otherSchedule = $this->createSchedule($otherUser, [
            'title' => '他ユーザー予定',
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $withTarget->id)
            ->assertJsonPath('data.0.target.id', $target->id)
            ->assertJsonPath('data.0.target.name', 'テストターゲット')
            ->assertJsonPath('data.1.id', $withoutTarget->id)
            ->assertJsonPath('data.1.target', null)
            ->assertJsonMissing(['id' => $otherSchedule->id]);
    }

    public function test_timed_schedules_overlapping_period_are_returned_and_outside_are_excluded(): void
    {
        // Arrange
        $user = User::factory()->create();
        $crossesStart = $this->createSchedule($user, [
            'title' => '期間開始をまたぐ予定',
            'starts_at' => '2026-09-30 00:00:00',
            'ends_at' => '2026-10-02 00:00:00',
        ]);
        $singleDay = $this->createSchedule($user, [
            'title' => '期間内単日予定',
            'starts_at' => '2026-10-05 00:00:00',
            'ends_at' => '2026-10-05 00:00:00',
        ]);
        $sameStartFirst = $this->createSchedule($user, [
            'title' => '同一開始1',
            'starts_at' => '2026-10-30 00:00:00',
            'ends_at' => '2026-11-02 00:00:00',
        ]);
        $sameStartSecond = $this->createSchedule($user, [
            'title' => '同一開始2',
            'starts_at' => '2026-10-30 00:00:00',
            'ends_at' => '2026-10-30 01:00:00',
        ]);
        $outsideBefore = $this->createSchedule($user, [
            'title' => '期間前',
            'starts_at' => '2026-09-20 00:00:00',
            'ends_at' => '2026-09-25 00:00:00',
        ]);
        $outsideAfter = $this->createSchedule($user, [
            'title' => '期間後',
            'starts_at' => '2026-11-01 00:00:00',
            'ends_at' => '2026-11-03 00:00:00',
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $crossesStart->id)
            ->assertJsonPath('data.1.id', $singleDay->id)
            ->assertJsonPath('data.2.id', $sameStartFirst->id)
            ->assertJsonPath('data.3.id', $sameStartSecond->id)
            ->assertJsonMissing(['id' => $outsideBefore->id])
            ->assertJsonMissing(['id' => $outsideAfter->id]);
    }

    public function test_schedule_without_end_is_returned_only_when_start_is_in_period(): void
    {
        // Arrange
        $user = User::factory()->create();
        $inside = $this->createSchedule($user, [
            'title' => '終了なし期間内',
            'starts_at' => '2026-10-10 00:00:00',
            'ends_at' => null,
        ]);
        $outside = $this->createSchedule($user, [
            'title' => '終了なし期間外',
            'starts_at' => '2026-09-30 00:00:00',
            'ends_at' => null,
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inside->id)
            ->assertJsonMissing(['id' => $outside->id]);
    }

    public function test_all_day_inclusive_end_boundary_is_handled_as_calendar_date(): void
    {
        // Arrange
        $user = User::factory()->create();
        $endsOnStart = $this->createSchedule($user, [
            'title' => '検索開始日に終了',
            'is_all_day' => true,
            'starts_at' => '2026-09-29 00:00:00',
            'ends_at' => '2026-10-01 00:00:00',
        ]);
        $startsOnEnd = $this->createSchedule($user, [
            'title' => '検索終了日に開始',
            'is_all_day' => true,
            'starts_at' => '2026-10-31 00:00:00',
            'ends_at' => '2026-11-02 00:00:00',
        ]);
        $endsBefore = $this->createSchedule($user, [
            'title' => '検索開始日前に終了',
            'is_all_day' => true,
            'starts_at' => '2026-09-28 00:00:00',
            'ends_at' => '2026-09-30 00:00:00',
        ]);
        $startsAfter = $this->createSchedule($user, [
            'title' => '検索終了日後に開始',
            'is_all_day' => true,
            'starts_at' => '2026-11-01 00:00:00',
            'ends_at' => '2026-11-03 00:00:00',
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $endsOnStart->id)
            ->assertJsonPath('data.1.id', $startsOnEnd->id)
            ->assertJsonMissing(['id' => $endsBefore->id])
            ->assertJsonMissing(['id' => $startsAfter->id]);
    }

    public function test_japanese_calendar_day_boundaries_are_converted_to_utc(): void
    {
        // Arrange
        $user = User::factory()->create();
        $atStart = $this->createSchedule($user, [
            'title' => '日本時間10月1日0時',
            'starts_at' => '2026-09-30 15:00:00',
            'ends_at' => null,
        ]);
        $beforeStart = $this->createSchedule($user, [
            'title' => '日本時間9月30日23時59分59秒',
            'starts_at' => '2026-09-30 14:59:59',
            'ends_at' => null,
        ]);
        $atNextDay = $this->createSchedule($user, [
            'title' => '日本時間10月2日0時',
            'starts_at' => '2026-10-01 15:00:00',
            'ends_at' => null,
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-01');

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $atStart->id)
            ->assertJsonMissing(['id' => $beforeStart->id])
            ->assertJsonMissing(['id' => $atNextDay->id]);
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
            'starts_at' => '2026-10-05 00:00:00',
            'ends_at' => '2026-10-05 01:00:00',
            'location' => null,
            'url' => null,
            'memo' => null,
        ], $attributes));
    }
}
