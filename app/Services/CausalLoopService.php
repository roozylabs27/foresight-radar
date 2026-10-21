<?php

namespace App\Services;

class CausalLoopService
{
    public const MAX_LENGTH = 5;

    public const MAX_LOOPS = 300;

    public const MAX_STEPS = 200000;

    /**
     * Detect elementary causal loops in a directed signed graph.
     *
     * @param  array<int, string>  $nodeIds
     * @param  array<int, array{source: string, target: string, score: int}>  $edges
     * @return array{loops: array<int, array{nodes: array<int, string>, edges: array<int, array{source: string, target: string, score: int}>, polarity: string, length: int, strength: float}>, truncated: bool}
     */
    public function detect(array $nodeIds, array $edges): array
    {
        $nodeIndex = array_flip($nodeIds);
        $adj = [];
        foreach ($edges as $edge) {
            $s = $edge['source'] ?? null;
            $t = $edge['target'] ?? null;
            $score = (int) ($edge['score'] ?? 0);
            if ($score === 0 || ! isset($nodeIndex[$s]) || ! isset($nodeIndex[$t])) {
                continue;
            }
            $adj[$s][] = [
                'target' => $t,
                'score' => $score,
            ];
        }

        $loops = [];
        $truncated = false;
        $steps = 0;

        $n = count($nodeIds);
        for ($i = 0; $i < $n; $i++) {
            $startNode = $nodeIds[$i];
            $startIndex = $i;

            $stack = [
                [
                    'curr' => $startNode,
                    'path' => [$startNode],
                    'pathEdges' => [],
                    'visited' => [$startNode => true],
                ],
            ];

            while (! empty($stack)) {
                $frame = array_pop($stack);
                $curr = $frame['curr'];
                $path = $frame['path'];
                $pathEdges = $frame['pathEdges'];
                $visited = $frame['visited'];

                $steps++;
                if ($steps > self::MAX_STEPS || count($loops) >= self::MAX_LOOPS) {
                    $truncated = true;
                    break 2;
                }

                $neighbors = $adj[$curr] ?? [];
                foreach ($neighbors as $edge) {
                    $next = $edge['target'];
                    $score = $edge['score'];

                    if ($next === $startNode) {
                        $loopEdges = array_merge($pathEdges, [['source' => $curr, 'target' => $next, 'score' => $score]]);
                        $length = count($loopEdges);
                        if ($length >= 2 && $length <= self::MAX_LENGTH) {
                            $negativeCount = 0;
                            $productAbs = 1.0;
                            foreach ($loopEdges as $le) {
                                if ($le['score'] < 0) {
                                    $negativeCount++;
                                }
                                $productAbs *= abs($le['score']);
                            }
                            $polarity = ($negativeCount % 2 === 0) ? 'R' : 'B';
                            $strength = round(pow($productAbs, 1 / $length) / 3, 3);

                            $loops[] = [
                                'nodes' => $path,
                                'edges' => $loopEdges,
                                'polarity' => $polarity,
                                'length' => $length,
                                'strength' => $strength,
                            ];

                            if (count($loops) >= self::MAX_LOOPS) {
                                $truncated = true;
                                break 3;
                            }
                        }

                        continue;
                    }

                    if (isset($visited[$next])) {
                        continue;
                    }

                    // Only visit nodes with index > startIndex to avoid rotational duplicates
                    if (! isset($nodeIndex[$next]) || $nodeIndex[$next] <= $startIndex) {
                        continue;
                    }

                    if (count($path) < self::MAX_LENGTH) {
                        $nextVisited = $visited;
                        $nextVisited[$next] = true;
                        $stack[] = [
                            'curr' => $next,
                            'path' => array_merge($path, [$next]),
                            'pathEdges' => array_merge($pathEdges, [['source' => $curr, 'target' => $next, 'score' => $score]]),
                            'visited' => $nextVisited,
                        ];
                    }
                }
            }
        }

        // Sort: strength descending, then length ascending
        usort($loops, function ($a, $b) {
            return ($b['strength'] <=> $a['strength']) ?: ($a['length'] <=> $b['length']);
        });

        return [
            'count' => count($loops),
            'loops' => $loops,
            'truncated' => $truncated,
        ];
    }
}
