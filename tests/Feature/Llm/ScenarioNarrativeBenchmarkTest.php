<?php

namespace Tests\Feature\Llm;

use App\Enums\LlmJobStatus;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Scenario;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Llm\LlmException;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

class ScenarioNarrativeBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;

    private Scenario $scenario;

    private Signal $signalA;

    private Signal $signalB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]
            );
        }

        $this->analyst = User::factory()->create();
        $this->analyst->givePermissionTo(['view-scenario', 'manage-scenario']);

        $macro = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $dimA = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Technological', 'environment_id' => $macro->id]);
        $dimB = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Regulatory', 'environment_id' => $macro->id]);

        $horizon = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);

        $forceA = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'Autonomous Transport',
            'description' => 'Autonomous freight transport networks',
            'status' => 'APPROVED',
            'dimension_id' => $dimA->id,
            'created_by' => $this->analyst->id,
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $forceA->id,
            'time_horizon_id' => $horizon->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
        ]);

        $forceB = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'Carbon Taxation',
            'description' => 'Cross-border border carbon adjustment tariffs',
            'status' => 'APPROVED',
            'dimension_id' => $dimB->id,
            'created_by' => $this->analyst->id,
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $forceB->id,
            'time_horizon_id' => $horizon->id,
            'impact_analysis' => 9,
            'uncertainty_analysis' => 8,
        ]);

        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Logistics Transformation Whitepaper',
            'raw_content' => 'Electric autonomous trucks achieve parity by 2028. Carbon border adjustment mechanisms take effect in 2026.',
            'content_hash' => hash('sha256', 'Logistics Transformation Whitepaper'),
            'extraction_status' => LlmJobStatus::COMPLETED,
            'created_by' => $this->analyst->id,
        ]);

        $this->signalA = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $dimA->id,
            'title' => 'Autonomous Freight Parity',
            'summary' => 'Electric autonomous freight reaching cost parity.',
            'evidence_quote' => 'Electric autonomous trucks achieve parity by 2028.',
            'review_status' => 'ACCEPTED',
            'is_ai_generated' => false,
            'quote_verified' => true,
        ]);
        $this->signalA->driving_forces()->attach($forceA->id);

        $this->signalB = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $dimB->id,
            'title' => 'CBAM Implementation',
            'summary' => 'Carbon border adjustment tax active across borders.',
            'evidence_quote' => 'Carbon border adjustment mechanisms take effect in 2026.',
            'review_status' => 'ACCEPTED',
            'is_ai_generated' => false,
            'quote_verified' => true,
        ]);
        $this->signalB->driving_forces()->attach($forceB->id);

        $this->scenario = Scenario::create([
            'name' => 'Transport Decarbonization 2030',
            'focal_question' => 'How will freight logistics evolve under carbon tariffs and autonomy?',
            'axis_a_force_id' => $forceA->id,
            'axis_b_force_id' => $forceB->id,
            'axis_a_low_label' => 'Manual',
            'axis_a_high_label' => 'Autonomous',
            'axis_b_low_label' => 'Low Tax',
            'axis_b_high_label' => 'High Tax',
            'created_by' => $this->analyst->id,
        ]);
    }

    public function test_benchmark_scenario_four_quadrant_synthesis_and_citation_mapping(): void
    {
        $llm = (new FakeLlmClient)->respond([
            'quadrants' => [
                [
                    'position' => 'HH',
                    'title' => 'Autonomous Green Freight',
                    'narrative' => 'Autonomous fleets dominate in high-tax environments [S1] supported by carbon tariffs [S2].',
                    'implications' => 'Requires aggressive early investment in autonomous electric charging depots.',
                    'early_indicators' => ['First autonomous corridor opens', 'CBAM tax rate exceeds $100/ton'],
                    'cited_refs' => ['S1', 'S2'],
                ],
                [
                    'position' => 'HL',
                    'title' => 'Autonomous Cost Minimization',
                    'narrative' => 'Autonomy scales without carbon regulation pressures [S1].',
                    'implications' => 'Fleet operators focus on labor efficiency over electrification.',
                    'early_indicators' => ['Level 4 autonomy approved on highways'],
                    'cited_refs' => ['S1'],
                ],
                [
                    'position' => 'LH',
                    'title' => 'Regulated Traditional Transit',
                    'narrative' => 'High carbon tariffs constrain transport margins while technology lags [S2].',
                    'implications' => 'Carriers face severe cost pressures and consolidation.',
                    'early_indicators' => ['High diesel surcharges passed to shippers'],
                    'cited_refs' => ['S2'],
                ],
                [
                    'position' => 'LL',
                    'title' => 'Status Quo Freight',
                    'narrative' => 'Slow adoption of autonomy with low regulatory stringency [S1].',
                    'implications' => 'Incremental supply chain evolution with standard equipment.',
                    'early_indicators' => ['Extended diesel fleet operational lifespans'],
                    'cited_refs' => ['S1'],
                ],
            ],
        ]);

        $service = new ScenarioNarrativeService($llm, app(ScenarioService::class));
        $result = $service->generate($this->scenario, false, $this->analyst->id);

        $this->scenario->refresh();
        $this->assertCount(4, $this->scenario->quadrants);
        $this->assertSame('claude', $result['provider']);

        $positions = $this->scenario->quadrants->pluck('position')->sort()->values()->all();
        $this->assertSame(['HH', 'HL', 'LH', 'LL'], $positions);

        $q1 = $this->scenario->quadrants->firstWhere('position', 'HH');
        $this->assertTrue($q1->is_ai_generated);
        $this->assertContains($this->signalA->id, $q1->cited_signal_ids);
        $this->assertContains($this->signalB->id, $q1->cited_signal_ids);
    }

    public function test_benchmark_scenario_rejects_hallucinated_citations(): void
    {
        $llm = (new FakeLlmClient)->respond([
            'quadrants' => [
                [
                    'position' => 'HH',
                    'title' => 'Hallucinated Citation',
                    'narrative' => 'Quadrant citing a non-existent signal [S99].',
                    'implications' => 'Test implications.',
                    'early_indicators' => ['Indicator A'],
                    'cited_refs' => ['S99'], // Invalid citation
                ],
                [
                    'position' => 'HL',
                    'title' => 'Valid HL',
                    'narrative' => 'Valid narrative citing [S1].',
                    'implications' => 'Valid implications.',
                    'early_indicators' => ['Indicator B'],
                    'cited_refs' => ['S1'],
                ],
                [
                    'position' => 'LH',
                    'title' => 'Valid LH',
                    'narrative' => 'Valid narrative citing [S2].',
                    'implications' => 'Valid implications.',
                    'early_indicators' => ['Indicator C'],
                    'cited_refs' => ['S2'],
                ],
                [
                    'position' => 'LL',
                    'title' => 'Valid LL',
                    'narrative' => 'Valid narrative citing [S1].',
                    'implications' => 'Valid implications.',
                    'early_indicators' => ['Indicator D'],
                    'cited_refs' => ['S1'],
                ],
            ],
        ]);

        $service = new ScenarioNarrativeService($llm, app(ScenarioService::class));

        $this->expectException(ValidationException::class);
        $service->generate($this->scenario, false, $this->analyst->id);
    }

    public function test_benchmark_scenario_fallback_to_template_when_llm_unavailable(): void
    {
        $failingLlm = (new FakeLlmClient)->respond(
            new LlmException('timeout', 'Gateway timeout during synthesis')
        );

        $service = new ScenarioNarrativeService($failingLlm, app(ScenarioService::class));
        $result = $service->generate($this->scenario, false, $this->analyst->id);

        $this->scenario->refresh();
        $this->assertSame(ScenarioNarrativeService::PROVIDER_TEMPLATE, $result['provider']);
        $this->assertCount(4, $this->scenario->quadrants);

        foreach ($this->scenario->quadrants as $quadrant) {
            $this->assertFalse($quadrant->is_ai_generated);
            $this->assertStringContainsString('Kerangka tanpa AI', $quadrant->narrative);
        }
    }
}
