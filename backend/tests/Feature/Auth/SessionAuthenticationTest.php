<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_spa_can_initialize_csrf_protection(): void
    {
        // Act
        $response = $this
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->get('/sanctum/csrf-cookie');

        // Assert
        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Act
        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJson([
                'message' => 'ログインしました。',
            ])
            ->assertJsonMissingPath('password');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_incorrect_credentials(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Act
        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ]);

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonMissing([
                'password' => 'incorrect-password',
            ]);
        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        // Act
        $response = $this->postJson('/logout');

        // Assert
        $response->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_unauthenticated_user_cannot_access_protected_schedule_api(): void
    {
        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_protected_schedule_api(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->getJson('/api/schedules?start=2026-10-01&end=2026-10-31');

        // Assert
        $response
            ->assertOk()
            ->assertJson([
                'data' => [],
            ]);
    }
}
