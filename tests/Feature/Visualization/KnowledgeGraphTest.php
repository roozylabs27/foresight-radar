<?php

namespace Tests\Feature\Visualization;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KnowledgeGraphTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;

    private Dimension $dimension;

    private DrivingForce $drivingForce;

    private Signal $signal;

    private Source $source;

    private function makePermission(string $name): Permission
    {
        return Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'uuid' => (string) Uuid::uuid4(),
                'category' => 'visualization',
                'description' => "Permission for {$name}",
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makePermission('view-foresight-radar');

        $this->analyst = User::factory()->create();
        $this->analyst->givePermissionTo('view-foresight-radar');

        $env = Environment::create(['name' => 'General', 'uuid' => (string) Uuid::uuid4()]);
        $this->dimension = Dimension::create([
            'name' => 'Technology',
            'symbol' => 'T',
            'environment_id' => $env->id,
            'uuid' => (string) Uuid::uuid4(),
        ]);

        $horizon = TimeHorizon::create([
            'name' => 'Medium Term',
            'code' => 'MT',
            'order' => 2,
            'uuid' => (string) Uuid::uuid4(),
        ]);

        $this->drivingForce = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'Autonomous Quantum Cryptography',
            'description' => 'Breakthrough post-quantum encryption protocols.',
            'dimension_id' => $this->dimension->id,
            'status' => 'APPROVED',
            'created_by' => $this->analyst->id,
        ]);

        $this->source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Quantum Security in Banking 2026',
            'publisher' => 'Bank Indonesia',
            'url' => 'https://example.com/quantum',
            'raw_content' => 'Lattice-based cryptography is essential for future banking infrastructure.',
            'content_hash' => hash('sha256', 'Lattice-based cryptography is essential for future banking infrastructure.'),
            'created_by' => $this->analyst->id,
        ]);

        $this->signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $this->source->id,
            'dimension_id' => $this->dimension->id,
            'suggested_time_horizon_id' => $horizon->id,
            'title' => 'Accelerated PQC Migration',
            'summary' => 'Financial institutions mandate PQC by 2027.',
            'evidence_quote' => 'Lattice-based cryptography is essential',
            'quote_verified' => true,
            'confidence_score' => 0.95,
            'preliminary_impact' => 8,
            'preliminary_uncertainty' => 4,
            'review_status' => 'ACCEPTED',
            'created_driving_force_id' => $this->drivingForce->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_view_knowledge_graph(): void
    {
        $this->get('/visualization/knowledge-graph')->assertRedirect('/login');
        $this->getJson('/visualization/knowledge-graph/get-data')->assertStatus(401);
    }

    public function test_unprivileged_user_gets_403_on_knowledge_graph(): void
    {
        $unprivileged = User::factory()->create();

        $this->actingAs($unprivileged)
            ->get('/visualization/knowledge-graph')
            ->assertStatus(403);

        $this->actingAs($unprivileged)
            ->getJson('/visualization/knowledge-graph/get-data')
            ->assertStatus(403);
    }

    public function test_authorized_user_can_view_knowledge_graph_page(): void
    {
        $response = $this->actingAs($this->analyst)->get('/visualization/knowledge-graph');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Visualization/KnowledgeGraph')
            ->has('title')
        );
    }

    public function test_knowledge_graph_data_endpoint_returns_nodes_and_links(): void
    {
        $response = $this->actingAs($this->analyst)->getJson('/visualization/knowledge-graph/get-data');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('nodes', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('dimensions', $data);

        // Assert nodes contain driving force, signal, and source
        $nodeTypes = collect($data['nodes'])->pluck('type')->all();
        $this->assertContains('driving_force', $nodeTypes);
        $this->assertContains('signal', $nodeTypes);
        $this->assertContains('source', $nodeTypes);

        // Assert links connect source -> signal and signal -> driving_force
        $linkTypes = collect($data['links'])->pluck('type')->all();
        $this->assertContains('source_to_signal', $linkTypes);
        $this->assertContains('signal_to_df', $linkTypes);

        // Assert stats counts
        $this->assertEquals(1, $data['stats']['driving_forces_count']);
        $this->assertEquals(1, $data['stats']['signals_count']);
        $this->assertEquals(1, $data['stats']['sources_count']);
    }
}

