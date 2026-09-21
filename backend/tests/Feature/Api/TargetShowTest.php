<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_get_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}");

        // Assert
        $response->assertUnauthorized();
    }

    public function test_user_can_get_their_target_with_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}");

        // Assert
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $target->id,
                    'name' => '不知火フレア',
                    'target_type' => '個人',
                    'memo' => null,
                    'category' => [
                        'id' => $category->id,
                        'name' => 'VTuber',
                    ],
                ],
            ]);
    }

    public function test_user_cannot_get_another_users_target(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($owner, $category);
        $this->actingAs($otherUser);

        // Act
        $response = $this->getJson("/api/targets/{$target->id}");

        // Assert
        $response
            ->assertForbidden()
            ->assertJsonMissing(['name' => '不知火フレア']);
    }

    private function createCategory(): Category
    {
        return Category::query()->forceCreate([
            'name' => 'VTuber',
        ]);
    }

    private function createTarget(User $user, Category $category): Target
    {
        return Target::query()->forceCreate([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => '不知火フレア',
            'target_type' => '個人',
            'memo' => null,
        ]);
    }
}
