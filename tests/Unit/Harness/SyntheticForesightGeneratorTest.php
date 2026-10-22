<?php

declare(strict_types=1);

namespace Tests\Unit\Harness;

use App\Services\Harness\SyntheticForesightGenerator;
use Tests\TestCase;

class SyntheticForesightGeneratorTest extends TestCase
{
    public function test_generates_exact_requested_count_with_reproducible_seed(): void
    {
        $generator = new SyntheticForesightGenerator;
        $datasetA = $generator->generate(count: 50, seed: 12345);
        $datasetB = $generator->generate(count: 50, seed: 12345);

        $this->assertCount(50, $datasetA['forces']);
        $this->assertCount(50, $datasetB['forces']);
        $this->assertSame($datasetA, $datasetB);
    }

    public function test_different_seeds_produce_different_distributions(): void
    {
        $generator = new SyntheticForesightGenerator;
        $datasetA = $generator->generate(count: 30, seed: 111);
        $datasetB = $generator->generate(count: 30, seed: 999);

        $this->assertNotSame($datasetA['forces'], $datasetB['forces']);
    }

    public function test_generated_attributes_satisfy_domain_boundaries(): void
    {
        $generator = new SyntheticForesightGenerator;
        $dataset = $generator->generate(count: 60, seed: 42);

        $validDimensions = [
            'Economy', 'Ecology', 'Regulation', 'Technology',
            'Customer', 'Competitor', 'Supplier', 'Substitute',
        ];
        $validHorizons = ['Short Term', 'Mid Term', 'Long Term'];
        $validActions = ['monitoring', 'decided_plan'];
        $validArchetypes = ['catalyst', 'wildcard', 'latent_anchor'];

        foreach ($dataset['forces'] as $force) {
            $this->assertNotEmpty($force['id']);
            $this->assertNotEmpty($force['name']);
            $this->assertContains($force['dimension'], $validDimensions);
            $this->assertContains($force['time_horizon'], $validHorizons);
            $this->assertContains($force['status_action'], $validActions);
            $this->assertContains($force['archetype'], $validArchetypes);

            $this->assertGreaterThanOrEqual(1, $force['impact']);
            $this->assertLessThanOrEqual(10, $force['impact']);
            $this->assertGreaterThanOrEqual(1, $force['uncertainty']);
            $this->assertLessThanOrEqual(10, $force['uncertainty']);
        }
    }

    public function test_generates_valid_cross_impact_edges(): void
    {
        $generator = new SyntheticForesightGenerator;
        $dataset = $generator->generate(count: 20, seed: 42);

        $this->assertArrayHasKey('cross_impacts', $dataset);
        $this->assertNotEmpty($dataset['cross_impacts']);

        $forceIds = array_column($dataset['forces'], 'id');

        foreach ($dataset['cross_impacts'] as $edge) {
            $this->assertContains($edge['from_id'], $forceIds);
            $this->assertContains($edge['to_id'], $forceIds);
            $this->assertNotSame($edge['from_id'], $edge['to_id']);
            $this->assertGreaterThanOrEqual(-3, $edge['score']);
            $this->assertLessThanOrEqual(3, $edge['score']);
            $this->assertNotEquals(0, $edge['score']);
        }
    }
}
