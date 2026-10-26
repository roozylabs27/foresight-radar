<?php

namespace Tests\Feature\Security;

use App\Enums\LlmJobStatus;
use App\Jobs\ExtractSignals;
use App\Jobs\GenerateScenarioNarrative;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Scenario;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ScenarioNarrativeService;
use App\Services\SignalExtractionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Throwable;

/**
 * Regression guard for TENANT-002: queued work runs under its own tenant only,
 * never under state left by a previous job, and not at all for a missing or
 * inactive tenant. See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class QueuedJobTenantContextTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'Cyberdyne Systems', 'slug' => 'cyberdyne', 'status' => 'active']);
    }

    public function test_worker_scope_reset_clears_tenant_context(): void
    {
        app(TenantContext::class)->setTenantId($this->tenantA->id);

        // What queue:work does before every job.
        app()->forgetScopedInstances();

        $this->assertNull(app(TenantContext::class)->getTenantId());
    }

    public function test_extraction_runs_under_its_own_tenant_and_restores_previous_context(): void
    {
        $source = $this->source($this->tenantB);
        $context = app(TenantContext::class);
        $context->setTenantId($this->tenantA->id);

        $seen = null;
        $this->mock(SignalExtractionService::class)->shouldReceive('extract')->once()
            ->andReturnUsing(function () use (&$seen) {
                $seen = app(TenantContext::class)->getTenantId();
            });

        ExtractSignals::dispatchSync($source);

        $this->assertSame($this->tenantB->id, $seen);
        $this->assertSame($this->tenantA->id, app(TenantContext::class)->getTenantId(), 'the dispatching request lost its tenant');
    }

    public function test_extraction_for_deleted_tenant_fails_without_running(): void
    {
        $source = $this->source($this->tenantB);
        $this->tenantB->delete();
        app(TenantContext::class)->setTenantId($this->tenantA->id);
        $this->mock(SignalExtractionService::class)->shouldNotReceive('extract');

        $this->runExpectingFailure(fn () => ExtractSignals::dispatchSync($source));

        $this->assertSame(LlmJobStatus::FAILED, $source->fresh()->extraction_status);
    }

    public function test_extraction_for_suspended_tenant_fails_without_running(): void
    {
        $source = $this->source($this->tenantB);
        $this->tenantB->update(['status' => 'suspended']);
        $this->mock(SignalExtractionService::class)->shouldNotReceive('extract');

        $this->runExpectingFailure(fn () => ExtractSignals::dispatchSync($source));

        $this->assertSame(LlmJobStatus::FAILED, $source->fresh()->extraction_status);
    }

    public function test_narrative_for_suspended_tenant_fails_without_running(): void
    {
        $scenario = $this->scenario($this->tenantB);
        $this->tenantB->update(['status' => 'suspended']);
        $this->mock(ScenarioNarrativeService::class)->shouldNotReceive('generate');

        $this->runExpectingFailure(fn () => GenerateScenarioNarrative::dispatchSync($scenario, false, null));

        $this->assertSame(LlmJobStatus::FAILED, $scenario->fresh()->generation_status);
    }

    public function test_narrative_runs_under_its_own_tenant(): void
    {
        $scenario = $this->scenario($this->tenantB);
        app(TenantContext::class)->setTenantId($this->tenantA->id);

        $seen = null;
        $this->mock(ScenarioNarrativeService::class)->shouldReceive('generate')->once()
            ->andReturnUsing(function () use (&$seen) {
                $seen = app(TenantContext::class)->getTenantId();

                return [];
            });

        GenerateScenarioNarrative::dispatchSync($scenario, false, null);

        $this->assertSame($this->tenantB->id, $seen);
    }

    private function runExpectingFailure(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable) {
            return;
        }

        $this->fail('The job ran instead of failing.');
    }

    private function source(Tenant $tenant): Source
    {
        return $this->asTenant($tenant, fn () => Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Source',
            'raw_content' => 'text',
            'content_hash' => hash('sha256', (string) Uuid::uuid4()),
            'extraction_status' => LlmJobStatus::QUEUED,
        ]));
    }

    private function scenario(Tenant $tenant): Scenario
    {
        return $this->asTenant($tenant, function () use ($tenant) {
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            $env = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'External']);
            $dimension = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Economy', 'environment_id' => $env->id]);
            $force = fn (string $keyword) => DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $dimension->id, 'created_by' => $user->id,
                'keyword' => $keyword, 'description' => $keyword, 'status' => 'APPROVED',
            ]);

            return Scenario::create([
                'name' => 'EV 2030',
                'focal_question' => 'Bagaimana pasar EV pada 2030?',
                'axis_a_force_id' => $force('Regulation')->id,
                'axis_b_force_id' => $force('Adoption')->id,
                'axis_a_low_label' => 'Longgar',
                'axis_a_high_label' => 'Ketat',
                'axis_b_low_label' => 'Lambat',
                'axis_b_high_label' => 'Cepat',
                'created_by' => $user->id,
                'generation_status' => LlmJobStatus::QUEUED,
            ]);
        });
    }

    private function asTenant(Tenant $tenant, callable $create): mixed
    {
        $context = app(TenantContext::class);
        $context->setTenant($tenant);
        try {
            return $create();
        } finally {
            $context->reset();
        }
    }
}
