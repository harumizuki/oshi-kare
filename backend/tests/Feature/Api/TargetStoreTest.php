<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_target(): void
    {
        // Arrange
        $category = $this->createCategory();

        // Act
        $response = $this->postJson('/api/targets', $this->validPayload($category));

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseCount('targets', 0);
    }

    public function test_authenticated_user_can_create_target_with_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);

        // Act
        $response = $this->postJson('/api/targets', $this->validPayload($category));

        // Assert
        $response
            ->assertCreated()
            ->assertJsonPath('data.name', '不知火フレア')
            ->assertJsonPath('data.target_type', '個人')
            ->assertJsonPath('data.memo', null)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'VTuber');

        $this->assertDatabaseHas('targets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => null,
        ]);
    }

    public function test_client_supplied_user_id_cannot_assign_target_to_another_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);

        $payload = array_merge($this->validPayload($category), [
            'user_id' => $otherUser->id,
        ]);

        // Act
        $response = $this->postJson('/api/targets', $payload);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('targets', [
            'user_id' => $user->id,
            'name' => '不知火フレア',
        ]);
        $this->assertDatabaseMissing('targets', [
            'user_id' => $otherUser->id,
            'name' => '不知火フレア',
        ]);
    }

    public function test_nonexistent_category_is_rejected(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);

        $payload = array_merge($this->validPayload($category), [
            'category_id' => $category->id + 999,
        ]);

        // Act
        $response = $this->postJson('/api/targets', $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
        $this->assertDatabaseCount('targets', 0);
    }

    public function test_name_and_target_type_are_required(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);

        $payload = $this->validPayload($category);
        unset($payload['name'], $payload['target_type']);

        // Act
        $response = $this->postJson('/api/targets', $payload);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'target_type']);
        $this->assertDatabaseCount('targets', 0);
    }

    public function test_name_accepts_255_characters_and_rejects_256_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);

        // Act
        $acceptedResponse = $this->postJson('/api/targets', array_merge(
            $this->validPayload($category),
            ['name' => str_repeat('a', 255)]
        ));
        $rejectedResponse = $this->postJson('/api/targets', array_merge(
            $this->validPayload($category),
            ['name' => str_repeat('a', 256)]
        ));

        // Assert
        $acceptedResponse->assertCreated();
        $rejectedResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_target_type_accepts_100_characters_and_rejects_101_characters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);

        // Act
        $acceptedResponse = $this->postJson('/api/targets', array_merge(
            $this->validPayload($category),
            ['target_type' => str_repeat('a', 100)]
        ));
        $rejectedResponse = $this->postJson('/api/targets', array_merge(
            $this->validPayload($category),
            ['target_type' => str_repeat('a', 101)]
        ));

        // Assert
        $acceptedResponse->assertCreated();
        $rejectedResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_type');
    }

    public function test_same_name_can_be_registered_more_than_once(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $this->actingAs($user);
        $payload = $this->validPayload($category);

        // Act
        $firstResponse = $this->postJson('/api/targets', $payload);
        $secondResponse = $this->postJson('/api/targets', $payload);

        // Assert
        $firstResponse->assertCreated();
        $secondResponse->assertCreated();
        $this->assertDatabaseCount('targets', 2);
    }

    private function createCategory(): Category
    {
        return Category::query()->forceCreate([
            'name' => 'VTuber',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => null,
        ];
    }
}
