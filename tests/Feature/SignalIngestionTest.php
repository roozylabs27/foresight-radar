<?php

namespace Tests\Feature;

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

class SignalIngestionTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;
    private Dimension $techDimension;
    private TimeHorizon $midHorizon;

    private function makePermission(string $name, string $category = 'general'): Permission
    {
        return Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'uuid' => (string) Uuid::uuid4(),
                'category' => $category,
                'description' => "Permission for {$name}",
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makePermission('view-signal');
        $this->makePermission('create-signal');
        $this->makePermission('review-signal');

        $this->analyst = User::factory()->create();
        $this->analyst->givePermissionTo(['view-signal', 'create-signal', 'review-signal']);

        $env = Environment::create(['name' => 'General', 'uuid' => (string) Uuid::uuid4()]);
        $this->techDimension = Dimension::create([
            'name' => 'Technology',
            'symbol' => 'T',
            'environment_id' => $env->id,
            'uuid' => (string) Uuid::uuid4(),
        ]);

        $this->midHorizon = TimeHorizon::create([
            'name' => 'Medium Term',
            'code' => 'MT',
            'uuid' => (string) Uuid::uuid4(),
        ]);
    }

    public function test_authenticated_user_with_permission_can_view_signals_page(): void
    {
        $response = $this->actingAs($this->analyst)->get('/signals');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Signals/Index'));
    }

    public function test_unauthenticated_user_cannot_access_signals_endpoints(): void
    {
        $response = $this->get('/signals');
        $response->assertRedirect('/login');

        $ingestRes = $this->postJson('/signals/ingest', ['title' => 'Test', 'content' => 'Sample']);
        $ingestRes->assertStatus(401);
    }

    public function test_unprivileged_user_gets_403_on_signals_routes(): void
    {
        $unprivileged = User::factory()->create();

        $res1 = $this->actingAs($unprivileged)->get('/signals');
        $res1->assertStatus(403);

        $res2 = $this->actingAs($unprivileged)->postJson('/signals/ingest', [
            'title' => 'Test Title',
            'content' => 'Short content that has more than 30 characters in total for testing.',
        ]);
        $res2->assertStatus(403);
    }

    public function test_ingest_source_creates_source_record_and_extracts_candidate_signals(): void
    {
        $sampleText = "Breakthrough commercial deployment of scalable solid-state battery technology achieved by researchers. " .
            "The energy density surpasses conventional lithium-ion by 45 percent, enabling long-range heavy transport. " .
            "Mass production is targeted for 2028 across major manufacturing facilities.";

        $response = $this->actingAs($this->analyst)->postJson('/signals/ingest', [
            'title' => 'Breakthrough Solid-State Battery Commercialization',
            'content' => $sampleText,
            'publisher' => 'Global Energy Review',
            'url' => 'https://energyreview.org/solid-state-2026',
            'published_at' => '2026-09-15',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'source' => ['id', 'uuid', 'title', 'content_hash'],
            'signals',
        ]);

        $this->assertDatabaseHas('sources', [
            'title' => 'Breakthrough Solid-State Battery Commercialization',
            'publisher' => 'Global Energy Review',
            'content_hash' => hash('sha256', trim($sampleText)),
        ]);

        $source = Source::where('title', 'Breakthrough Solid-State Battery Commercialization')->first();
        $this->assertNotNull($source);

        $signal = Signal::where('source_id', $source->id)->first();
        $this->assertNotNull($signal);
        $this->assertEquals('PENDING', $signal->review_status);
        $this->assertTrue($signal->is_ai_generated);
        $this->assertNotEmpty($signal->evidence_quote);
        $this->assertGreaterThan(0.70, $signal->confidence_score);
    }

    public function test_fetch_signals_endpoint_returns_paginated_json_with_filters(): void
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Report 2026',
            'raw_content' => 'Detailed test content for fetching signals.',
            'content_hash' => hash('sha256', 'Detailed test content for fetching signals.'),
            'created_by' => $this->analyst->id,
        ]);

        Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $this->techDimension->id,
            'title' => 'Quantum Encryption Standard',
            'summary' => 'NIST finalizes post-quantum encryption standards for enterprise.',
            'evidence_quote' => 'NIST finalizes post-quantum encryption standards for enterprise.',
            'review_status' => 'PENDING',
            'confidence_score' => 0.88,
        ]);

        $response = $this->actingAs($this->analyst)->getJson('/signals/fetch?status=PENDING');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
            'current_page',
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('Quantum Encryption Standard', $data[0]['title']);
    }

    public function test_analyst_can_accept_signal_and_convert_to_new_driving_force(): void
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'AI Robotics Breakthrough',
            'raw_content' => 'Humanoid robots enter automotive assembly lines.',
            'content_hash' => hash('sha256', 'Humanoid robots enter automotive assembly lines.'),
            'created_by' => $this->analyst->id,
        ]);

        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $this->techDimension->id,
            'title' => 'Autonomous Factory Robotics Integration',
            'summary' => 'Humanoid robots enter automotive assembly lines at scale.',
            'evidence_quote' => 'Humanoid robots enter automotive assembly lines at scale.',
            'review_status' => 'PENDING',
            'confidence_score' => 0.85,
        ]);

        $response = $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'accept_create',
            'title' => 'Autonomous Factory Robotics Integration',
            'summary' => 'Humanoid robots enter automotive assembly lines at scale.',
            'dimension_id' => $this->techDimension->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'signal',
            'driving_force',
        ]);

        $signal->refresh();
        $this->assertEquals('ACCEPTED', $signal->review_status);
        $this->assertEquals($this->analyst->id, $signal->reviewed_by);
        $this->assertNotNull($signal->reviewed_at);
        $this->assertNotNull($signal->created_driving_force_id);

        // Verify the newly created driving force exists in PENDING status
        $df = DrivingForce::find($signal->created_driving_force_id);
        $this->assertNotNull($df);
        $this->assertEquals('PENDING', $df->status);
        $this->assertEquals($this->techDimension->id, $df->dimension_id);

        // Verify pivot linkage
        $this->assertTrue($signal->driving_forces->contains($df->id));
    }

    public function test_analyst_can_accept_signal_and_link_to_existing_driving_force(): void
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Energy Transition Report',
            'raw_content' => 'Hydrogen pipeline expansion announced in Western Europe.',
            'content_hash' => hash('sha256', 'Hydrogen pipeline expansion announced in Western Europe.'),
            'created_by' => $this->analyst->id,
        ]);

        $existingDf = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'Hydrogen Network',
            'description' => 'Cross-border hydrogen transmission infrastructure',
            'dimension_id' => $this->techDimension->id,
            'created_by' => $this->analyst->id,
            'pic' => $this->analyst->id,
            'status' => 'PENDING',
        ]);

        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $this->techDimension->id,
            'title' => 'Hydrogen Grid Funding Approved',
            'summary' => 'Ten European nations approve financing for trunk pipeline.',
            'evidence_quote' => 'Ten European nations approve financing for trunk pipeline.',
            'review_status' => 'PENDING',
            'confidence_score' => 0.90,
        ]);

        $response = $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'accept_link',
            'driving_force_id' => $existingDf->id,
        ]);

        $response->assertStatus(200);

        $signal->refresh();
        $this->assertEquals('ACCEPTED', $signal->review_status);
        $this->assertNull($signal->created_driving_force_id);
        $this->assertTrue($signal->driving_forces->contains($existingDf->id));
    }

    public function test_analyst_can_reject_signal_with_reason(): void
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Speculative Venture Blog',
            'raw_content' => 'Cold fusion supposedly demonstrated in home workshop.',
            'content_hash' => hash('sha256', 'Cold fusion supposedly demonstrated in home workshop.'),
            'created_by' => $this->analyst->id,
        ]);

        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $this->techDimension->id,
            'title' => 'Unverified Cold Fusion Experiment',
            'summary' => 'Home workshop claims nuclear fusion without peer review.',
            'evidence_quote' => 'Home workshop claims nuclear fusion without peer review.',
            'review_status' => 'PENDING',
            'confidence_score' => 0.40,
        ]);

        $response = $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'reject',
            'rejection_reason' => 'Lacks empirical peer review and reproducibility.',
        ]);

        $response->assertStatus(200);

        $signal->refresh();
        $this->assertEquals('REJECTED', $signal->review_status);
        $this->assertEquals('Lacks empirical peer review and reproducibility.', $signal->rejection_reason);
        $this->assertEquals($this->analyst->id, $signal->reviewed_by);
        $this->assertNotNull($signal->reviewed_at);
    }

    public function test_ingestion_validates_required_fields_and_min_length(): void
    {
        $response = $this->actingAs($this->analyst)->postJson('/signals/ingest', [
            'title' => '',
            'content' => 'Too short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'content']);
    }

    private function pendingSignal(array $attributes = []): Signal
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Robotics Report',
            'raw_content' => 'Humanoid robots enter automotive assembly lines at scale across Asia.',
            'content_hash' => hash('sha256', 'Humanoid robots enter automotive assembly lines at scale across Asia.'),
            'created_by' => $this->analyst->id,
        ]);

        return Signal::create($attributes + [
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $this->techDimension->id,
            'title' => 'Autonomous Factory Robotics',
            'summary' => 'Humanoid robots enter automotive assembly lines at scale.',
            'evidence_quote' => 'Humanoid robots enter automotive assembly lines at scale across Asia.',
            'review_status' => 'PENDING',
            'confidence_score' => 0.85,
        ]);
    }

    public function test_signals_page_reports_the_extraction_mode(): void
    {
        $this->actingAs($this->analyst)->get('/signals')
            ->assertInertia(fn ($page) => $page
                ->where('extractionMode', 'heuristic')
                ->where('extractionModel', null)
                ->where('maxContentChars', 60000));
    }

    public function test_reviewing_an_already_reviewed_signal_is_a_conflict(): void
    {
        $signal = $this->pendingSignal(['review_status' => 'REJECTED', 'rejection_reason' => 'Duplicate']);

        $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'accept_create',
        ])->assertStatus(409);

        $this->assertSame('REJECTED', $signal->fresh()->review_status);
        $this->assertNull($signal->fresh()->created_driving_force_id);
    }

    public function test_signal_with_unverified_quote_cannot_be_accepted(): void
    {
        $signal = $this->pendingSignal(['quote_verified' => false]);

        $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", ['action' => 'accept_create'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidence_quote');

        $this->assertSame('PENDING', $signal->fresh()->review_status);
        $this->assertSame(0, DrivingForce::count());
    }

    public function test_edited_quote_is_verified_again_against_the_source(): void
    {
        $signal = $this->pendingSignal(['quote_verified' => true]);

        $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'update',
            'evidence_quote' => 'Robots replace every factory worker in Asia by next year.',
        ])->assertStatus(200);
        $this->assertFalse($signal->fresh()->quote_verified);

        $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", ['action' => 'accept_create'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidence_quote');

        $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'update',
            'evidence_quote' => 'Humanoid robots enter automotive assembly lines at scale',
        ])->assertStatus(200);
        $this->assertTrue($signal->fresh()->quote_verified);
    }

    public function test_older_signal_without_verification_can_still_be_accepted(): void
    {
        $signal = $this->pendingSignal();
        $this->assertNull($signal->fresh()->quote_verified);

        $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", ['action' => 'accept_create'])
            ->assertStatus(200);

        $this->assertSame('ACCEPTED', $signal->fresh()->review_status);
    }
}
