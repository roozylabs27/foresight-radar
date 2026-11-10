<?php

namespace App\Services\IndicatorSources;

/**
 * An external data feed for early warning indicators (roadmap R3.6). A connector reads one series for
 * one area and returns its points; it throws IndicatorSourceException with a readable reason.
 */
interface IndicatorSource
{
    /**
     * @return list<array{period: string, date: string, value: float}> points with a value, any order
     *
     * @throws IndicatorSourceException
     */
    public function fetch(string $series, string $area): array;

    /** Stable reference of the series for provenance, e.g. "FP.CPI.TOTL.ZG/IDN". */
    public function reference(string $series, string $area): string;
}
