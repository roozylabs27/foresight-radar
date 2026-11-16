<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\ScenarioPlanningProject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Llm\AiStatusStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Server-Sent Events for the AI assists of an exercise (2026-11-16): the page learns that a queued
 * job finished without polling. Streams are short (they end after `ops.sse.max_seconds`, the browser
 * reconnects) and capped (`ops.sse.max_streams`), so PHP-FPM workers are never all held by streams.
 */
class AiStatusStreamTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private ScenarioPlanningProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'view-scenario', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => 'view-scenario']);
        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo('view-scenario');
        $this->project = ScenarioPlanningProject::create([
            'uuid' => (string) Uuid::uuid4(), 'name' => 'Stream', 'focal_question' => 'Bagaimana pasar baterai berubah hingga 2035?',
            'purpose' => 'Uji.', 'target_year' => now()->year + 9, 'status' => 'draft', 'created_by' => $this->viewer->id,
            'focal_suggestion_status' => 'processing',
        ]);
        config(['ops.sse.max_seconds' => 0, 'ops.sse.interval_ms' => 10]);
    }

    private function stream()
    {
        return $this->actingAs($this->viewer)->get(route('scenario-planning.ai-status.stream', $this->project));
    }

    public function test_it_streams_the_current_ai_statuses_as_server_sent_events_and_ends(): void
    {
        $response = $this->stream();

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('retry: ', $body);
        $this->assertMatchesRegularExpression('/event: status\ndata: (\{.*\})\n\n/', $body);
        preg_match('/event: status\ndata: (\{.*\})\n\n/', $body, $match);
        $statuses = json_decode($match[1], true);
        $this->assertSame('processing', $statuses['focal']);
        $this->assertArrayHasKey('narrative', $statuses);
        $this->assertStringEndsWith("event: end\ndata: {}\n\n", $body);
    }

    public function test_a_status_change_during_the_stream_is_pushed(): void
    {
        $statuses = app(AiStatusStream::class);
        $first = $statuses->snapshot($this->project);
        $this->project->forceFill(['focal_suggestion_status' => 'completed'])->save();

        $this->assertNotSame($first, $statuses->snapshot($this->project));
        $this->assertSame('completed', $statuses->snapshot($this->project)['focal']);
    }

    public function test_another_organisation_cannot_open_the_stream(): void
    {
        $other = Tenant::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Lain', 'slug' => 'lain', 'status' => 'active']);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);
        $outsider->givePermissionTo('view-scenario');

        $this->actingAs($outsider)->get(route('scenario-planning.ai-status.stream', $this->project))->assertNotFound();
    }

    public function test_without_view_permission_the_stream_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->get(route('scenario-planning.ai-status.stream', $this->project))->assertForbidden();
    }

    public function test_when_every_stream_slot_is_taken_it_answers_503_so_the_page_falls_back_to_polling(): void
    {
        config(['ops.sse.max_streams' => 1]);
        $held = Cache::lock('ai-sse-slot:0', 60);
        $this->assertTrue($held->get());

        $this->stream()->assertStatus(503)->assertHeader('Retry-After');

        $held->release();
        $this->stream()->assertOk();
    }
}
