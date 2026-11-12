<?php

namespace Tests\Unit\Support;

use App\Support\ThresholdEvaluator;
use PHPUnit\Framework\TestCase;

/** Audit U4 (AUD-04): the server decides a numeric threshold crossing, by operator and value. */
class ThresholdEvaluatorTest extends TestCase
{
    public function test_each_operator_and_its_boundary(): void
    {
        $cases = [
            ['gt', 10.0, 10.5, true], ['gt', 10.0, 10.0, false], ['gt', 10.0, 9.0, false],
            ['gte', 10.0, 10.0, true], ['gte', 10.0, 9.99, false],
            ['lt', 5.0, 4.0, true], ['lt', 5.0, 5.0, false], ['lt', 5.0, 6.0, false],
            ['lte', 5.0, 5.0, true], ['lte', 5.0, 5.01, false],
            ['gt', -1.5, -1.0, true], ['lt', 0.0, -0.1, true],
        ];
        foreach ($cases as [$operator, $threshold, $value, $expected]) {
            $this->assertSame($expected, ThresholdEvaluator::crosses($operator, $threshold, $value), "{$value} {$operator} {$threshold}");
        }
    }

    public function test_no_decision_without_a_numeric_rule_or_value(): void
    {
        $this->assertNull(ThresholdEvaluator::crosses(null, 10.0, 12.0));
        $this->assertNull(ThresholdEvaluator::crosses('gt', null, 12.0));
        $this->assertNull(ThresholdEvaluator::crosses('gt', 10.0, null));
        $this->assertNull(ThresholdEvaluator::crosses('between', 10.0, 12.0));
        $this->assertFalse(ThresholdEvaluator::isNumeric(null, 10.0));
        $this->assertTrue(ThresholdEvaluator::isNumeric('lte', 0.0));
    }

    public function test_the_rule_reads_as_text(): void
    {
        $this->assertSame('> 10', ThresholdEvaluator::describe('gt', 10.0));
        $this->assertSame('≤ 2.5', ThresholdEvaluator::describe('lte', 2.5));
        $this->assertNull(ThresholdEvaluator::describe(null, null));
    }
}
