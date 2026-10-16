<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Column sorting for the DataTable endpoints (app/Support/TableSort.php).
 */
class TableSortTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-driving-force', 'view-rating-urgency', 'view-user'] as $name) {
            Permission::create([
                'uuid' => fake()->uuid(),
                'name' => $name,
                'description' => $name,
                'category' => 'general',
                'guard_name' => 'web',
            ]);
        }

        $role = Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo(['view-driving-force', 'view-rating-urgency', 'view-user']);

        $this->admin = User::factory()->create(['name' => 'Mira Admin']);
        $this->admin->assignRole('super-admin');

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $economy = Dimension::create(['uuid' => fake()->uuid(), 'name' => 'Economy', 'environment_id' => $env->id]);
        $technology = Dimension::create(['uuid' => fake()->uuid(), 'name' => 'Technology', 'environment_id' => $env->id]);
        $horizon = TimeHorizon::create(['uuid' => fake()->uuid(), 'name' => 'Short Term', 'code' => 'short']);

        // Created oldest to newest, so the default order (newest first) is Charlie, Alpha, Bravo.
        $rows = [
            ['Bravo', $economy, 3, now()->startOfMonth()->addDay()],
            ['Alpha', $technology, 9, now()->startOfMonth()->addDays(2)],
            ['Charlie', $economy, 6, now()->startOfMonth()->addDays(3)],
        ];
        foreach ($rows as [$keyword, $dimension, $impact, $createdAt]) {
            $drivingForce = DrivingForce::create([
                'uuid' => fake()->uuid(),
                'keyword' => $keyword,
                'description' => "{$keyword} description",
                'dimension_id' => $dimension->id,
                'created_by' => $this->admin->id,
                'pic' => $this->admin->id,
                'status' => 'PENDING',
            ]);
            $drivingForce->forceFill(['created_at' => $createdAt])->save();

            DrivingForceRating::create([
                'driving_force_id' => $drivingForce->id,
                'time_horizon_id' => $horizon->id,
                'impact_analysis' => $impact,
                'uncertainty_analysis' => 5,
            ]);
        }
    }

    private function keywords(string $url, string $field = 'keyword'): array
    {
        $response = $this->actingAs($this->admin)->getJson($url);
        $response->assertOk();

        return array_column($response->json('data'), $field);
    }

    public function test_driving_forces_sort_by_keyword_both_ways(): void
    {
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->keywords('/driving-force/fetch-data?sortField=keyword&sortOrder=ascend'));
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->keywords('/driving-force/fetch-data?sortField=keyword&sortOrder=descend'));
    }

    public function test_driving_forces_sort_by_related_dimension_name(): void
    {
        // Economy before Technology; ties keep a stable newest-id-first order.
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->keywords('/driving-force/fetch-data?sortField=dimension&sortOrder=ascend'));
        $this->assertSame('Alpha', $this->keywords('/driving-force/fetch-data?sortField=dimension&sortOrder=descend')[0]);
    }

    public function test_unknown_field_or_order_keeps_the_default_newest_first_order(): void
    {
        $default = ['Charlie', 'Alpha', 'Bravo'];

        $this->assertSame($default, $this->keywords('/driving-force/fetch-data'));
        $this->assertSame($default, $this->keywords('/driving-force/fetch-data?sortField=description&sortOrder=ascend'));
        $this->assertSame($default, $this->keywords('/driving-force/fetch-data?sortField=keyword&sortOrder=sideways'));
        $this->assertSame($default, $this->keywords('/driving-force/fetch-data?sortField=' . urlencode('keyword desc, (select 1)') . '&sortOrder=ascend'));
    }

    public function test_rating_urgency_sorts_by_rating_score(): void
    {
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->keywords('/rating-urgency/fetch-data?sortField=impact&sortOrder=ascend'));
        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->keywords('/rating-urgency/fetch-data?sortField=impact&sortOrder=descend'));
    }

    public function test_users_sort_by_name(): void
    {
        $member = Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'analyst',
            'display_name' => 'Analyst',
            'guard_name' => 'web',
        ]);
        foreach (['Zaki', 'Ayu'] as $name) {
            User::factory()->create(['name' => $name])->assignRole($member);
        }

        $names = $this->keywords('/user-management/user/fetch-data?sortField=name&sortOrder=ascend', 'name');
        $this->assertSame(['Ayu', 'Mira Admin', 'Zaki'], $names);
    }
}
