# Synthetic Data Generator & Theory Reconciliation Harness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a deterministic synthetic data generator, mathematical clustering & PCA evaluation engine, and theory reconciliation benchmark harness to validate foresight analytics (K-Means, PCA, Causal Loops, and Vester archetypes) against domain invariants prior to UI delivery.

**Architecture:** A standalone domain harness layer (`app/Services/Harness/`) containing a seedable PRNG generator (`SyntheticForesightGenerator`), mathematical statistical evaluators (`KMeansClusterer`, `SilhouetteEvaluator`, `PcaTransformer`), and an epistemic domain verifier (`TheoryReconciliationService`). Evaluated offline via dedicated PHPUnit suites (`tests/Feature/Harness/`) and an Artisan benchmark command (`php artisan harness:eval-clustering`).

**Tech Stack:** Laravel 10 (PHP 8.2), PHPUnit, Larastan Level 5, Pint, System Dynamics & Matrix mathematics.

## Global Constraints

- **No Ant Design:** Frontend never imports or uses Ant Design components (`antd`).
- **PHP 8.2 & Laravel 10:** Strict typing (`declare(strict_types=1);`), no untyped properties or mixed parameters without PHPDoc.
- **Deterministic & Offline:** 100% offline executable, reproducible via explicit integer seed; zero external API dependencies.
- **Quality Gates:** 0 Pint issues, 0 Larastan Level 5 errors, 100% PHPUnit tests passing.

---

### Task 1: Synthetic Foresight Data Generator (`SyntheticForesightGenerator`)

**Files:**
- Create: `app/Services/Harness/SyntheticForesightGenerator.php`
- Test: `tests/Unit/Harness/SyntheticForesightGeneratorTest.php`

**Interfaces:**
- Consumes: Eloquent models (`DrivingForce`, `DrivingForceRating`, `Dimension`, `StatusActionType`) or returns in-memory synthetic arrays/collections.
- Produces: `SyntheticForesightGenerator::generate(int $count = 150, int $seed = 42): array` returning generated forces with dimensions, time horizons, impact (1–10), uncertainty (1–10), status actions, and an aligned cross-impact matrix $(-3 \dots +3)$.

- [ ] **Step 1: Write the failing unit test for `SyntheticForesightGenerator`**

```php
// tests/Unit/Harness/SyntheticForesightGeneratorTest.php
namespace Tests\Unit\Harness;

use App\Services\Harness\SyntheticForesightGenerator;
use Tests\TestCase;

class SyntheticForesightGeneratorTest extends TestCase
{
    public function test_generates_exact_requested_count_with_reproducible_seed(): void
    {
        $generator = new SyntheticForesightGenerator();
        $datasetA = $generator->generate(count: 50, seed: 12345);
        $datasetB = $generator->generate(count: 50, seed: 12345);

        $this->assertCount(50, $datasetA['forces']);
        $this->assertSame($datasetA, $datasetB);
    }

    public function test_generated_attributes_satisfy_domain_boundaries(): void
    {
        $generator = new SyntheticForesightGenerator();
        $dataset = $generator->generate(count: 30, seed: 42);

        foreach ($dataset['forces'] as $force) {
            $this->assertGreaterThanOrEqual(1, $force['impact']);
            $this->assertLessThanOrEqual(10, $force['impact']);
            $this->assertGreaterThanOrEqual(1, $force['uncertainty']);
            $this->assertLessThanOrEqual(10, $force['uncertainty']);
            $this->assertContains($force['time_horizon'], ['Short Term', 'Mid Term', 'Long Term']);
            $this->assertContains($force['status_action'], ['monitoring', 'decided_plan']);
        }
    }
}
```

- [ ] **Step 2: Run test to confirm failure**
Run: `docker exec laravel_app php artisan test tests/Unit/Harness/SyntheticForesightGeneratorTest.php`

- [ ] **Step 3: Implement `SyntheticForesightGenerator`**
Implement seedable pseudo-random generator creating realistic distribution profiles (Macro & Mezzo dimensions, cluster tendencies: Catalyst, Wildcard, Latent Anchor, and dense cross-impact matrix).

