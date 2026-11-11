<?php

namespace App\Support;

/**
 * Audit U4 (AUD-04): whether a numeric observation crosses an indicator's threshold. Pure: no
 * database, no client input. Null means "no decision" (no numeric rule, or no numeric value).
 */
final class ThresholdEvaluator
{
    public const OPERATORS = ['gt' => '>', 'gte' => '≥', 'lt' => '<', 'lte' => '≤'];

    public static function isNumeric(?string $operator, ?float $threshold): bool
    {
        return $operator !== null && isset(self::OPERATORS[$operator]) && $threshold !== null;
    }

    public static function crosses(?string $operator, ?float $threshold, ?float $value): ?bool
    {
        if (! self::isNumeric($operator, $threshold) || $value === null) {
            return null;
        }

        return match ($operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            default => null,
        };
    }

    /** The rule as people read it, e.g. "> 10". */
    public static function describe(?string $operator, ?float $threshold): ?string
    {
        if (! self::isNumeric($operator, $threshold)) {
            return null;
        }

        return self::OPERATORS[$operator].' '.rtrim(rtrim(number_format((float) $threshold, 6, '.', ''), '0'), '.');
    }
}
