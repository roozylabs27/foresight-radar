<?php

namespace Tests\Feature\Workflow;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ForesightLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $name, string $category = 'general'): Permission
    {
        return Permission::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => $category,
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_complete_foresight_workflow_lifecycle_from_draft_to_approval_and_closure(): void
    {
        // 0. Seed permissions
        $permissions = [
            'view-driving-force', 'create-driving-force',
            'view-time-horizon', 'create-time-horizon',
            'view-rating-urgency', 'create-rating-urgency',
            'view-status-action', 'create-status-action',
            'view-approval-items', 'create-approval-items',
            'view-closed-items',
        ];

        foreach ($permissions as $p) {
            $this->createPermission($p);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        // Pre-populate reference entities
        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);
        $th = TimeHorizon::create(['name' => 'Mid Term', 'code' => 'MT', 'uuid' => Uuid::uuid1()]);
        $sa = StatusAction::create(['name' => 'Act', 'symbol' => 'A', 'uuid' => Uuid::uuid1()]);
        Priority::create(['id' => 1, 'name' => 'High', 'color' => '#ff0000', 'uuid' => Uuid::uuid1()]);
        Priority::create(['id' => 2, 'name' => 'Medium', 'color' => '#ffff00', 'uuid' => Uuid::uuid1()]);
        Priority::create(['id' => 3, 'name' => 'Low', 'color' => '#00ff00', 'uuid' => Uuid::uuid1()]);

        // Stage 1: Create Driving Force (PENDING status)
        $res1 = $this->actingAs($user)->postJson('/driving-force', [
            'dimension_id' => $dim->id,
            'keyword' => 'Quantum Cryptography',
            'description' => 'Breakthrough post-quantum security algorithms',
            'pic_id' => $user->id,
        ]);
        $res1->assertStatus(201);

        $df = DrivingForce::where('keyword', 'Quantum Cryptography')->firstOrFail();
        $this->assertEquals('PENDING', $df->status);

        // Stage 2: Assess Time Horizon
        $res2 = $this->actingAs($user)->postJson("/time-horizon/{$df->id}", [
            'time_horizon_id' => $th->id,
        ]);
        $res2->assertStatus(200);

        $rating = DrivingForceRating::where('driving_force_id', $df->id)->firstOrFail();
        $this->assertEquals($th->id, $rating->time_horizon_id);

        // Stage 3: Rate Urgency (Impact & Uncertainty)
        $res3a = $this->actingAs($user)->postJson("/rating-urgency/{$rating->uuid}", [
            'type' => 'impact',
            'value' => 8,
        ]);
        $res3a->assertStatus(200);

        $res3b = $this->actingAs($user)->postJson("/rating-urgency/{$rating->uuid}", [
            'type' => 'uncertainty',
            'value' => 7,
        ]);
        $res3b->assertStatus(200);

        $rating->refresh();
        $this->assertEquals(8, $rating->impact_analysis);
        $this->assertEquals(7, $rating->uncertainty_analysis);
        $this->assertEquals(1, $rating->priority_id); // High priority

        // Stage 4: Assign Status Action
        $res4 = $this->actingAs($user)->postJson("/status-action/{$rating->uuid}", [
            'status_action_id' => $sa->id,
            'reason' => 'Critical strategic development for Q1 roadmap',
        ]);
        $res4->assertStatus(200);

        $rating->refresh();
        $this->assertEquals($sa->id, $rating->status_action_id);
        $this->assertDatabaseHas('action_reasons', [
            'driving_force_rating_id' => $rating->id,
            'status_action_id' => $sa->id,
            'reason' => 'Critical strategic development for Q1 roadmap',
        ]);

        // Stage 5: Approval - Approve then Close
        $res5a = $this->actingAs($user)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'APPROVED',
            'text' => 'Approve',
        ]);
        $res5a->assertStatus(200);

        $df->refresh();
        $this->assertEquals('APPROVED', $df->status);
        $this->assertNotNull($df->approved_at);

        $res5b = $this->actingAs($user)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'CLOSED',
            'text' => 'Close',
        ]);
        $res5b->assertStatus(200);

        $df->refresh();
        $this->assertEquals('CLOSED', $df->status);
        $this->assertNotNull($df->closed_at);

        // Stage 6: Verify presence in Closed Items
        $res6 = $this->actingAs($user)->getJson('/closed-items/fetch-data');
        $res6->assertStatus(200);

        $closedItems = $res6->json();
        $this->assertNotEmpty($closedItems['data']);
        $matched = collect($closedItems['data'])->firstWhere('id', $df->uuid);
        $this->assertNotNull($matched);
        $this->assertEquals('Quantum Cryptography', $matched['keyword']);
    }
}