- [ ] **Step 4: Run test to confirm passing**
Run: `docker exec laravel_app php artisan test tests/Unit/Harness/SyntheticForesightGeneratorTest.php`

- [ ] **Step 5: Commit**
`git add app/Services/Harness/SyntheticForesightGenerator.php tests/Unit/Harness/SyntheticForesightGeneratorTest.php`
`git commit -m "feat(harness): implement deterministic synthetic foresight generator"`

---

### Task 2: Mathematical Analytics Engine (K-Means, Silhouette & PCA 2D/3D)

**Files:**
- Create: `app/Services/Harness/KMeansClusterer.php`
- Create: `app/Services/Harness/SilhouetteEvaluator.php`
- Create: `app/Services/Harness/PcaTransformer.php`
- Test: `tests/Unit/Harness/MathematicalAnalyticsTest.php`

**Interfaces:**
- `KMeansClusterer::cluster(array $samples, int $k = 3, int $maxIterations = 100, int $seed = 42): array` (returns `['clusters' => array, 'centroids' => array]`)
- `SilhouetteEvaluator::score(array $samples, array $clusterAssignments): float` (returns overall silhouette coefficient $[-1.0 \dots 1.0]$)
- `PcaTransformer::project(array $samples, int $dimensions = 2): array` (returns covariance-based projected coordinates)

- [ ] **Step 1: Write failing unit tests for mathematical clustering and PCA**

```php
// tests/Unit/Harness/MathematicalAnalyticsTest.php
namespace Tests\Unit\Harness;

use App\Services\Harness\KMeansClusterer;
use App\Services\Harness\PcaTransformer;
use App\Services\Harness\SilhouetteEvaluator;
use Tests\TestCase;

class MathematicalAnalyticsTest extends TestCase
{
    public function test_kmeans_separates_well_defined_clusters(): void
    {
        $clusterer = new KMeansClusterer();
        $samples = [
            [1.0, 1.0], [1.1, 0.9], [0.9, 1.2], // Cluster 0
            [8.0, 8.0], [8.2, 7.9], [7.8, 8.1], // Cluster 1
        ];

        $result = $clusterer->cluster($samples, k: 2, seed: 42);
        $this->assertCount(2, $result['centroids']);
        $this->assertEquals($result['assignments'][0], $result['assignments'][1]);
        $this->assertNotEquals($result['assignments'][0], $result['assignments'][3]);
    }

    public function test_silhouette_evaluator_evaluates_cluster_cohesion(): void
    {
        $evaluator = new SilhouetteEvaluator();
        $samples = [
            [1.0, 1.0], [1.1, 0.9],
            [10.0, 10.0], [10.1, 9.9],
        ];
        $assignments = [0, 0, 1, 1];

        $score = $evaluator->score($samples, $assignments);
        $this->assertGreaterThan(0.70, $score);
    }

    public function test_pca_projects_multidimensional_data_to_2d_and_3d(): void
    {
        $pca = new PcaTransformer();
        $samples = [
            [1.0, 2.0, 3.0, 4.0],
            [2.0, 4.0, 6.0, 8.0],
            [1.5, 3.0, 4.5, 6.0],
            [5.0, 1.0, 2.0, 3.0],
        ];

        $projected2D = $pca->project($samples, dimensions: 2);
        $this->assertCount(4, $projected2D);
        $this->assertCount(2, $projected2D[0]);

        $projected3D = $pca->project($samples, dimensions: 3);
        $this->assertCount(3, $projected3D[0]);
    }
}
```

- [ ] **Step 2: Run test to confirm failure**
Run: `docker exec laravel_app php artisan test tests/Unit/Harness/MathematicalAnalyticsTest.php`

- [ ] **Step 3: Implement KMeansClusterer, SilhouetteEvaluator, and PcaTransformer**
Implement Euclidean vector mathematics, mean centering, power iteration for principal eigenvectors, and silhouette scoring.

- [ ] **Step 4: Run test to confirm passing**
Run: `docker exec laravel_app php artisan test tests/Unit/Harness/MathematicalAnalyticsTest.php`

