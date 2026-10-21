<?php

namespace Tests\Feature\Reliability;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApprovalValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makePermission(string $name): Permission
    {
        return Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ], [
            'uuid' => Uuid::uuid1(),
            'category' => 'general',
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_approval_rejects_invalid_status_with_422(): void
    {
        $this->makePermission('view-approval-items');
        $this->makePermission('create-approval-items');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-approval-items', 'create-approval-items']);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);

        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'pic' => $user->id,
            'keyword' => 'Test Signal',
            'description' => 'Test signal description',
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'MALICIOUS_STATUS',
            'text' => 'Invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_approval_rejection_stores_remark_and_clears_approved_timestamp(): void
    {
        $this->makePermission('view-approval-items');
        $this->makePermission('create-approval-items');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-approval-items', 'create-approval-items']);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);

        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'pic' => $user->id,
            'keyword' => 'Needs Revision Signal',
            'description' => 'Test signal description',
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'REJECTED',
            'text' => 'Reject',
            'remark' => 'Requires additional evidence from industry publications',
        ]);

        $response->assertStatus(200);

        $df->refresh();
        $this->assertEquals('REJECTED', $df->status);
        $this->assertEquals('Requires additional evidence from industry publications', $df->remark);
        $this->assertNull($df->approved_at);
    }

    public function test_approval_rejects_approved_status_when_status_action_is_missing(): void
    {
        $this->makePermission('view-approval-items');
        $this->makePermission('create-approval-items');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-approval-items', 'create-approval-items']);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);

        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'pic' => $user->id,
            'keyword' => 'Unrated Signal',
            'description' => 'Test signal description',
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'APPROVED',
            'text' => 'Approve',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Cannot approve a driving force without an assigned Status of Action', $response->json('message'));

        $df->refresh();
        $this->assertEquals('PENDING', $df->status);
        $this->assertNull($df->approved_at);
    }

    public function test_approval_allows_approved_status_when_status_action_is_present(): void
    {
        $this->makePermission('view-approval-items');
        $this->makePermission('create-approval-items');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-approval-items', 'create-approval-items']);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);
        $sa = \App\Models\StatusAction::create(['name' => 'Act', 'symbol' => 'A', 'uuid' => Uuid::uuid1()]);
        $th = \App\Models\TimeHorizon::create(['name' => 'Mid Term', 'code' => 'MT', 'uuid' => Uuid::uuid1()]);

        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'pic' => $user->id,
            'keyword' => 'Ready Signal',
            'description' => 'Test signal description',
            'status' => 'PENDING',
        ]);

        \App\Models\DrivingForceRating::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_id' => $df->id,
            'time_horizon_id' => $th->id,
            'status_action_id' => $sa->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
            'priority_id' => 1,
        ]);

        $response = $this->actingAs($user)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'APPROVED',
            'text' => 'Approve',
        ]);

        $response->assertStatus(200);

        $df->refresh();
        $this->assertEquals('APPROVED', $df->status);
        $this->assertNotNull($df->approved_at);
    }

    public function test_bod_role_can_approve_items(): void
    {
        // Seed roles using role.json definitions
        $roleDefinitions = json_decode(file_get_contents(base_path('database/data/role.json')), true);
        $bodDef = collect($roleDefinitions)->firstWhere('name', 'bod');
        $this->assertNotNull($bodDef);
        $this->assertContains('create-approval-items', $bodDef['permissions']);

        foreach ($bodDef['permissions'] as $permName) {
            $this->makePermission($permName);
        }

        $bodRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'bod',
            'guard_name' => 'web',
        ], [
            'uuid' => Uuid::uuid1(),
            'display_name' => 'Board of Directors',
        ]);
        $bodRole->syncPermissions($bodDef['permissions']);

        $bodUser = User::factory()->create();
        $bodUser->assignRole('bod');

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);
        $sa = \App\Models\StatusAction::create(['name' => 'Act', 'symbol' => 'A', 'uuid' => Uuid::uuid1()]);
        $th = \App\Models\TimeHorizon::create(['name' => 'Mid Term', 'code' => 'MT', 'uuid' => Uuid::uuid1()]);

        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $dim->id,
            'created_by' => $bodUser->id,
            'pic' => $bodUser->id,
            'keyword' => 'BOD Approval Candidate',
            'description' => 'Executive review item',
            'status' => 'PENDING',
        ]);

        \App\Models\DrivingForceRating::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_id' => $df->id,
            'time_horizon_id' => $th->id,
            'status_action_id' => $sa->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
            'priority_id' => 1,
        ]);

        $response = $this->actingAs($bodUser)->postJson("/approval-items/{$df->uuid}", [
            'status' => 'APPROVED',
            'text' => 'Approve',
        ]);

        $response->assertStatus(200);

        $df->refresh();
        $this->assertEquals('APPROVED', $df->status);
    }
}
