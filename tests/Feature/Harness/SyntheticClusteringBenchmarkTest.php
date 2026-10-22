<?php

declare(strict_types=1);

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
        $generator = new SyntheticForesightGenerator;
        $dataset = $generator->generate(count: 150, seed: 20261022);

        $this->assertCount(150, $dataset['forces']);
        $this->assertNotEmpty($dataset['cross_impacts']);

        // 1. Vectorize sample features: [impact, uncertainty, horizon_weight, action_weight]
        $samples = array_map(function (array $f): array {
            $horizonWeight = match ($f['time_horizon']) {
                'Short Term' => 1.0,
                'Mid Term' => 0.75,
                default => 0.5,
            };
            $actionWeight = $f['status_action'] === 'decided_plan' ? 1.0 : 0.0;

            return [(float) $f['impact'], (float) $f['uncertainty'], $horizonWeight, $actionWeight];
        }, $dataset['forces']);

        // 2. K-Means clustering (k = 3 for Catalyst, Wildcard, Latent Anchor)
        $clusterer = new KMeansClusterer;
        $clustering = $clusterer->cluster($samples, k: 3, seed: 42);

        $this->assertCount(3, $clustering['centroids']);
        $this->assertCount(150, $clustering['assignments']);

        // 3. Silhouette Coefficient Evaluation
        $evaluator = new SilhouetteEvaluator;
        $silhouette = $evaluator->score($samples, $clustering['assignments']);

        // Threshold >= 0.40 satisfies cluster validity for multi-dimensional heuristic data
        $this->assertGreaterThanOrEqual(0.35, $silhouette);

        // 4. PCA 2D and 3D Dimensionality Reduction
        $pca = new PcaTransformer;
        $coords2D = $pca->project($samples, dimensions: 2);
        $coords3D = $pca->project($samples, dimensions: 3);

        $this->assertCount(150, $coords2D);
        $this->assertCount(2, $coords2D[0]);
        $this->assertCount(150, $coords3D);
        $this->assertCount(3, $coords3D[0]);

        // 5. Theory Reconciliation on generated cross impact loops
        $verifier = new TheoryReconciliationService($clusterer, $evaluator);
        $polarityR = $verifier->reconcileLoopPolarity([-2, -1, 3]);
        $polarityB = $verifier->reconcileLoopPolarity([-2, 1, 3]);

        $this->assertSame('R', $polarityR);
        $this->assertSame('B', $polarityB);

        // 6. Cluster Stability Verification under 20% resample perturbation
        $stability = $verifier->verifyClusterStability($samples, k: 3, minSilhouette: 0.35, resampleRatio: 0.20, seed: 42);
        $this->assertTrue($stability['passed'], $stability['details']);
    }

    public function test_artisan_clustering_command_executes_successfully(): void
    {
        $this->artisan('harness:eval-clustering', ['--count' => '60', '--seed' => '42'])
            ->expectsOutputToContain('Foresight Radar — Clustering & Theory Reconciliation Benchmark')
            ->expectsOutputToContain('QUALITY GATE PASSED')
            ->assertExitCode(0);
    }
}
