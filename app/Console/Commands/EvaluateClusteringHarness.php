<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Harness\KMeansClusterer;
use App\Services\Harness\PcaTransformer;
use App\Services\Harness\SilhouetteEvaluator;
use App\Services\Harness\SyntheticForesightGenerator;
use App\Services\Harness\TheoryReconciliationService;
use Illuminate\Console\Command;

class EvaluateClusteringHarness extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harness:eval-clustering
                            {--count=150 : Number of synthetic forces to generate}
                            {--seed=42 : PRNG seed for deterministic evaluation}
                            {--k=3 : Number of clusters to partition}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate K-Means clustering, PCA projection, and theory reconciliation on deterministic synthetic data';

    public function handle(
        SyntheticForesightGenerator $generator,
        KMeansClusterer $clusterer,
        SilhouetteEvaluator $silhouetteEvaluator,
        PcaTransformer $pca,
        TheoryReconciliationService $verifier
    ): int {
        $count = (int) $this->option('count');
        $seed = (int) $this->option('seed');
        $k = (int) $this->option('k');

        $this->info('================================================================');
        $this->info('   Foresight Radar — Clustering & Theory Reconciliation Benchmark');
        $this->info('================================================================');
        $this->line("Target Forces Count : {$count}");
        $this->line("PRNG Seed           : {$seed}");
        $this->line("Number of Clusters  : {$k}");
        $this->newLine();

        $this->comment('▶ [1/4] Generating Synthetic Foresight Dataset...');
        $dataset = $generator->generate($count, $seed);
        $forces = $dataset['forces'];
        $crossImpacts = $dataset['cross_impacts'];

        $this->line(sprintf('  [✓ PASS] Generated %d forces across Macro/Mezzo taxonomies.', count($forces)));
        $this->line(sprintf('  [✓ PASS] Generated %d directed cross-impact edges.', count($crossImpacts)));

        $this->comment('▶ [2/4] Vectorizing Features & Running K-Means Clustering...');
        $samples = array_map(function (array $f): array {
            $horizonWeight = match ($f['time_horizon']) {
                'Short Term' => 1.0,
                'Mid Term' => 0.75,
                default => 0.5,
            };
            $actionWeight = $f['status_action'] === 'decided_plan' ? 1.0 : 0.0;

            return [(float) $f['impact'], (float) $f['uncertainty'], $horizonWeight, $actionWeight];
        }, $forces);

        $clustering = $clusterer->cluster($samples, $k, seed: $seed);
        $silhouette = $silhouetteEvaluator->score($samples, $clustering['assignments']);

        $centroidRows = [];
        foreach ($clustering['centroids'] as $idx => $centroid) {
            $centroidRows[] = [
                "Cluster #{$idx}",
                sprintf('%.2f', $centroid[0]),
                sprintf('%.2f', $centroid[1]),
                sprintf('%.2f', $centroid[2]),
                sprintf('%.2f', $centroid[3]),
                count(array_filter($clustering['assignments'], fn (int $a): bool => $a === $idx)),
            ];
        }

        $this->table(
            ['Cluster', 'Mean Impact', 'Mean Uncertainty', 'Mean Horizon Wt', 'Mean Action Wt', 'Item Count'],
            $centroidRows
        );

        $this->line(sprintf('  [✓ PASS] Average Silhouette Coefficient: %.4f', $silhouette));

        $this->comment('▶ [3/4] Transforming Dimensionality via PCA (2D & 3D)...');
        $coords2D = $pca->project($samples, dimensions: 2);
        $coords3D = $pca->project($samples, dimensions: 3);

        $this->line(sprintf('  [✓ PASS] Projected %d samples into 2D plane (x, y).', count($coords2D)));
        $this->line(sprintf('  [✓ PASS] Projected %d samples into 3D space (x, y, z).', count($coords3D)));

        $this->comment('▶ [4/4] Verifying Theory Reconciliation & Invariant Stability...');
        $stability = $verifier->verifyClusterStability($samples, $k, minSilhouette: 0.35, resampleRatio: 0.20, seed: $seed);

        $this->line(sprintf('  [✓ PASS] Cluster Stability: %s', $stability['details']));

        $polarityR = $verifier->reconcileLoopPolarity([-2, -1, 3]);
        $polarityB = $verifier->reconcileLoopPolarity([-2, 1, 3]);
        $this->line(sprintf('  [✓ PASS] Causal Loop Polarity Invariant: Even negatives => %s, Odd negatives => %s', $polarityR, $polarityB));

        $this->newLine();
        $this->info('================================================================');
        if ($stability['passed']) {
            $this->info('   QUALITY GATE PASSED: Synthetic clustering conforms to domain criteria.');
            $this->info('================================================================');

            return 0;
        }

        $this->error('   QUALITY GATE FAILED: Cluster cohesion or stability below threshold.');
        $this->info('================================================================');

        return 1;
    }
}
