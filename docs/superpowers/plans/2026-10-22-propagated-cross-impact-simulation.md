# Propagated Cross-Impact What-If Simulation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the "What-If" Scenario Simulation engine from direct-effects-only to an advanced systemic simulation engine that propagates cross-impact relationships ($\pm 3$) to second-order connected driving forces, allowing executives to observe how mitigating one strategic force ripples across the entire resilience and growth posture.

**Architecture:** Extend `ScenarioSimulationService` to query directed cross-impact edges (`CrossImpact`) between filtered approved forces when `propagate = true`. Apply calibrated propagation mathematics to calculate induced delta impact ($\Delta I_{prop}$) and delta uncertainty ($\Delta U_{prop}$) on target forces, clamping values to $[1, 10]$ without database mutation. Expose propagation traces in the simulation API envelope and provide an interactive toggle and propagation ripple panel in `ScenarioSimulation.jsx`.

**Tech Stack:** Laravel 10 (PHP 8.2), React 18, Inertia.js, Tailwind CSS v4, Lucide React, PHPUnit.

## Global Constraints

- **No Ant Design:** Frontend uses `shadcn/ui` + `radix-ui` + Tailwind CSS v4. Never introduce `antd`.
- **Pure Memory / Read-Only Invariant:** Simulation must NEVER mutate database records (`INSERT`, `UPDATE`, `DELETE` are strictly forbidden during simulation).
- **Backwards Compatibility:** Default behavior remains `propagate: false` (direct effects only) unless explicitly toggled.
- **Quality Gates:** 0 Pint issues, 0 Larastan Level 5 errors, 100% PHPUnit tests passing, Vite production build passing.

---

### Task 1: Backend Propagation Mathematics & `ScenarioSimulationService`

**Files:**
- Modify: `app/Services/ScenarioSimulationService.php`
- Modify: `app/Http/Requests/SimulationRequest.php`
- Test: `tests/Feature/Reasoning/ScenarioSimulationPropagationTest.php`

**Interfaces:**
- Input: `ScenarioSimulationService::simulate($dateRange, $dimension, array $overrides, bool $propagate = false): array`
- Mathematical Calibration:
  - For each override on source force $S$ and outgoing edge $S \xrightarrow{score} T$ (where $score \in \{-3 \dots +3\}$):
    - Normalized score: $s_{norm} = score / 3.0 \in [-1.0, 1.0]$.
    - If status of $S$ changes to `decided_plan`:
      - Active commitment on $S$ clarifies uncertainty on $T$:
        $$\Delta U_{prop}(T) = - s_{norm} \times 1.5$$ (positive synergy reduces uncertainty; negative pressure increases uncertainty).
      - If $S \xrightarrow{+} T$ (strengthening, $score > 0$):
        $$\Delta I_{prop}(T) = + s_{norm} \times 1.0$$
      - If $S \xrightarrow{-} T$ (mitigating/damping, $score < 0$):
        $$\Delta I_{prop}(T) = + s_{norm} \times 1.0$$ (lowers risk impact of $T$).
    - Clamping: $I_{final} = \text{clamp}(\text{round}(I_{base} + \Delta I_{prop}), 1, 10)$, $U_{final} = \text{clamp}(\text{round}(U_{base} + \Delta U_{prop}), 1, 10)$.
- Produces: simulation payload with additional keys:
  - `propagation_enabled`: bool
  - `propagations`: list of `array{source: string, source_keyword: string, target: string, target_keyword: string, score: int, induced_delta_impact: int, induced_delta_uncertainty: int}`

- [ ] **Step 1: Write failing test in `tests/Feature/Reasoning/ScenarioSimulationPropagationTest.php`**

