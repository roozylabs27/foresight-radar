<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\ReasoningEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PhaseTwoThreeRemediationTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private Dimension $dimEconomy;

    private Dimension $dimTechnology;

    private TimeHorizon $horizon;

    private Priority $priority;

    private StatusAction $statusAction;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->environment = Environment::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Macro',
            'code' => 'ME',
        ]);

        $this->dimEconomy = Dimension::create([
            'id' => 1,
            'uuid' => (string) Str::uuid(),
            'name' => 'Economy',
            'environment_id' => $this->environment->id,
        ]);

        $this->dimTechnology = Dimension::create([
            'id' => 2,
            'uuid' => (string) Str::uuid(),
            'name' => 'Technology',
            'environment_id' => $this->environment->id,
        ]);

        $this->horizon = TimeHorizon::create([
            'id' => 1,
            'uuid' => (string) Str::uuid(),
            'name' => 'Short Term',
            'alias' => 'ST',
            'code' => 'ST',
            'start_period' => 0,
            'end_period' => 2,
        ]);

        $this->priority = Priority::create([
            'id' => 1,
            'uuid' => (string) Str::uuid(),
            'name' => 'High',
            'code' => 'H',
            'color' => 'red',
        ]);

        $this->statusAction = StatusAction::create([
            'id' => 1,
            'uuid' => (string) Str::uuid(),
            'name' => 'Monitoring',
            'code' => 'M',
            'symbol' => 'M',
        ]);
    }

    private function createUserWithPermissions(array $permissions, string $roleName = 'Admin'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], [
            'uuid' => (string) Str::uuid(),
            'display_name' => $roleName,
        ]);

        foreach ($permissions as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web'], [
                'uuid' => (string) Str::uuid(),
                'display_name' => $perm,
                'description' => $perm,
                'category' => 'General',
            ]);
            $role->givePermissionTo($p);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_rel_005_export_data_groups_rows_contiguously_by_dimension(): void
    {
        $user = $this->createUserWithPermissions(['view-registered-list', 'export-registered-list']);

        // Create forces in alternating dimensions (Technology first, then Economy, then Technology)
        $forceTech1 = DrivingForce::create([
            'uuid' => (string) Str::uuid(),
            'keyword' => 'Tech Force One',
            'description' => str_repeat('tech one ', 25),
            'dimension_id' => $this->dimTechnology->id,
            'created_by' => $user->id,
            'pic_id' => $user->id,
            'status' => 'APPROVED',
        ]);
        DrivingForceRating::create([
            'uuid' => (string) Str::uuid(),
            'driving_force_id' => $forceTech1->id,
            'time_horizon_id' => $this->horizon->id,
            'priority_id' => $this->priority->id,
            'status_action_id' => $this->statusAction->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
        ]);

        $forceEcon1 = DrivingForce::create([
            'uuid' => (string) Str::uuid(),
            'keyword' => 'Economy Force One',
            'description' => str_repeat('economy one ', 25),
            'dimension_id' => $this->dimEconomy->id,
            'created_by' => $user->id,
            'pic_id' => $user->id,
            'status' => 'APPROVED',
        ]);
        DrivingForceRating::create([
            'uuid' => (string) Str::uuid(),
            'driving_force_id' => $forceEcon1->id,
            'time_horizon_id' => $this->horizon->id,
            'priority_id' => $this->priority->id,
            'status_action_id' => $this->statusAction->id,
            'impact_analysis' => 6,
            'uncertainty_analysis' => 5,
        ]);

        $forceTech2 = DrivingForce::create([
            'uuid' => (string) Str::uuid(),
            'keyword' => 'Tech Force Two',
            'description' => str_repeat('tech two ', 25),
            'dimension_id' => $this->dimTechnology->id,
            'created_by' => $user->id,
            'pic_id' => $user->id,
            'status' => 'APPROVED',
        ]);
        DrivingForceRating::create([
            'uuid' => (string) Str::uuid(),
            'driving_force_id' => $forceTech2->id,
            'time_horizon_id' => $this->horizon->id,
            'priority_id' => $this->priority->id,
            'status_action_id' => $this->statusAction->id,
            'impact_analysis' => 7,
            'uncertainty_analysis' => 6,
        ]);

        $response = $this->actingAs($user)->get(route('visualization.registered-list.export-data'));
        $response->assertOk();

        $exported = json_decode($response->streamedContent(), true);
        $this->assertCount(3, $exported);

        // Economy (dimension_id=1) must precede both Technology (dimension_id=2) rows contiguously
        $dimensions = array_column($exported, 'dimension');
        $this->assertSame(['Economy', 'Technology', 'Technology'], $dimensions);
    }

    public function test_perf_004_reasoning_engine_queries_dimensions_table_only_once(): void
    {
        /** @var ReasoningEngineService $service */
        $service = app(ReasoningEngineService::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $service->getExecutiveOverview();

        $dimensionQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains(strtolower($q['query']), 'from "dimensions"') || str_contains(strtolower($q['query']), 'from `dimensions`'));

        $this->assertCount(
            1,
            $dimensionQueries,
            'Expected ReasoningEngineService::getExecutiveOverview to query dimensions table only once, got '.$dimensionQueries->count()
        );
    }

    public function test_sec_007_inertia_shared_auth_user_minimizes_serialized_attributes(): void
    {
        $user = $this->createUserWithPermissions(['view-dashboard']);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $page = $response->viewData('page');
        $authUser = $page['props']['auth']['user'] ?? [];

        $this->assertSame(
            ['id', 'uuid', 'name', 'email', 'email_verified_at'],
            array_keys($authUser),
            'auth.user should only expose minimal identity attributes without nested Spatie relation trees'
        );
        $this->assertArrayNotHasKey('roles', $authUser);
        $this->assertArrayNotHasKey('permissions', $authUser);
    }

    public function test_rel_006_handles_api_errors_includes_both_message_and_errors_keys(): void
    {
        $handler = new class
        {
            use HandlesApiErrors;

            public function trigger(): array
            {
                return $this->handleError(new \RuntimeException('Database timeout'), 'testing context', 'Custom user message');
            }
        };

        $payload = $handler->trigger();

        $this->assertSame(500, $payload['statusCode']);
        $this->assertSame('Custom user message', $payload['message']);
        $this->assertSame('Custom user message', $payload['errors']);
    }
}
