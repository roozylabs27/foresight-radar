<?php

namespace App\Enums;

use App\Models\TimeHorizon;

/**
 * Time horizon of a rating, keyed by the `code` column of time_horizons
 * (seeded in database/data/time_horizon.json).
 */
enum TimeHorizonType: string
{
    case SHORT_TERM = 'ST';
    case MID_TERM = 'MT';
    case LONG_TERM = 'LT';

    /**
     * Resolve from a TimeHorizon row by its code, falling back to the name.
     */
    public static function fromModel(?TimeHorizon $timeHorizon): ?self
    {
        if ($timeHorizon === null) {
            return null;
        }

        return self::tryFrom(strtoupper((string) $timeHorizon->code))
            ?? match (strtolower(trim((string) $timeHorizon->name))) {
                'short term' => self::SHORT_TERM,
                'mid term', 'medium term' => self::MID_TERM,
                'long term' => self::LONG_TERM,
                default => null,
            };
    }

    public function label(): string
    {
        return match ($this) {
            self::SHORT_TERM => 'Short Term',
            self::MID_TERM => 'Mid Term',
            self::LONG_TERM => 'Long Term',
        };
    }

    /**
     * Position of the horizon from the centre of the radar (0 = innermost).
     */
    public function band(): int
    {
        return match ($this) {
            self::SHORT_TERM => 0,
            self::MID_TERM => 1,
            self::LONG_TERM => 2,
        };
    }
}
