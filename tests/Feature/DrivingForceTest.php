<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrivingForceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Dimension $dimension;

    private function makePermission(string $name): Permission
    {
        return Permission::create([
            'uuid' => fake()->uuid(),
            'name' => $name,
            'description' => $name,
            'category' => 'general',
            'guard_name' => 'web',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makePermission('view-driving-force');
        $this->makePermission('create-driving-force');
        $this->makePermission('update-driving-force');
        $this->makePermission('delete-driving-force');
        $this->makePermission('view-dashboard');

        $role = Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'admin',
            'display_name' => 'Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'view-driving-force',
            'create-driving-force',
            'update-driving-force',
            'delete-driving-force',
            'view-dashboard',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->syncPermissions($role->permissions);

        $env = Environment::create([
            'uuid' => fake()->uuid(),
            'name' => 'External',
        ]);

        $this->dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);
    }

    public function test_authenticated_user_can_view_driving_force_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/driving-force');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_driving_force(): void
    {
        $response = $this->get('/driving-force');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_driving_force(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/driving-force', [
            'keyword' => 'Test Signal',
            'description' => 'Test description for signal',
            'dimension_id' => $this->dimension->id,
            'pic_id' => $this->admin->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('driving_forces', ['keyword' => 'Test Signal']);
    }

    public function test_admin_can_delete_own_driving_force(): void
    {
        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'To Delete',
            'description' => 'Will be deleted',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/driving-force/{$drivingForce->uuid}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('driving_forces', ['id' => $drivingForce->id]);
    }

    public function test_driving_force_fetch_data_returns_signal_provenance_data(): void
    {
        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'AI Drug Discovery',
            'description' => 'Generative biology accelerates pharmacology',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $raw = 'Full text content from biotechnology journal about novel generative AI models.';
        $source = \App\Models\Source::create([
            'uuid' => (string) \Ramsey\Uuid\Uuid::uuid4(),
            'title' => 'Nature Biotechnology',
            'url' => 'https://nature.com/articles/ai-drugs',
            'raw_content' => $raw,
            'content_hash' => hash('sha256', $raw),
            'created_by' => $this->admin->id,
        ]);

        \App\Models\Signal::create([
            'uuid' => fake()->uuid(),
            'source_id' => $source->id,
            'dimension_id' => $this->dimension->id,
            'title' => 'Breakthrough in Target Discovery',
            'summary' => 'Deep neural models map novel binding sites.',
            'evidence_quote' => 'Deep neural models map novel binding sites.',
            'confidence_score' => 0.95,
            'created_driving_force_id' => $drivingForce->id,
            'review_status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/driving-force/fetch-data');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $item = collect($data)->firstWhere('keyword', 'AI Drug Discovery');
        $this->assertNotNull($item);
        $this->assertEquals(1, $item['signals_count']);
        $this->assertNotNull($item['source_signal']);
        $this->assertEquals('Breakthrough in Target Discovery', $item['source_signal']['title']);
        $this->assertEquals('Nature Biotechnology', $item['source_signal']['source_title']);
        $this->assertEquals('https://nature.com/articles/ai-drugs', $item['source_signal']['source_url']);
    }

    public function test_soft_deleting_driving_force_cascades_to_rating(): void
    {
        $timeHorizon = \App\Models\TimeHorizon::create([
            'uuid' => fake()->uuid(),
            'name' => 'Medium Term',
            'code' => 'MT',
        ]);

        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Cascade Test',
            'description' => 'Test cascade soft deletion',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $rating = \App\Models\DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $drivingForce->id,
            'time_horizon_id' => $timeHorizon->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
        ]);

        $this->assertDatabaseHas('driving_force_ratings', [
            'id' => $rating->id,
            'deleted_at' => null,
        ]);

        // Soft delete driving force
        $drivingForce->delete();

        $this->assertSoftDeleted('driving_forces', ['id' => $drivingForce->id]);
        $this->assertSoftDeleted('driving_force_ratings', ['id' => $rating->id]);
        $this->assertNull(\App\Models\DrivingForceRating::find($rating->id));
        $this->assertNotNull(\App\Models\DrivingForceRating::withTrashed()->find($rating->id));

        // Restore driving force
        $drivingForce->restore();

        $this->assertNotSoftDeleted('driving_forces', ['id' => $drivingForce->id]);
        $this->assertNotSoftDeleted('driving_force_ratings', ['id' => $rating->id]);
        $this->assertNotNull(\App\Models\DrivingForceRating::find($rating->id));
    }

    public function test_driving_force_fetch_data_returns_pipeline_stepper_metadata(): void
    {
        $timeHorizon = \App\Models\TimeHorizon::create([
            'uuid' => fake()->uuid(),
            'name' => 'Near Term',
            'code' => 'NT',
        ]);

        // Case 1: unrated driving force is at Step 2
        $df1 = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Unrated DF',
            'description' => 'Awaiting time horizon',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        // Case 2: rated time horizon but pending urgency is at Step 3
        $df2 = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Horizon Rated DF',
            'description' => 'Awaiting urgency analysis',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        \App\Models\DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $df2->id,
            'time_horizon_id' => $timeHorizon->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/driving-force/fetch-data');
        $response->assertStatus(200);

        $data = $response->json('data');
        $item1 = collect($data)->firstWhere('keyword', 'Unrated DF');
        $this->assertNotNull($item1['pipeline']);
        $this->assertEquals(2, $item1['pipeline']['step']);
        $this->assertEquals('/time-horizon', $item1['pipeline']['next_url']);

        $item2 = collect($data)->firstWhere('keyword', 'Horizon Rated DF');
        $this->assertNotNull($item2['pipeline']);
        $this->assertEquals(3, $item2['pipeline']['step']);
        $this->assertEquals('/rating-urgency', $item2['pipeline']['next_url']);
    }
}

