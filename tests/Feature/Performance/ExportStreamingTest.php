<?php

namespace Tests\Feature\Performance;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExportStreamingTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => 'report',
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_export_data_returns_streamed_json_array_compatible_with_resource(): void
    {
        $perm = $this->createPermission('export-registered-list');
        $user = User::factory()->create();
        $user->givePermissionTo($perm);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Tech', 'uuid' => Uuid::uuid1(), 'environment_id' => $env->id]);
        $df = DrivingForce::create([
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'keyword' => 'Robotics',
            'description' => 'Autonomous systems',
            'status' => 'APPROVED',
            'uuid' => Uuid::uuid1(),
        ]);
        $th = TimeHorizon::create(['name' => 'Short', 'code' => 'ST', 'uuid' => Uuid::uuid1()]);
        $sa = StatusAction::create(['name' => 'Act', 'symbol' => 'A', 'uuid' => Uuid::uuid1()]);

        DrivingForceRating::create([
            'driving_force_id' => $df->id,
            'time_horizon_id' => $th->id,
            'status_action_id' => $sa->id,
            'impact_analysis' => 4,
            'uncertainty_analysis' => 3,
        ]);

        $response = $this->actingAs($user)->get('/visualization/registered-list/export-data');

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        // Verify valid JSON array structure
        $content = $response->streamedContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('Robotics', $data[0]['keyword']);
        $this->assertEquals('Tech', $data[0]['dimension']);
    }

    public function test_export_data_returns_empty_json_array_when_no_records_exist(): void
    {
        $perm = $this->createPermission('export-registered-list');
        $user = User::factory()->create();
        $user->givePermissionTo($perm);

        $response = $this->actingAs($user)->get('/visualization/registered-list/export-data');

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertCount(0, $data);
        $this->assertSame('[]', $content);
    }

    public function test_export_data_streams_multiple_records_correctly(): void
    {
        $perm = $this->createPermission('export-registered-list');
        $user = User::factory()->create();
        $user->givePermissionTo($perm);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Tech', 'uuid' => Uuid::uuid1(), 'environment_id' => $env->id]);
        $th = TimeHorizon::create(['name' => 'Short', 'code' => 'ST', 'uuid' => Uuid::uuid1()]);
        $sa = StatusAction::create(['name' => 'Act', 'symbol' => 'A', 'uuid' => Uuid::uuid1()]);

        $df1 = DrivingForce::create([
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'keyword' => 'AI System',
            'description' => 'GenAI',
            'status' => 'APPROVED',
            'uuid' => Uuid::uuid1(),
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $df1->id,
            'time_horizon_id' => $th->id,
            'status_action_id' => $sa->id,
            'impact_analysis' => 5,
            'uncertainty_analysis' => 4,
        ]);

        $df2 = DrivingForce::create([
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'keyword' => 'Quantum Computing',
            'description' => 'Qubits',
            'status' => 'APPROVED',
            'uuid' => Uuid::uuid1(),
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $df2->id,
            'time_horizon_id' => $th->id,
            'status_action_id' => $sa->id,
            'impact_analysis' => 3,
            'uncertainty_analysis' => 2,
        ]);

        $response = $this->actingAs($user)->get('/visualization/registered-list/export-data');

        $response->assertOk();
        $content = $response->streamedContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $keywords = array_column($data, 'keyword');
        $this->assertContains('AI System', $keywords);
        $this->assertContains('Quantum Computing', $keywords);
    }
}
