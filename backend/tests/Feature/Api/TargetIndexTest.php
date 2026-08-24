<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_get_targets(): void
    {
        // Act
        $response = $this->getJson('/api/targets');

        // Assert
        $response->assertUnauthorized();
    }

    public function test_user_can_get_only_their_targets_with_categories(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory('VTuber');

        $target = $this->createTarget($user, $category, [
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => null,
        ]);

        $otherTarget = $this->createTarget($otherUser, $category, [
            'name' => '他ユーザーの推し',
        ]);

        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/targets');

        // Assert
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $target->id,
                        'name' => '不知火フレア',
                        'target_type' => '個人',
                        'memo' => null,
                        'category' => [
                            'id' => $category->id,
                            'name' => 'VTuber',
                        ],
                    ]
                ],
            ])
            ->assertJsonMissing(['id' => $otherTarget->id]);
    }

    public function test_user_can_filter_their_targets_by_keyword(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory('VTuber');

        $matchedTarget = $this->createTarget($user, $category, [
            'name' => '不知火フレア',
        ]);
        $unmatchedTarget = $this->createTarget($user, $category, [
            'name' => '白上フブキ',
        ]);
        $otherTarget = $this->createTarget($otherUser, $category, [
            'name' => 'フレアを含む他ユーザーの推し',
        ]);

        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/targets?keyword=' . urlencode('フレア'));

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchedTarget->id)
            ->assertJsonMissing(['id' => $unmatchedTarget->id])
            ->assertJsonMissing(['id' => $otherTarget->id]);
    }

    public function test_keyword_must_be_a_string_within_255_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $arrayResponse = $this->getJson('/api/targets?keyword[0]=invalid');
        $tooLongResponse = $this->getJson('/api/targets?keyword=' . str_repeat('a', 256));

        // Assert
        $arrayResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('keyword');

        $tooLongResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('keyword');
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->forceCreate([
            'name' => $name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTarget(User $user, Category $category, array $attributes = []): Target
    {
        return Target::query()->forceCreate(array_merge([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'テストTarget',
            'target_type' => '個人',
            'memo' => null,
        ], $attributes));
    }
}
