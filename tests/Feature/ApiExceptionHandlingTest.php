<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_return_standardized_json_response(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $response = $this->postJson('/api/v1/subscriptions', []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => ['plan_id'],
            ])
            ->assertJsonPath('message', 'The plan id field is required.');
    }

    public function test_not_found_returns_standardized_json_response(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $response = $this->getJson('/api/v1/subscriptions/99999');

        $response->assertNotFound()
            ->assertExactJson([
                'message' => 'Resource not found.',
            ]);
    }

    public function test_unauthenticated_returns_standardized_json_response(): void
    {
        $response = $this->getJson('/api/v1/subscriptions');

        $response->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_forbidden_returns_standardized_json_response(): void
    {
        $customer = User::factory()->customer()->create();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/subscriptions/'.$subscription->id);

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden.',
            ]);
    }

    public function test_admin_middleware_returns_standardized_forbidden_response(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $response = $this->getJson('/api/v1/test-admin-route');

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden.',
            ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/api/v1/test-admin-route', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'admin']);
    }
}