- [ ] **Step 5: Commit**
`git add app/Services/Harness/KMeansClusterer.php app/Services/Harness/SilhouetteEvaluator.php app/Services/Harness/PcaTransformer.php tests/Unit/Harness/MathematicalAnalyticsTest.php`
`git commit -m "feat(harness): implement kmeans clustering, silhouette evaluator, and pca transformer"`

---

### Task 3: Theory Reconciliation Verifier (`TheoryReconciliationService`)

**Files:**
- Create: `app/Services/Harness/TheoryReconciliationService.php`
- Test: `tests/Unit/Harness/TheoryReconciliationServiceTest.php`

**Interfaces:**
- `TheoryReconciliationService::verifyCausalLoopPolarity(array $loopEdges): bool`
- `TheoryReconciliationService::verifyVesterClassification(int $active, int $passive, float $avgActive, float $avgPassive): string`
- `TheoryReconciliationService::verifyScenarioOrthogonality(array $axis1Scores, array $axis2Scores, float $maxCorrelation = 0.70): bool`
- `TheoryReconciliationService::verifyClusterStability(array $dataset, float $minSilhouette = 0.50, float $resampleRatio = 0.20): array`

- [ ] **Step 1: Write failing unit test for `TheoryReconciliationService`**

```php
// tests/Unit/Harness/TheoryReconciliationServiceTest.php
namespace Tests\Unit\Harness;

use App\Services\Harness\TheoryReconciliationService;
use Tests\TestCase;

class TheoryReconciliationServiceTest extends TestCase
{
    public function test_reconciles_causal_loop_polarity(): void
    {
        $verifier = new TheoryReconciliationService();
        
        // Even negative edges = Reinforcing (R)
        $this->assertSame('R', $verifier->reconcileLoopPolarity([-1, -2, 3]));
        // Odd negative edges = Balancing (B)
        $this->assertSame('B', $verifier->reconcileLoopPolarity([-1, 2, 3]));
    }

    public function test_reconciles_vester_archetype_boundaries(): void
    {
        $verifier = new TheoryReconciliationService();
        $avg = 10.0;

        $this->assertSame('Kritis', $verifier->reconcileVester(15.0, 12.0, $avg, $avg));
        $this->assertSame('Pendorong', $verifier->reconcileVester(15.0, 8.0, $avg, $avg));
        $this->assertSame('Indikator', $verifier->reconcileVester(8.0, 12.0, $avg, $avg));
        $this->assertSame('Penyangga', $verifier->reconcileVester(8.0, 8.0, $avg, $avg));
    }

    public function test_reconciles_scenario_axis_orthogonality(): void
    {
        $verifier = new TheoryReconciliationService();

        $uncorrelatedA = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $uncorrelatedB = [5, 4, 6, 3, 7, 2, 8, 1, 9, 2];
        $this->assertTrue($verifier->verifyScenarioOrthogonality($uncorrelatedA, $uncorrelatedB));

        $collinearA = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $collinearB = [2, 4, 6, 8, 10, 12, 14, 16, 18, 20];
        $this->assertFalse($verifier->verifyScenarioOrthogonality($collinearA, $collinearB));
    }
}
```

- [ ] **Step 2: Run test to confirm failure**
Run: `docker exec laravel_app php artisan test tests/Unit/Harness/TheoryReconciliationServiceTest.php`

- [ ] **Step 3: Implement `TheoryReconciliationService`**
Code domain invariant checkers based on System Dynamics and Vester criteria defined in `docs/product/REASONING_ENGINE.md`.

- [ ] **Step 4: Run test to confirm passing**
Run: `docker exec laravel_app php artisan test tests/Unit/Harness/TheoryReconciliationServiceTest.php`

- [ ] **Step 5: Commit**
`git add app/Services/Harness/TheoryReconciliationService.php tests/Unit/Harness/TheoryReconciliationServiceTest.php`
`git commit -m "feat(harness): implement theory reconciliation domain verifier"`

---

### Task 4: Integration Benchmark Suite & Artisan Command (`artisan harness:eval-clustering`)

**Files:**
- Create: `app/Console/Commands/EvaluateClusteringHarness.php`
- Create: `tests/Feature/Harness/SyntheticClusteringBenchmarkTest.php`
- Modify: `app/Console/Kernel.php` (register command if needed)

