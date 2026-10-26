<?php

namespace Tests\Feature\Audit;

use App\Models\ActionReason;
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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseZeroRemediationTest extends TestCase
{
    use RefreshDatabase;

    private Dimension $dimension;

    private TimeHorizon $timeHorizon;

    private Priority $priority;

    private StatusAction $statusAction;

    private function makePermission(string $name): Permission
    {
        return Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Uuid::uuid4(),
            'category' => 'general',
            'description' => "Permission for {$name}",
        ]);
    }

    private function seedLookupTables(): void
    {
        $env = Environment::create(['name' => 'General', 'uuid' => (string) Uuid::uuid4()]);
        $this->dimension = Dimension::create([
            'name' => 'Technology',
            'symbol' => 'T',
            'environment_id' => $env->id,
            'uuid' => (string) Uuid::uuid4(),
        ]);
        $this->statusAction = StatusAction::create([
            'name' => 'Monitoring',
            'code' => 'M',
            'symbol' => 'circle',
            'uuid' => (string) Uuid::uuid4(),
        ]);
        $this->timeHorizon = TimeHorizon::create([
            'name' => 'Short Term',
            'code' => 'ST',
            'uuid' => (string) Uuid::uuid4(),
        ]);
        $this->priority = Priority::create([
            'name' => 'High',
            'color' => '#ff0000',
            'uuid' => (string) Uuid::uuid4(),
        ]);
    }

    public function test_sec_001_create_driving_force_ignores_unvalidated_mass_assignment_fields(): void
    {
        $this->seedLookupTables();
        $this->makePermission('create-driving-force');

        $creator = User::factory()->create();
        $creator->givePermissionTo('create-driving-force');
        $pic = User::factory()->create();

        $description = 'Strategic shift in quantum computing grid architecture.';

        $response = $this->actingAs($creator)->postJson('/driving-force', [
            'keyword' => 'Quantum Grid Shift',
            'description' => $description,
            'dimension_id' => $this->dimension->id,
            'pic_id' => $pic->id,
            'approved_at' => '2026-10-23 10:00:00',
            'closed_at' => '2026-10-23 11:00:00',
            'remark' => 'Injected remark bypassing approval',
            'updated_by' => $pic->id,
        ]);

        $response->assertStatus(201);

        $force = DrivingForce::firstOrFail();
        $this->assertSame('PENDING', $force->status);
        $this->assertSame($creator->id, $force->created_by);
        $this->assertNull($force->getRawOriginal('approved_at'));
        $this->assertNull($force->getRawOriginal('closed_at'));
        $this->assertNull($force->remark);
        $this->assertNull($force->updated_by);
    }

    public function test_sec_001_update_driving_force_enforces_authenticated_user_as_updated_by(): void
    {
        $this->seedLookupTables();
        $this->makePermission('edit-driving-force');

        $editor = User::factory()->create();
        $editor->givePermissionTo('edit-driving-force');
        $spoofedUser = User::factory()->create();

        $force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'dimension_id' => $this->dimension->id,
            'pic' => $editor->id,
            'created_by' => $editor->id,
            'keyword' => 'Initial Force',
            'description' => 'Initial description for driving force item.',
            'status' => 'PENDING',
        ]);

        $updatedDescription = 'Updated description for driving force item.';

        $response = $this->actingAs($editor)->putJson("/driving-force/{$force->uuid}", [
            'keyword' => 'Updated Force',
            'description' => $updatedDescription,
            'dimension_id' => $this->dimension->id,
            'pic_id' => $editor->id,
            'updated_by' => $spoofedUser->id,
        ]);

        $response->assertStatus(200);

        $force->refresh();
        $this->assertSame($editor->id, $force->updated_by);
    }

    public function test_sec_004_public_registration_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('auth.allow_registration'));

        $this->get('/register')->assertStatus(403);
        $this->post('/register', [
            'name' => 'Anonymous User',
            'email' => 'anon@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(403);
    }

    public function test_rel_002_user_management_handles_roleless_users_without_500_crash(): void
    {
        $viewUserPermission = $this->makePermission('view-user');
        $devRole = Role::firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Developer',
        ]);
        $devRole->givePermissionTo($viewUserPermission);

        $developer = User::factory()->create();
        $developer->assignRole($devRole);

        // A user in the database with zero assigned roles
        $rolelessUser = User::factory()->create(['name' => 'Roleless Account']);

        $response = $this->actingAs($developer)->getJson('/user-management/user/fetch-data');
        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($rolelessUser->name, $names);

        // Also verify UserController::index does not crash when user has direct permission but no role
        $directUser = User::factory()->create();
        $directUser->givePermissionTo($viewUserPermission);
        $this->actingAs($directUser)->get('/user-management/user')->assertStatus(200);
    }

    public function test_rel_002_approval_and_closed_items_handle_soft_deleted_pic_user_without_500_crash(): void
    {
        $this->seedLookupTables();

        $viewApproval = $this->makePermission('view-approval-items');
        $viewClosed = $this->makePermission('view-closed-items');

        $viewer = User::factory()->create();
        $viewer->givePermissionTo([$viewApproval, $viewClosed]);

        $picUser = User::factory()->create(['name' => 'Former PIC Analyst']);

        foreach (['PENDING', 'CLOSED'] as $status) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(),
                'dimension_id' => $this->dimension->id,
                'pic' => $picUser->id,
                'created_by' => $viewer->id,
                'keyword' => "Force {$status}",
                'description' => implode(' ', array_fill(0, 25, 'evidence')),
                'status' => $status,
            ]);

            $rating = DrivingForceRating::create([
                'uuid' => (string) Uuid::uuid4(),
                'driving_force_id' => $force->id,
                'time_horizon_id' => $this->timeHorizon->id,
                'priority_id' => $this->priority->id,
                'status_action_id' => $this->statusAction->id,
                'impact_analysis' => 8,
                'uncertainty_analysis' => 7,
            ]);

            ActionReason::create([
                'uuid' => (string) Uuid::uuid4(),
                'driving_force_rating_id' => $rating->id,
                'status_action_id' => $this->statusAction->id,
                'reason' => 'Initial monitoring reason',
                'date' => now(),
            ]);
        }

        // Soft-delete the PIC user
        $picUser->delete();

        $approvalResponse = $this->actingAs($viewer)->getJson('/approval-items/fetch-data');
        $approvalResponse->assertStatus(200);
        $this->assertSame('Former PIC Analyst', $approvalResponse->json('data.0.pic'));

        $closedResponse = $this->actingAs($viewer)->getJson('/closed-items/fetch-data');
        $closedResponse->assertStatus(200);
        $this->assertSame('Former PIC Analyst', $closedResponse->json('data.0.pic'));
    }
}
