<?php

declare(strict_types=1);

namespace App\Services\Harness;

use InvalidArgumentException;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

/**
 * Theory Reconciliation Service ensuring analytics outputs comply with strategic foresight literature
 * and System Dynamics domain invariants.
 */
class TheoryReconciliationService
{
    public function __construct(
        private readonly KMeansClusterer $clusterer = new KMeansClusterer,
        private readonly SilhouetteEvaluator $silhouetteEvaluator = new SilhouetteEvaluator
    ) {}

    /**
     * Reconcile causal loop polarity:
     * - Even number of negative influence edges = Reinforcing (R / Penguat)
     * - Odd number of negative influence edges = Balancing (B / Penyeimbang)
     *
     * @param  list<int|float>  $edgeScores
     */
    public function reconcileLoopPolarity(array $edgeScores): string
    {
        $negativeCount = 0;
        foreach ($edgeScores as $score) {
            if ($score < 0) {
                $negativeCount++;
            }
        }

        return ($negativeCount % 2 === 0) ? 'R' : 'B';
    }

    /**
     * Reconcile Vester matrix quadrant archetype boundaries:
     * - Active > avg, Passive > avg => Kritis
     * - Active > avg, Passive <= avg => Pendorong
     * - Active <= avg, Passive > avg => Indikator
     * - Active <= avg, Passive <= avg => Penyangga
     */
    public function reconcileVester(float $active, float $passive, float $avgActive, float $avgPassive): string
    {
        if ($active > $avgActive && $passive > $avgPassive) {
            return 'Kritis';
        }

        if ($active > $avgActive && $passive <= $avgPassive) {
            return 'Pendorong';
        }

        if ($active <= $avgActive && $passive > $avgPassive) {
            return 'Indikator';
        }

        return 'Penyangga';
    }

    /**
     * Verify scenario 2x2 axis orthogonality.
     * Scenario axes must be mutually independent (Pearson |r| <= maxCorrelation)
     * to avoid redundant or collapsed scenario quadrants.
     *
     * @param  list<float>  $axis1
     * @param  list<float>  $axis2
     */
    public function verifyScenarioOrthogonality(array $axis1, array $axis2, float $maxCorrelation = 0.70): bool
    {
        $n = count($axis1);
        if ($n !== count($axis2) || $n < 2) {
            throw new InvalidArgumentException('Both axes must contain at least 2 corresponding scores');
        }

        $mean1 = array_sum($axis1) / $n;
        $mean2 = array_sum($axis2) / $n;

        $covariance = 0.0;
        $var1 = 0.0;
        $var2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $diff1 = $axis1[$i] - $mean1;
            $diff2 = $axis2[$i] - $mean2;

            $covariance += $diff1 * $diff2;
            $var1 += $diff1 * $diff1;
            $var2 += $diff2 * $diff2;
        }

        $denom = sqrt($var1 * $var2);
        if ($denom < 1e-12) {
            // Invariant: constant series has zero correlation
            return true;
        }

        $r = $covariance / $denom;

        return abs($r) <= $maxCorrelation;
    }

    /**
     * Verify cluster stability on a dataset by computing silhouette on full dataset
     * and checking consistency when 20% of data is randomly resampled.
     *
     * @param  list<list<float>>  $samples
     * @return array{
     *     passed: bool,
     *     silhouette: float,
     *     resample_silhouette: float,
     *     details: string
     * }
     */
    public function verifyClusterStability(
        array $samples,
        int $k = 3,
        float $minSilhouette = 0.40,
        float $resampleRatio = 0.20,
        int $seed = 42
    ): array {
        $n = count($samples);
        if ($n < $k) {
            throw new InvalidArgumentException("Sample count ({$n}) must be >= k ({$k})");
        }

        // 1. Full clustering & Silhouette
        $fullClustering = $this->clusterer->cluster($samples, $k, seed: $seed);
        $fullScore = $this->silhouetteEvaluator->score($samples, $fullClustering['assignments']);

        // 2. Resampling (drop resampleRatio fraction)
        $keepCount = (int) max($k, round($n * (1.0 - $resampleRatio)));
        $randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));
        $indices = range(0, $n - 1);

        $selectedIndices = [];
        for ($i = 0; $i < $keepCount; $i++) {
            $pick = $randomizer->getInt(0, count($indices) - 1);
            $selectedIndices[] = $indices[$pick];
            array_splice($indices, $pick, 1);
        }

        $resampled = [];
        foreach ($selectedIndices as $idx) {
            $resampled[] = $samples[$idx];
        }

        $resampleClustering = $this->clusterer->cluster($resampled, $k, seed: $seed);
        $resampleScore = $this->silhouetteEvaluator->score($resampled, $resampleClustering['assignments']);

        $passed = ($fullScore >= $minSilhouette) && ($resampleScore >= ($minSilhouette * 0.75));

        return [
            'passed' => $passed,
            'silhouette' => $fullScore,
            'resample_silhouette' => $resampleScore,
            'details' => sprintf(
                'Full Silhouette: %.4f (threshold: %.2f), Resample Silhouette: %.4f',
                $fullScore,
                $minSilhouette,
                $resampleScore
            ),
        ];
    }
}
