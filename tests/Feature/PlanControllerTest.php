<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_active_plans_only(): void
    {
        Plan::factory()->create(['name' => 'Active Plan', 'is_active' => true]);
        Plan::factory()->inactive()->create(['name' => 'Inactive Plan']);

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Plan');
    }

    public function test_customer_can_view_active_plan(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/plans/'.$plan->id)
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_customer_cannot_view_inactive_plan(): void
    {
        $plan = Plan::factory()->inactive()->create();

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/plans/'.$plan->id)->assertForbidden();
    }

    public function test_admin_can_manage_plans(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $createResponse = $this->postJson('/api/v1/admin/plans', [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 199.90,
            'interval' => 'monthly',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.slug', 'enterprise');

        $planId = $createResponse->json('data.id');

        $this->getJson('/api/v1/admin/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson('/api/v1/admin/plans/'.$planId, [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->patchJson('/api/v1/admin/plans/'.$planId.'/activate')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->deleteJson('/api/v1/admin/plans/'.$planId)
            ->assertNoContent();
    }

    public function test_customer_cannot_access_admin_plan_routes(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/admin/plans')->assertForbidden();
        $this->postJson('/api/v1/admin/plans', [
            'name' => 'Hack',
            'slug' => 'hack',
            'price' => 1,
            'interval' => 'monthly',
        ])->assertForbidden();
    }

    public function test_plan_store_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/admin/plans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug', 'price', 'interval']);
    }

    public function test_admin_cannot_delete_plan_with_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();
        \App\Models\Subscription::factory()->for($plan)->create();

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/admin/plans/'.$plan->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan'])
            ->assertJsonPath('errors.plan.0', 'Cannot delete a plan that has subscriptions.');

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_plans_require_authentication(): void
    {
        $this->getJson('/api/v1/plans')->assertUnauthorized();
    }
}
