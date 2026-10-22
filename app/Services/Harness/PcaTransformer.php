<?php

declare(strict_types=1);

namespace App\Services\Harness;

use InvalidArgumentException;

class PcaTransformer
{
    /**
     * Project multidimensional samples into lower-dimensional space (e.g. 2D or 3D) using Principal Component Analysis.
     *
     * @param  list<list<float>>  $samples  N x D matrix
     * @param  int  $dimensions  Target dimensions (e.g. 2 or 3)
     * @return list<list<float>> N x dimensions projected coordinates
     */
    public function project(array $samples, int $dimensions = 2): array
    {
        $n = count($samples);
        if ($n === 0) {
            throw new InvalidArgumentException('Samples cannot be empty');
        }

        $d = count($samples[0]);
        if ($dimensions < 1 || $dimensions > $d) {
            throw new InvalidArgumentException("Dimensions must be between 1 and {$d}");
        }

        // 1. Mean centering
        $means = array_fill(0, $d, 0.0);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $d; $j++) {
                $means[$j] += $samples[$i][$j];
            }
        }
        for ($j = 0; $j < $d; $j++) {
            $means[$j] /= $n;
        }

        $centered = [];
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $d; $j++) {
                $row[$j] = $samples[$i][$j] - $means[$j];
            }
            $centered[] = $row;
        }

        // 2. Compute Covariance Matrix (D x D)
        $denom = max(1, $n - 1);
        $cov = [];
        for ($j = 0; $j < $d; $j++) {
            $cov[$j] = array_fill(0, $d, 0.0);
            for ($k = 0; $k < $d; $k++) {
                $sum = 0.0;
                for ($i = 0; $i < $n; $i++) {
                    $sum += $centered[$i][$j] * $centered[$i][$k];
                }
                $cov[$j][$k] = $sum / $denom;
            }
        }

        // 3. Find top eigenvectors via Power Iteration with Hotelling deflation
        $eigenvectors = [];
        $covMatrix = $cov;

        for ($p = 0; $p < $dimensions; $p++) {
            // Initial unit vector
            $v = array_fill(0, $d, 1.0 / sqrt($d));

            for ($iter = 0; $iter < 60; $iter++) {
                // Matrix-vector multiplication: w = Cov * v
                $w = array_fill(0, $d, 0.0);
                for ($r = 0; $r < $d; $r++) {
                    for ($c = 0; $c < $d; $c++) {
                        $w[$r] += $covMatrix[$r][$c] * $v[$c];
                    }
                }

                // Vector norm
                $normSq = 0.0;
                for ($r = 0; $r < $d; $r++) {
                    $normSq += $w[$r] * $w[$r];
                }
                $norm = sqrt($normSq);

                if ($norm < 1e-12) {
                    break;
                }

                for ($r = 0; $r < $d; $r++) {
                    $v[$r] = $w[$r] / $norm;
                }
            }

            // Eigenvalue: lambda = v^T * Cov * v
            $lambda = 0.0;
            for ($r = 0; $r < $d; $r++) {
                $covV_r = 0.0;
                for ($c = 0; $c < $d; $c++) {
                    $covV_r += $covMatrix[$r][$c] * $v[$c];
                }
                $lambda += $v[$r] * $covV_r;
            }

            $eigenvectors[] = $v;

            // Deflation: Cov = Cov - lambda * (v * v^T)
            for ($r = 0; $r < $d; $r++) {
                for ($c = 0; $c < $d; $c++) {
                    $covMatrix[$r][$c] -= $lambda * $v[$r] * $v[$c];
                }
            }
        }

        // 4. Project centered data onto top eigenvectors: Y = X_c * V
        $projected = [];
        for ($i = 0; $i < $n; $i++) {
            $coords = [];
            for ($p = 0; $p < $dimensions; $p++) {
                $val = 0.0;
                for ($j = 0; $j < $d; $j++) {
                    $val += $centered[$i][$j] * $eigenvectors[$p][$j];
                }
                $coords[$p] = round($val, 4);
            }
            $projected[] = $coords;
        }

        return $projected;
    }
}
