<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.role', UserRole::Customer->value);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'john@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->customer()->create([
            'email' => 'john@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->customer()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', UserRole::Customer->value);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_admin_middleware_blocks_customers(): void
    {
        $customer = User::factory()->customer()->create();

        Sanctum::actingAs($customer);

        Route::middleware(['auth:sanctum', 'admin'])->get('/api/v1/test-admin', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/v1/test-admin');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_admin_middleware_allows_admins(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        Route::middleware(['auth:sanctum', 'admin'])->get('/api/v1/test-admin-ok', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/api/v1/test-admin-ok');

        $response->assertOk()
            ->assertJsonPath('ok', true);
    }
}