```php
// tests/Feature/Reasoning/ScenarioSimulationPropagationTest.php
namespace Tests\Feature\Reasoning;

use App\Models\CrossImpact;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\ScenarioSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioSimulationPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_propagates_cross_impact_when_enabled(): void
    {
        $user = User::factory()->create();
        $dim = Dimension::firstOrCreate(['name' => 'Technology'], ['code' => 'TECH', 'type' => 'macro']);
        $th = TimeHorizon::firstOrCreate(['name' => 'Short Term'], ['horizon' => 'short']);
        $statusMon = StatusAction::where('name', 'Monitoring')->first() ?? StatusAction::create(['name' => 'Monitoring', 'code' => 'MONITORING']);
        $statusDec = StatusAction::where('name', 'Decided Plan')->first() ?? StatusAction::create(['name' => 'Decided Plan', 'code' => 'DECIDED_PLAN']);

        $forceA = DrivingForce::create(['keyword' => 'AI Robotics', 'dimension_id' => $dim->id, 'status' => 'APPROVED']);
        $forceB = DrivingForce::create(['keyword' => 'Labor Market', 'dimension_id' => $dim->id, 'status' => 'APPROVED']);

        DrivingForceRating::create([
            'driving_force_id' => $forceA->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
            'time_horizon_id' => $th->id,
            'status_action_id' => $statusMon->id,
        ]);

        DrivingForceRating::create([
            'driving_force_id' => $forceB->id,
            'impact_analysis' => 7,
            'uncertainty_analysis' => 8,
            'time_horizon_id' => $th->id,
            'status_action_id' => $statusMon->id,
        ]);

        // Cross-impact: AI Robotics (A) strongly pressures/affects Labor Market (B) with score -3
        CrossImpact::create([
            'source_driving_force_id' => $forceA->id,
            'target_driving_force_id' => $forceB->id,
            'score' => -3,
        ]);

        $service = app(ScenarioSimulationService::class);

        // Run direct simulation (propagate = false)
        $directResult = $service->simulate(null, null, [
            ['force' => $forceA->uuid, 'status_action' => 'decided_plan']
        ], propagate: false);

        $this->assertFalse($directResult['propagation_enabled']);
        $this->assertEmpty($directResult['propagations']);

        // Run propagated simulation (propagate = true)
        $propResult = $service->simulate(null, null, [
            ['force' => $forceA->uuid, 'status_action' => 'decided_plan']
        ], propagate: true);

        $this->assertTrue($propResult['propagation_enabled']);
        $this->assertNotEmpty($propResult['propagations']);
        $this->assertSame($forceA->uuid, $propResult['propagations'][0]['source']);
        $this->assertSame($forceB->uuid, $propResult['propagations'][0]['target']);
        $this->assertSame(-3, $propResult['propagations'][0]['score']);

        // Assert database was NOT mutated
        $this->assertDatabaseHas('driving_force_ratings', [
            'driving_force_id' => $forceA->id,
            'status_action_id' => $statusMon->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to confirm failure**
Run: `docker exec laravel_app php artisan test tests/Feature/Reasoning/ScenarioSimulationPropagationTest.php`

- [ ] **Step 3: Implement cross-impact propagation in `ScenarioSimulationService` and `SimulationRequest`**
Add `$propagate` parameter to `simulate()`, query `CrossImpact`, apply $\Delta I_{prop}$ and $\Delta U_{prop}$ to cloned ratings, and populate `propagations` audit list. Allow `propagate` in `SimulationRequest`.

- [ ] **Step 4: Run test to confirm passing**
Run: `docker exec laravel_app php artisan test tests/Feature/Reasoning/ScenarioSimulationPropagationTest.php`

- [ ] **Step 5: Run Pint and Larastan**
Run: `docker exec laravel_app ./vendor/bin/pint app/Services/ScenarioSimulationService.php app/Http/Requests/SimulationRequest.php tests/Feature/Reasoning/ScenarioSimulationPropagationTest.php`
Run: `docker exec laravel_app ./vendor/bin/phpstan analyse --memory-limit=2G app/Services/ScenarioSimulationService.php`

- [ ] **Step 6: Commit Task 1**
`git commit -m "feat(simulation): implement propagated cross impact scenario simulation"`

---

### Task 2: Frontend UI Integration (`ScenarioSimulation.jsx`)

**Files:**
- Modify: `resources/js/Components/ScenarioSimulation.jsx`

- [ ] **Step 1: Add Propagation Toggle and Ripple Effect Panel to `ScenarioSimulation.jsx`**
  - Add state `const [propagate, setPropagate] = useState(false)`.
  - Include `propagate` in the POST payload to `/dashboard/simulate`.
  - Add an intuitive Switch/Checkbox in the Simulation Control Bar: *"Perambatan Cross-Impact (Sistemik)"*.
  - When active and `simulationResult.propagations.length > 0`, render a dedicated **"Efek Riak Lintas-Force (Cross-Impact Ripple Trace)"** panel displaying cards with:
    - Source Force $\rightarrow$ Target Force.
    - Cross-impact badge (e.g. `+3 Memperkuat` hijau, `-3 Menekan/Memitigasi` merah).
    - Induced impact/uncertainty shift indicators ($\Delta I$, $\Delta U$).
  - Update banner text dynamically: when propagation is ON, state *"Simulasi Sistemik: Efek perambatan lintas-force aktif"*.

- [ ] **Step 2: Build frontend to verify 0 syntax/bundling errors**
Run: `npm run build`

- [ ] **Step 3: Commit Task 2**
`git commit -m "feat(ui): add cross impact propagation toggle and ripple trace to scenario simulation"`

---

### Task 3: Quality Gate Verification & Ledger Persistence

**Files:**
- Modify: `feature_list.json` (update FEAT-012 Strategic Executive Dashboard & What-If Simulation with propagation capability)
- Modify: `PROGRESS.md` (record session execution and verification evidence)

- [ ] **Step 1: Run Full Unified Verification Engine**
Run: `bash ./verify.sh --quick`

- [ ] **Step 2: Update `feature_list.json` and `PROGRESS.md`**

- [ ] **Step 3: Commit Task 3**
`git commit -m "docs(simulation): record propagated cross impact what-if simulation enhancement"`

