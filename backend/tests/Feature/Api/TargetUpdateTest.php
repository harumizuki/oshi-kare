<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($user, $category);

        // Act
        $response = $this->patchJson("/api/targets/{$target->id}", [
            'category_id' => $category->id,
            'name' => '更新後の名前',
            'target_type' => 'グループ',
            'memo' => null,
        ]);

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'name' => '不知火フレア',
        ]);
    }

    public function test_user_can_update_their_target_and_receive_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $oldCategory = $this->createCategory('VTuber');
        $newCategory = $this->createCategory('音楽');
        $target = $this->createTarget($user, $oldCategory);
        $this->actingAs($user);

        // Act
        $response = $this->patchJson(
            "/api/targets/{$target->id}",
            $this->validPayload($newCategory)
        );

        // Assert
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $target->id,
                    'name' => '更新後の名前',
                    'target_type' => 'グループ',
                    'memo' => null,
                    'category' => [
                        'id' => $newCategory->id,
                        'name' => '音楽',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $user->id,
            'category_id' => $newCategory->id,
            'name' => '更新後の名前',
            'target_type' => 'グループ',
            'memo' => null,
        ]);
    }

    public function test_user_cannot_update_another_users_target(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($owner, $category);
        $this->actingAs($otherUser);

        // Act
        $response = $this->patchJson(
            "/api/targets/{$target->id}",
            $this->validPayload($category)
        );

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $owner->id,
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => '更新前のメモ',
        ]);
    }

    public function test_client_supplied_user_id_cannot_change_target_owner(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($owner, $category);
        $this->actingAs($owner);

        $payload = array_merge($this->validPayload($category), [
            'user_id' => $otherUser->id,
        ]);

        // Act
        $response = $this->patchJson("/api/targets/{$target->id}", $payload);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $owner->id,
            'name' => '更新後の名前',
        ]);
        $this->assertDatabaseMissing('targets', [
            'id' => $target->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_nonexistent_category_is_rejected_without_changing_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        $payload = array_merge($this->validPayload($category), [
            'category_id' => $category->id + 999,
        ]);

        // Act
        $response = $this->patchJson("/api/targets/{$target->id}", $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
        $this->assertTargetIsUnchanged($target, $user, $category);
    }

    public function test_name_and_target_type_are_required_without_changing_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        $payload = $this->validPayload($category);
        unset($payload['name'], $payload['target_type']);

        // Act
        $response = $this->patchJson("/api/targets/{$target->id}", $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'target_type']);
        $this->assertTargetIsUnchanged($target, $user, $category);
    }

    public function test_name_accepts_255_characters_and_rejects_256_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        // Act
        $acceptedResponse = $this->patchJson(
            "/api/targets/{$target->id}",
            array_merge($this->validPayload($category), ['name' => str_repeat('a', 255)])
        );
        $rejectedResponse = $this->patchJson(
            "/api/targets/{$target->id}",
            array_merge($this->validPayload($category), ['name' => str_repeat('a', 256)])
        );

        // Assert
        $acceptedResponse->assertOk();
        $rejectedResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'name' => str_repeat('a', 255),
        ]);
    }

    public function test_target_type_accepts_100_characters_and_rejects_101_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        // Act
        $acceptedResponse = $this->patchJson(
            "/api/targets/{$target->id}",
            array_merge($this->validPayload($category), ['target_type' => str_repeat('a', 100)])
        );
        $rejectedResponse = $this->patchJson(
            "/api/targets/{$target->id}",
            array_merge($this->validPayload($category), ['target_type' => str_repeat('a', 101)])
        );

        // Assert
        $acceptedResponse->assertOk();
        $rejectedResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_type');
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'target_type' => str_repeat('a', 100),
        ]);
    }

    public function test_target_can_be_updated_to_same_name_as_another_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory('VTuber');
        $target = $this->createTarget($user, $category);
        $this->createTarget($user, $category, ['name' => '更新後の名前']);
        $this->actingAs($user);

        // Act
        $response = $this->patchJson(
            "/api/targets/{$target->id}",
            $this->validPayload($category)
        );

        // Assert
        $response->assertOk();
        $this->assertDatabaseCount('targets', 2);
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'name' => '更新後の名前',
        ]);
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
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => '更新前のメモ',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'name' => '更新後の名前',
            'target_type' => 'グループ',
            'memo' => null,
        ];
    }

    private function assertTargetIsUnchanged(Target $target, User $user, Category $category): void
    {
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => '更新前のメモ',
        ]);
    }
}
