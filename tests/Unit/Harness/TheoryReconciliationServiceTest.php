<?php

declare(strict_types=1);

namespace Tests\Unit\Harness;

use App\Services\Harness\TheoryReconciliationService;
use Tests\TestCase;

class TheoryReconciliationServiceTest extends TestCase
{
    public function test_reconciles_causal_loop_polarity(): void
    {
        $verifier = new TheoryReconciliationService;

        // Even negative edges = Reinforcing (R)
        $this->assertSame('R', $verifier->reconcileLoopPolarity([-1, -2, 3]));
        $this->assertSame('R', $verifier->reconcileLoopPolarity([1, 2, 3]));
        $this->assertSame('R', $verifier->reconcileLoopPolarity([-1, -2, -3, -1]));

        // Odd negative edges = Balancing (B)
        $this->assertSame('B', $verifier->reconcileLoopPolarity([-1, 2, 3]));
        $this->assertSame('B', $verifier->reconcileLoopPolarity([-1, -2, -3]));
    }

    public function test_reconciles_vester_archetype_boundaries(): void
    {
        $verifier = new TheoryReconciliationService;
        $avg = 10.0;

        $this->assertSame('Kritis', $verifier->reconcileVester(15.0, 12.0, $avg, $avg));
        $this->assertSame('Pendorong', $verifier->reconcileVester(15.0, 8.0, $avg, $avg));
        $this->assertSame('Indikator', $verifier->reconcileVester(8.0, 12.0, $avg, $avg));
        $this->assertSame('Penyangga', $verifier->reconcileVester(8.0, 8.0, $avg, $avg));
    }

    public function test_reconciles_scenario_axis_orthogonality(): void
    {
        $verifier = new TheoryReconciliationService;

        $uncorrelatedA = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0];
        $uncorrelatedB = [5.0, 4.0, 6.0, 3.0, 7.0, 2.0, 8.0, 1.0, 9.0, 2.0];
        $this->assertTrue($verifier->verifyScenarioOrthogonality($uncorrelatedA, $uncorrelatedB, 0.70));

        $collinearA = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0];
        $collinearB = [2.0, 4.0, 6.0, 8.0, 10.0, 12.0, 14.0, 16.0, 18.0, 20.0];
        $this->assertFalse($verifier->verifyScenarioOrthogonality($collinearA, $collinearB, 0.70));
    }

    public function test_verifies_cluster_stability_evaluation(): void
    {
        $verifier = new TheoryReconciliationService;

        $wellSeparatedSamples = [
            [1.0, 1.0], [1.1, 0.9], [0.9, 1.1], [1.2, 0.8], [0.8, 1.2],
            [10.0, 10.0], [10.1, 9.9], [9.9, 10.1], [10.2, 9.8], [9.8, 10.2],
            [20.0, 20.0], [20.1, 19.9], [19.9, 20.1], [20.2, 19.8], [19.8, 20.2],
        ];

        $evaluation = $verifier->verifyClusterStability($wellSeparatedSamples, k: 3, minSilhouette: 0.50);

        $this->assertTrue($evaluation['passed']);
        $this->assertGreaterThanOrEqual(0.50, $evaluation['silhouette']);
        $this->assertGreaterThanOrEqual(0.40, $evaluation['resample_silhouette']);
    }
}
