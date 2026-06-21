<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_is_forbidden_on_all_admin_routes(): void
    {
        $customer = User::factory()->customer()->create();
        $plan = Plan::factory()->create();
        $payment = Payment::factory()->create();

        Sanctum::actingAs($customer);

        $this->assertForbiddenJson($this->getJson('/api/v1/admin/plans'));
        $this->assertForbiddenJson($this->postJson('/api/v1/admin/plans', [
            'name' => 'Blocked',
            'slug' => 'blocked',
            'price' => 10,
            'interval' => 'monthly',
        ]));
        $this->assertForbiddenJson($this->getJson('/api/v1/admin/plans/'.$plan->id));
        $this->assertForbiddenJson($this->putJson('/api/v1/admin/plans/'.$plan->id, [
            'name' => 'Blocked Update',
        ]));
        $this->assertForbiddenJson($this->patchJson('/api/v1/admin/plans/'.$plan->id.'/activate'));
        $this->assertForbiddenJson($this->deleteJson('/api/v1/admin/plans/'.$plan->id));

        $this->assertForbiddenJson($this->getJson('/api/v1/admin/payments'));
        $this->assertForbiddenJson($this->getJson('/api/v1/admin/payments/'.$payment->id));
        $this->assertForbiddenJson($this->postJson('/api/v1/admin/payments/'.$payment->id.'/confirm'));
        $this->assertForbiddenJson($this->postJson('/api/v1/admin/payments/'.$payment->id.'/fail'));
    }

    protected function assertForbiddenJson(TestResponse $response): TestResponse
    {
        return $response
            ->assertForbidden()
            ->assertExactJson(['message' => 'Forbidden.']);
    }
}
