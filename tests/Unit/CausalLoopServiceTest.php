<?php

namespace Tests\Unit;

use App\Services\CausalLoopService;
use PHPUnit\Framework\TestCase;

class CausalLoopServiceTest extends TestCase
{
    private CausalLoopService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CausalLoopService();
    }

    public function test_detects_reinforcing_two_node_loop(): void
    {
        $nodeIds = ['A', 'B'];
        $edges = [
            ['source' => 'A', 'target' => 'B', 'score' => 2],
            ['source' => 'B', 'target' => 'A', 'score' => 3],
        ];

        $result = $this->service->detect($nodeIds, $edges);

        $this->assertFalse($result['truncated']);
        $this->assertCount(1, $result['loops']);
        $loop = $result['loops'][0];
        $this->assertEquals(['A', 'B'], $loop['nodes']);
        $this->assertEquals('R', $loop['polarity']);
        $this->assertEquals(2, $loop['length']);
        $this->assertGreaterThan(0.8, $loop['strength']);
    }

    public function test_detects_balancing_loop_with_odd_negative_edges(): void
    {
        $nodeIds = ['A', 'B', 'C'];
        $edges = [
            ['source' => 'A', 'target' => 'B', 'score' => 1],
            ['source' => 'B', 'target' => 'C', 'score' => -2],
            ['source' => 'C', 'target' => 'A', 'score' => 2],
        ];

        $result = $this->service->detect($nodeIds, $edges);

        $this->assertCount(1, $result['loops']);
        $this->assertEquals('B', $result['loops'][0]['polarity']);
        $this->assertEquals(3, $result['loops'][0]['length']);
    }

    public function test_ignores_zero_score_edges(): void
    {
        $nodeIds = ['A', 'B'];
        $edges = [
            ['source' => 'A', 'target' => 'B', 'score' => 0],
            ['source' => 'B', 'target' => 'A', 'score' => 2],
        ];

        $result = $this->service->detect($nodeIds, $edges);

        $this->assertEmpty($result['loops']);
    }
}
