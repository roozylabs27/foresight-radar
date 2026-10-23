<?php

namespace Tests\Feature\Audit;

use App\Enums\LlmJobStatus;
use App\Jobs\ExtractSignals;
use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\Signal;
use App\Models\Source;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneRemediationTest extends TestCase
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

    public function test_perf_001_paginated_endpoints_clamp_page_size_to_100(): void
    {
        $this->seedLookupTables();

        $perms = [
            'view-driving-force',
            'view-registered-list',
            'view-signal',
            'view-user',
        ];
        foreach ($perms as $perm) {
            $this->makePermission($perm);
        }

        $devRole = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Developer',
        ]);
        $devRole->givePermissionTo($perms);

        $user = User::factory()->create();
        $user->assignRole($devRole);

        $dfResp = $this->actingAs($user)->getJson('/driving-force/fetch-data?pagination[pageSize]=50000');
        $dfResp->assertStatus(200);
        $this->assertSame(100, $dfResp->json('meta.per_page'));

        $regResp = $this->actingAs($user)->getJson('/visualization/registered-list/get-data?pagination[pageSize]=50000');
        $regResp->assertStatus(200);
        $this->assertSame(100, $regResp->json('meta.per_page'));

        $sigResp = $this->actingAs($user)->getJson('/signals/fetch?pageSize=50000');
        $sigResp->assertStatus(200);
        $this->assertSame(100, $sigResp->json('per_page'));

        $userResp = $this->actingAs($user)->getJson('/user-management/user/fetch-data?pagination[pageSize]=50000');
        $userResp->assertStatus(200);
        $this->assertSame(100, $userResp->json('meta.per_page'));
    }

    public function test_perf_002_approval_closed_and_status_action_endpoints_eliminate_n_plus_1_queries(): void
    {
        $this->seedLookupTables();

        foreach (['view-approval-items', 'view-closed-items', 'view-status-action'] as $perm) {
            $this->makePermission($perm);
        }

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-approval-items', 'view-closed-items', 'view-status-action']);

        for ($i = 1; $i <= 10; $i++) {
            foreach (['PENDING', 'CLOSED'] as $status) {
                $force = DrivingForce::create([
                    'uuid' => (string) Uuid::uuid4(),
                    'dimension_id' => $this->dimension->id,
                    'pic' => $viewer->id,
                    'created_by' => $viewer->id,
                    'keyword' => "Force {$status} {$i}",
                    'description' => 'Description for N+1 query benchmark verification.',
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
                    'reason' => "Reason A {$i}",
                    'date' => now()->subMinute(),
                ]);

                ActionReason::create([
                    'uuid' => (string) Uuid::uuid4(),
                    'driving_force_rating_id' => $rating->id,
                    'status_action_id' => $this->statusAction->id,
                    'reason' => "Reason B {$i}",
                    'date' => now(),
                ]);
            }
        }

        foreach (['/approval-items/fetch-data', '/closed-items/fetch-data', '/status-action/fetch-data'] as $endpoint) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $response = $this->actingAs($viewer)->getJson($endpoint);
            $response->assertStatus(200);
            $this->assertCount(10, $response->json('data'));

            $queryCount = count(DB::getQueryLog());
            $this->assertLessThanOrEqual(
                12,
                $queryCount,
                "Endpoint {$endpoint} executed {$queryCount} queries for 10 rows (expected <= 12 without N+1)."
            );
        }
    }

    public function test_perf_003_signals_fetch_omits_raw_content_from_source_relation(): void
    {
        $this->seedLookupTables();
        $this->makePermission('view-signal');

        $analyst = User::factory()->create();
        $analyst->givePermissionTo('view-signal');

        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Long Strategic Report',
            'url' => 'https://example.com/report',
            'publisher' => 'Foresight Institute',
            'raw_content' => str_repeat('Very long raw source document content. ', 500),
            'content_hash' => hash('sha256', 'long-report'),
            'created_by' => $analyst->id,
            'extraction_status' => LlmJobStatus::COMPLETED,
        ]);

        Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $this->dimension->id,
            'title' => 'Extracted Signal Title',
            'summary' => 'Signal summary text',
            'evidence_quote' => 'Very long raw source document content.',
            'review_status' => 'PENDING',
            'quote_verified' => true,
        ]);

        $response = $this->actingAs($analyst)->getJson('/signals/fetch');
        $response->assertStatus(200);
        $this->assertSame('Long Strategic Report', $response->json('data.0.source.title'));
        $this->assertArrayNotHasKey('raw_content', $response->json('data.0.source'));
    }

    public function test_sec_003_source_extraction_requires_ownership_and_closed_forces_reject_stage_mutations(): void
    {
        $this->seedLookupTables();

        foreach (['view-signal', 'create-time-horizon', 'create-rating-urgency', 'create-status-action'] as $perm) {
            $this->makePermission($perm);
        }

        $owner = User::factory()->create();
        $otherAnalyst = User::factory()->create();
        $otherAnalyst->givePermissionTo(['view-signal', 'create-time-horizon', 'create-rating-urgency', 'create-status-action']);

        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Owner Private Source',
            'raw_content' => str_repeat('Private intelligence content. ', 5),
            'content_hash' => hash('sha256', 'owner-source'),
            'created_by' => $owner->id,
            'extraction_status' => LlmJobStatus::PROCESSING,
        ]);

        // Another non-admin analyst cannot poll owner's source extraction status
        $this->actingAs($otherAnalyst)
            ->getJson("/signals/sources/{$source->uuid}/extraction")
            ->assertStatus(403);

        // Closed driving force cannot be mutated via stage endpoints
        $closedForce = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'dimension_id' => $this->dimension->id,
            'pic' => $otherAnalyst->id,
            'created_by' => $otherAnalyst->id,
            'keyword' => 'Archived Closed Force',
            'description' => 'Closed driving force description.',
            'status' => 'CLOSED',
            'closed_at' => now(),
        ]);

        $closedRating = DrivingForceRating::create([
            'uuid' => (string) Uuid::uuid4(),
            'driving_force_id' => $closedForce->id,
            'time_horizon_id' => $this->timeHorizon->id,
            'priority_id' => $this->priority->id,
            'status_action_id' => $this->statusAction->id,
            'impact_analysis' => 6,
            'uncertainty_analysis' => 6,
        ]);

        $this->actingAs($otherAnalyst)
            ->postJson("/time-horizon/{$closedForce->id}", ['time_horizon_id' => $this->timeHorizon->id])
            ->assertStatus(422);

        $this->actingAs($otherAnalyst)
            ->postJson("/rating-urgency/{$closedRating->uuid}", ['type' => 'impact', 'value' => 10])
            ->assertStatus(422);

        $this->actingAs($otherAnalyst)
            ->postJson("/status-action/{$closedRating->uuid}", [
                'status_action_id' => $this->statusAction->id,
                'reason' => 'Attempting to mutate closed item',
            ])
            ->assertStatus(422);
    }

    public function test_sec_005_user_creation_assigns_role_without_duplicating_direct_permissions(): void
    {
        $createUserPerm = $this->makePermission('create-user');
        $viewDashboardPerm = $this->makePermission('view-dashboard');

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Admin',
        ]);
        $adminRole->givePermissionTo([$createUserPerm, $viewDashboardPerm]);

        $targetRole = Role::firstOrCreate(['name' => 'analyst', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Analyst',
        ]);
        $targetRole->givePermissionTo($viewDashboardPerm);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->postJson('/user-management/user?type=create', [
            'name' => 'New Analyst',
            'email' => 'analyst@example.com',
            'password' => 'Secret123!',
            'role_id' => $targetRole->id,
        ]);

        $response->assertStatus(201);

        $createdUser = User::where('email', 'analyst@example.com')->firstOrFail();
        $this->assertTrue($createdUser->hasRole('analyst'));
        $this->assertTrue($createdUser->can('view-dashboard'));
        // Direct permissions table should be empty so role permission revocation works
        $this->assertCount(0, $createdUser->permissions);
    }

    public function test_rel_004_extract_signals_job_implements_should_be_unique(): void
    {
        $user = User::factory()->create();
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Unique Job Source',
            'raw_content' => str_repeat('Unique source content for job check. ', 5),
            'content_hash' => hash('sha256', 'unique-job'),
            'created_by' => $user->id,
            'extraction_status' => LlmJobStatus::QUEUED,
        ]);

        $job = new ExtractSignals($source);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $source->id, $job->uniqueId());
    }
}
