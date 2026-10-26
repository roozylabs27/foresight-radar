<?php

namespace Tests\Feature;

use App\Enums\LlmJobStatus;
use App\Jobs\GenerateScenarioNarrative;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Scenario;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScenarioHarnessTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $otherManager;

    private User $admin;

    private DrivingForce $forceA;

    private DrivingForce $forceB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]
            );
        }

        Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin']
        );

        $this->creator = User::factory()->create();
        $this->creator->givePermissionTo(['view-scenario', 'manage-scenario']);

        $this->otherManager = User::factory()->create();
        $this->otherManager->givePermissionTo(['view-scenario', 'manage-scenario']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(['view-scenario', 'manage-scenario']);

        $env = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $horizon = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);

        $dimA = Dimension::firstOrCreate(['name' => 'Tech'], ['uuid' => (string) Uuid::uuid4(), 'environment_id' => $env->id]);
        $dimB = Dimension::firstOrCreate(['name' => 'Gov'], ['uuid' => (string) Uuid::uuid4(), 'environment_id' => $env->id]);

        $this->forceA = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'AI Adoption',
            'description' => 'Test force A',
            'status' => 'APPROVED',
            'dimension_id' => $dimA->id,
            'created_by' => $this->creator->id,
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $this->forceA->id,
            'time_horizon_id' => $horizon->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
        ]);

        $this->forceB = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'Data Privacy Law',
            'description' => 'Test force B',
            'status' => 'APPROVED',
            'dimension_id' => $dimB->id,
            'created_by' => $this->creator->id,
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $this->forceB->id,
            'time_horizon_id' => $horizon->id,
            'impact_analysis' => 9,
            'uncertainty_analysis' => 8,
        ]);
    }

    private function createScenario(User $owner): Scenario
    {
        return Scenario::create([
            'name' => 'Future of AI Governance',
            'focal_question' => 'What will the regulatory and tech landscape look like?',
            'axis_a_force_id' => $this->forceA->id,
            'axis_b_force_id' => $this->forceB->id,
            'axis_a_low_label' => 'Slow',
            'axis_a_high_label' => 'Rapid',
            'axis_b_low_label' => 'Weak',
            'axis_b_high_label' => 'Strict',
            'created_by' => $owner->id,
        ]);
    }

    public function test_scenario_remains_usable_and_shows_deleted_state_when_axis_is_soft_deleted(): void
    {
        $scenario = $this->createScenario($this->creator);

        // Soft delete force A
        $this->forceA->delete();
        $this->assertSoftDeleted('driving_forces', ['id' => $this->forceA->id]);

        // Scenario should retain historical axis relation via withTrashed
        $reloaded = Scenario::with(['axis_a', 'axis_b'])->find($scenario->id);
        $this->assertNotNull($reloaded->axis_a, 'Scenario should retain historical axis_a reference even when soft-deleted');
        $this->assertEquals('AI Adoption', $reloaded->axis_a->keyword);

        // fetch endpoint should return keyword with deleted indication
        $fetchRes = $this->actingAs($this->creator)->getJson('/scenarios/fetch');
        $fetchRes->assertStatus(200);
        $fetchData = collect($fetchRes->json())->firstWhere('id', $scenario->uuid);
        $this->assertNotNull($fetchData);
        $this->assertStringContainsString('AI Adoption', $fetchData['axis_a']);
        $this->assertStringContainsString('Dihapus', $fetchData['axis_a']);

        // show endpoint should return axis details with is_deleted flag true
        $showRes = $this->actingAs($this->creator)->getJson("/scenarios/{$scenario->uuid}");
        $showRes->assertStatus(200);
        $showRes->assertJsonPath('axis_a.keyword', 'AI Adoption');
        $showRes->assertJsonPath('axis_a.is_deleted', true);
        $showRes->assertJsonPath('axis_b.is_deleted', false);
    }

    public function test_non_owner_without_admin_role_cannot_delete_scenario(): void
    {
        $scenario = $this->createScenario($this->creator);

        // otherManager has manage-scenario permission, but is not creator and not admin
        $response = $this->actingAs($this->otherManager)->deleteJson("/scenarios/{$scenario->uuid}");
        $response->assertStatus(403);

        $this->assertDatabaseHas('scenarios', ['id' => $scenario->id]);
    }

    public function test_owner_can_delete_own_scenario(): void
    {
        $scenario = $this->createScenario($this->creator);

        $response = $this->actingAs($this->creator)->deleteJson("/scenarios/{$scenario->uuid}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('scenarios', ['id' => $scenario->id]);
    }

    public function test_admin_can_delete_any_scenario(): void
    {
        $scenario = $this->createScenario($this->creator);

        $response = $this->actingAs($this->admin)->deleteJson("/scenarios/{$scenario->uuid}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('scenarios', ['id' => $scenario->id]);
    }

    public function test_non_owner_without_admin_role_cannot_update_scenario(): void
    {
        $scenario = $this->createScenario($this->creator);

        $payload = [
            'name' => 'Updated by Intruder',
            'axis_a' => $this->forceA->uuid,
            'axis_b' => $this->forceB->uuid,
            'axis_a_low_label' => 'Low',
            'axis_a_high_label' => 'High',
            'axis_b_low_label' => 'Low',
            'axis_b_high_label' => 'High',
        ];

        $response = $this->actingAs($this->otherManager)->putJson("/scenarios/{$scenario->uuid}", $payload);
        $response->assertStatus(403);
    }

    public function test_non_owner_without_admin_role_cannot_generate_scenario_narrative(): void
    {
        $scenario = $this->createScenario($this->creator);

        $response = $this->actingAs($this->otherManager)->postJson("/scenarios/{$scenario->uuid}/generate");
        $response->assertStatus(403);
    }

    public function test_generate_scenario_narrative_job_implements_unique_contract(): void
    {
        $scenario = $this->createScenario($this->creator);
        $job = new GenerateScenarioNarrative($scenario, false, $this->creator->id);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $scenario->id, $job->uniqueId());
    }

    public function test_concurrent_narrative_generation_is_rejected_when_job_is_pending(): void
    {
        $scenario = $this->createScenario($this->creator);
        $scenario->update([
            'generation_status' => LlmJobStatus::QUEUED,
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->creator)->postJson("/scenarios/{$scenario->uuid}/generate");
        $response->assertStatus(409);
        $response->assertSee('Narasi skenario ini sedang dibuat');
    }
}