- [ ] **Step 1: Write integration feature benchmark test**

```php
// tests/Feature/Harness/SyntheticClusteringBenchmarkTest.php
namespace Tests\Feature\Harness;

use App\Services\Harness\KMeansClusterer;
use App\Services\Harness\PcaTransformer;
use App\Services\Harness\SilhouetteEvaluator;
use App\Services\Harness\SyntheticForesightGenerator;
use App\Services\Harness\TheoryReconciliationService;
use Tests\TestCase;

class SyntheticClusteringBenchmarkTest extends TestCase
{
    public function test_synthetic_foresight_dataset_meets_clustering_quality_gate(): void
    {
        $generator = new SyntheticForesightGenerator();
        $dataset = $generator->generate(count: 150, seed: 20261022);

        // Extract feature matrix: [impact, uncertainty, horizon_weight, action_weight]
        $samples = array_map(function ($f) {
            $horizonWeight = match ($f['time_horizon']) {
                'Short Term' => 1.0,
                'Mid Term' => 0.75,
                default => 0.5,
            };
            $actionWeight = $f['status_action'] === 'decided_plan' ? 1.0 : 0.0;
            return [(float) $f['impact'], (float) $f['uncertainty'], $horizonWeight, $actionWeight];
        }, $dataset['forces']);

        $clusterer = new KMeansClusterer();
        $clustering = $clusterer->cluster($samples, k: 3, seed: 42);

        $evaluator = new SilhouetteEvaluator();
        $silhouette = $evaluator->score($samples, $clustering['assignments']);

        // Assert cluster quality complies with docs/product/REASONING_ENGINE.md threshold
        $this->assertGreaterThanOrEqual(0.40, $silhouette);

        // Verify PCA projection preserves sample count
        $pca = new PcaTransformer();
        $coords2D = $pca->project($samples, 2);
        $coords3D = $pca->project($samples, 3);
        $this->assertCount(150, $coords2D);
        $this->assertCount(150, $coords3D);
    }
}
```

- [ ] **Step 2: Run test to confirm failure**
Run: `docker exec laravel_app php artisan test tests/Feature/Harness/SyntheticClusteringBenchmarkTest.php`

- [ ] **Step 3: Implement `EvaluateClusteringHarness` command**
Create command `harness:eval-clustering` taking `--count=150`, `--seed=42`, and displaying an ASCII summary table with:
- Generated forces count and distribution.
- K-Means clusters and centroids.
- Average Silhouette score and quality gate pass/fail.
- PCA eigenvalue variance explained.
- Theory reconciliation checks summary.

- [ ] **Step 4: Run test and command to verify passing**
Run: `docker exec laravel_app php artisan test tests/Feature/Harness/SyntheticClusteringBenchmarkTest.php`
Run: `docker exec laravel_app php artisan harness:eval-clustering`

- [ ] **Step 5: Commit**
`git add app/Console/Commands/EvaluateClusteringHarness.php tests/Feature/Harness/SyntheticClusteringBenchmarkTest.php`
`git commit -m "feat(harness): add clustering benchmark command and end-to-end evaluation suite"`

---

### Task 5: Full Quality Gate Verification & Ledger Updates

**Files:**
- Modify: `feature_list.json` (register FEAT-018: Synthetic Data & Theory Reconciliation Harness)
- Modify: `PROGRESS.md` (record session execution and verification evidence)

- [ ] **Step 1: Run Pint code style fixer**
Run: `docker exec laravel_app composer pint`

- [ ] **Step 2: Run Larastan Level 5 static analysis**
Run: `docker exec laravel_app composer phpstan`

- [ ] **Step 3: Run Full Test Suite**
Run: `docker exec laravel_app php artisan test`

- [ ] **Step 4: Run Vite Frontend Build & NPM Audit**
Run: `npm run build && npm audit --audit-level=critical`

- [ ] **Step 5: Update `feature_list.json` and `PROGRESS.md`**
Register FEAT-018 as `passing`, update verification history, session log, and DoD checklist.

- [ ] **Step 6: Commit**
`git add feature_list.json PROGRESS.md`
`git commit -m "docs(harness): record FEAT-018 synthetic clustering and theory reconciliation harness"`

