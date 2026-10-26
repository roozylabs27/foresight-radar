<?php

declare(strict_types=1);

namespace App\Services\Harness;

use DateTimeImmutable;
use DateTimeInterface;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

/**
 * Deterministic synthetic foresight data generator for test harnesses and ML clustering evaluations.
 */
class SyntheticForesightGenerator
{
    private const MACRO_DIMENSIONS = ['Economy', 'Ecology', 'Regulation', 'Technology'];

    private const MEZZO_DIMENSIONS = ['Customer', 'Competitor', 'Supplier', 'Substitute'];

    private const HORIZONS = ['Short Term', 'Mid Term', 'Long Term'];

    private const ARCHETYPES = ['catalyst', 'wildcard', 'latent_anchor'];

    private Randomizer $randomizer;

    /**
     * Generate synthetic forces and directed cross-impact edges with reproducible PRNG.
     *
     * @return array{
     *     forces: list<array{
     *         id: string,
     *         name: string,
     *         dimension: string,
     *         time_horizon: string,
     *         impact: int,
     *         uncertainty: int,
     *         status_action: string,
     *         archetype: string
     *     }>,
     *     cross_impacts: list<array{
     *         from_id: string,
     *         to_id: string,
     *         score: int
     *     }>,
     *     metadata: array{
     *         count: int,
     *         seed: int,
     *         generated_at: string
     *     }
     * }
     */
    public function generate(int $count = 150, int $seed = 42): array
    {
        $this->randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));

        $dimensions = array_merge(self::MACRO_DIMENSIONS, self::MEZZO_DIMENSIONS);
        $forces = [];

        for ($i = 1; $i <= $count; $i++) {
            $archetypeIndex = ($i - 1) % count(self::ARCHETYPES);
            $archetype = self::ARCHETYPES[$archetypeIndex];
            $dimension = $dimensions[$this->randomizer->getInt(0, count($dimensions) - 1)];

            $profile = match ($archetype) {
                'catalyst' => [
                    'impact' => $this->randomizer->getInt(7, 10),
                    'uncertainty' => $this->randomizer->getInt(1, 4),
                    'time_horizon' => $this->randomChoice(['Short Term', 'Short Term', 'Mid Term']),
                    'status_action' => $this->randomChoice(['decided_plan', 'decided_plan', 'monitoring']),
                ],
                'wildcard' => [
                    'impact' => $this->randomizer->getInt(8, 10),
                    'uncertainty' => $this->randomizer->getInt(6, 10),
                    'time_horizon' => $this->randomChoice(['Mid Term', 'Long Term', 'Long Term']),
                    'status_action' => $this->randomChoice(['monitoring', 'monitoring', 'decided_plan']),
                ],
                'latent_anchor' => [
                    'impact' => $this->randomizer->getInt(2, 6),
                    'uncertainty' => $this->randomizer->getInt(2, 6),
                    'time_horizon' => self::HORIZONS[$this->randomizer->getInt(0, count(self::HORIZONS) - 1)],
                    'status_action' => $this->randomChoice(['monitoring', 'decided_plan']),
                ],
            };

            $forces[] = [
                'id' => sprintf('syn-df-%04d', $i),
                'name' => sprintf('Synthetic Force #%d: %s %s', $i, $dimension, ucfirst($archetype)),
                'dimension' => $dimension,
                'time_horizon' => $profile['time_horizon'],
                'impact' => $profile['impact'],
                'uncertainty' => $profile['uncertainty'],
                'status_action' => $profile['status_action'],
                'archetype' => $archetype,
            ];
        }

        // Generate cross impact relationships
        $crossImpacts = [];
        $forceCount = count($forces);

        if ($forceCount >= 2) {
            $maxEdges = min($forceCount * 3, 200);
            $scores = [-3, -2, -1, 1, 2, 3];

            $seenPairs = [];
            for ($k = 0; $k < $maxEdges; $k++) {
                $fromIdx = $this->randomizer->getInt(0, $forceCount - 1);
                $toIdx = $this->randomizer->getInt(0, $forceCount - 1);

                if ($fromIdx === $toIdx) {
                    continue;
                }

                $pairKey = $fromIdx.'->'.$toIdx;
                if (isset($seenPairs[$pairKey])) {
                    continue;
                }
                $seenPairs[$pairKey] = true;

                $crossImpacts[] = [
                    'from_id' => $forces[$fromIdx]['id'],
                    'to_id' => $forces[$toIdx]['id'],
                    'score' => $scores[$this->randomizer->getInt(0, count($scores) - 1)],
                ];
            }
        }

        return [
            'forces' => $forces,
            'cross_impacts' => $crossImpacts,
            'metadata' => [
                'count' => $count,
                'seed' => $seed,
                'generated_at' => (new DateTimeImmutable)->format(DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @param  list<string>  $choices
     */
    private function randomChoice(array $choices): string
    {
        $idx = $this->randomizer->getInt(0, count($choices) - 1);

        return $choices[$idx];
    }
}
