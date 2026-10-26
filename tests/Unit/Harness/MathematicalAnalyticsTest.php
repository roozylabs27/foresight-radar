<?php

declare(strict_types=1);

namespace Tests\Unit\Harness;

use App\Services\Harness\KMeansClusterer;
use App\Services\Harness\PcaTransformer;
use App\Services\Harness\SilhouetteEvaluator;
use Tests\TestCase;

class MathematicalAnalyticsTest extends TestCase
{
    public function test_kmeans_separates_well_defined_clusters(): void
    {
        $clusterer = new KMeansClusterer;
        $samples = [
            [1.0, 1.0], [1.1, 0.9], [0.9, 1.2], // Cluster 0
            [8.0, 8.0], [8.2, 7.9], [7.8, 8.1], // Cluster 1
        ];

        $result = $clusterer->cluster($samples, k: 2, seed: 42);

        $this->assertCount(2, $result['centroids']);
        $this->assertCount(6, $result['assignments']);
        // Same cluster points must share assignment
        $this->assertSame($result['assignments'][0], $result['assignments'][1]);
        $this->assertSame($result['assignments'][0], $result['assignments'][2]);
        $this->assertSame($result['assignments'][3], $result['assignments'][4]);
        $this->assertSame($result['assignments'][3], $result['assignments'][5]);
        // Different clusters must have different assignments
        $this->assertNotSame($result['assignments'][0], $result['assignments'][3]);
    }

    public function test_kmeans_handles_k_equals_sample_count(): void
    {
        $clusterer = new KMeansClusterer;
        $samples = [
            [1.0, 2.0],
            [3.0, 4.0],
            [5.0, 6.0],
        ];

        $result = $clusterer->cluster($samples, k: 3, seed: 42);

        $this->assertCount(3, $result['centroids']);
        $uniqueAssignments = array_unique($result['assignments']);
        $this->assertCount(3, $uniqueAssignments);
    }

    public function test_silhouette_evaluator_evaluates_cluster_cohesion(): void
    {
        $evaluator = new SilhouetteEvaluator;
        $samples = [
            [1.0, 1.0], [1.1, 0.9], [0.9, 1.1],
            [10.0, 10.0], [10.1, 9.9], [9.9, 10.1],
        ];
        $assignments = [0, 0, 0, 1, 1, 1];

        $score = $evaluator->score($samples, $assignments);

        $this->assertGreaterThan(0.80, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    public function test_silhouette_returns_zero_for_single_cluster(): void
    {
        $evaluator = new SilhouetteEvaluator;
        $samples = [
            [1.0, 1.0], [1.1, 0.9],
        ];
        $assignments = [0, 0];

        $score = $evaluator->score($samples, $assignments);

        $this->assertSame(0.0, $score);
    }

    public function test_pca_projects_multidimensional_data_to_2d_and_3d(): void
    {
        $pca = new PcaTransformer;
        $samples = [
            [1.0, 2.0, 3.0, 4.0],
            [2.0, 4.0, 6.0, 8.0],
            [1.5, 3.0, 4.5, 6.0],
            [5.0, 1.0, 2.0, 3.0],
            [8.0, 2.0, 1.0, 4.0],
        ];

        $projected2D = $pca->project($samples, dimensions: 2);
        $this->assertCount(5, $projected2D);
        $this->assertCount(2, $projected2D[0]);

        $projected3D = $pca->project($samples, dimensions: 3);
        $this->assertCount(5, $projected3D);
        $this->assertCount(3, $projected3D[0]);
    }
}
