<?php

declare(strict_types=1);

namespace App\Services\Harness;

class SilhouetteEvaluator
{
    /**
     * Compute the mean Silhouette Coefficient for a clustering assignment.
     * Returns a float in [-1.0, 1.0]. A score >= 0.50 indicates strong cohesion.
     *
     * @param  list<list<float>>  $samples
     * @param  list<int>  $assignments
     */
    public function score(array $samples, array $assignments): float
    {
        $n = count($samples);
        if ($n <= 1) {
            return 0.0;
        }

        $clusters = [];
        for ($i = 0; $i < $n; $i++) {
            $clusters[$assignments[$i]][] = $i;
        }

        // If all samples belong to one cluster, silhouette is defined as 0.0
        if (count($clusters) <= 1) {
            return 0.0;
        }

        $silhouetteSum = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $ownCluster = $assignments[$i];
            $ownClusterIndices = $clusters[$ownCluster];

            // 1. Calculate a(i): mean intra-cluster distance
            if (count($ownClusterIndices) <= 1) {
                continue;
            }

            $intraSum = 0.0;
            foreach ($ownClusterIndices as $idx) {
                if ($idx !== $i) {
                    $intraSum += $this->euclideanDistance($samples[$i], $samples[$idx]);
                }
            }
            $a_i = $intraSum / (count($ownClusterIndices) - 1);

            // 2. Calculate b(i): minimum mean distance to any other cluster
            $b_i = PHP_FLOAT_MAX;
            foreach ($clusters as $cId => $otherIndices) {
                if ($cId === $ownCluster) {
                    continue;
                }

                $interSum = 0.0;
                foreach ($otherIndices as $idx) {
                    $interSum += $this->euclideanDistance($samples[$i], $samples[$idx]);
                }
                $meanDist = $interSum / count($otherIndices);
                if ($meanDist < $b_i) {
                    $b_i = $meanDist;
                }
            }

            $maxDenom = max($a_i, $b_i);
            $s_i = $maxDenom > 0.0 ? ($b_i - $a_i) / $maxDenom : 0.0;
            $silhouetteSum += $s_i;
        }

        return round($silhouetteSum / $n, 4);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $count = count($a);
        for ($i = 0; $i < $count; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
