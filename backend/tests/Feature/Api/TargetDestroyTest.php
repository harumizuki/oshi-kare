<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_delete_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response->assertUnauthorized();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_delete_target(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($user, $category);
        $this->actingAs($user);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response
            ->assertNoContent()
            ->assertContent('');
        $this->assertDatabaseMissing('targets', [
            'id' => $target->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_target(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        $target = $this->createTarget($owner, $category);
        $this->actingAs($otherUser);

        // Act
        $response = $this->deleteJson("/api/targets/{$target->id}");

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('targets', [
            'id' => $target->id,
            'user_id' => $owner->id,
        ]);
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
            'name' => 'テストターゲット',
            'target_type' => '個人',
            'memo' => null,
        ]);
    }
}
