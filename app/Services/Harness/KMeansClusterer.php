<?php

declare(strict_types=1);

namespace App\Services\Harness;

use InvalidArgumentException;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

class KMeansClusterer
{
    /**
     * Perform deterministic K-Means clustering over vector samples.
     *
     * @param  list<list<float>>  $samples
     * @return array{
     *     assignments: list<int>,
     *     centroids: list<list<float>>,
     *     iterations: int
     * }
     */
    public function cluster(array $samples, int $k = 3, int $maxIterations = 100, int $seed = 42): array
    {
        $n = count($samples);
        if ($n === 0) {
            throw new InvalidArgumentException('Samples cannot be empty');
        }
        if ($k < 1 || $k > $n) {
            throw new InvalidArgumentException("k must be between 1 and {$n}");
        }

        $d = count($samples[0]);
        $randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));

        // Deterministic centroid initialization: k distinct samples
        $indices = range(0, $n - 1);
        $selectedIndices = [];
        for ($i = 0; $i < $k; $i++) {
            $pick = $randomizer->getInt(0, count($indices) - 1);
            $selectedIndices[] = $indices[$pick];
            array_splice($indices, $pick, 1);
        }

        $centroids = [];
        foreach ($selectedIndices as $idx) {
            $centroids[] = $samples[$idx];
        }

        $assignments = array_fill(0, $n, -1);
        $iterations = 0;

        for ($iter = 0; $iter < $maxIterations; $iter++) {
            $iterations++;
            $changed = false;

            // Assignment step
            for ($i = 0; $i < $n; $i++) {
                $bestCluster = 0;
                $minDist = PHP_FLOAT_MAX;

                for ($c = 0; $c < $k; $c++) {
                    $dist = $this->squaredDistance($samples[$i], $centroids[$c]);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $bestCluster = $c;
                    }
                }

                if ($assignments[$i] !== $bestCluster) {
                    $assignments[$i] = $bestCluster;
                    $changed = true;
                }
            }

            if (! $changed && $iter > 0) {
                break;
            }

            // Update step
            $newCentroids = [];
            for ($c = 0; $c < $k; $c++) {
                $sum = array_fill(0, $d, 0.0);
                $count = 0;

                for ($i = 0; $i < $n; $i++) {
                    if ($assignments[$i] === $c) {
                        $count++;
                        for ($dim = 0; $dim < $d; $dim++) {
                            $sum[$dim] += $samples[$i][$dim];
                        }
                    }
                }

                if ($count > 0) {
                    for ($dim = 0; $dim < $d; $dim++) {
                        $sum[$dim] /= $count;
                    }
                    $newCentroids[] = $sum;
                } else {
                    $newCentroids[] = $centroids[$c];
                }
            }

            $centroids = $newCentroids;
        }

        return [
            'assignments' => $assignments,
            'centroids' => $centroids,
            'iterations' => $iterations,
        ];
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function squaredDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $count = count($a);
        for ($i = 0; $i < $count; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return $sum;
    }
}
