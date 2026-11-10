<?php

namespace App\Services\IndicatorSources;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * World Bank Indicators API v2 (public, no key): one indicator code for one country or aggregate.
 * The host is fixed; series and area are validated before they reach the URL. Periods are years
 * ("2024"), quarters ("2024Q1") or months ("2024M03"); a point is dated at the end of its period.
 */
class WorldBankSource implements IndicatorSource
{
    public const BASE_URL = 'https://api.worldbank.org/v2';

    /** Most recent values asked per fetch. */
    public const RECENT_VALUES = 20;

    public const TIMEOUT_SECONDS = 15;

    public function fetch(string $series, string $area): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()
                ->get(self::BASE_URL.'/country/'.rawurlencode($area).'/indicator/'.rawurlencode($series), ['format' => 'json', 'per_page' => self::RECENT_VALUES, 'mrv' => self::RECENT_VALUES]);
        } catch (ConnectionException) {
            throw new IndicatorSourceException('World Bank tidak dapat dihubungi. Coba lagi nanti.');
        }
        if (! $response->successful()) {
            throw new IndicatorSourceException("World Bank menjawab dengan status {$response->status()}.");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new IndicatorSourceException('Jawaban World Bank bukan JSON yang dikenali.');
        }
        if (isset($body[0]['message'])) {
            $reason = collect((array) $body[0]['message'])->pluck('value')->filter()->implode(' ');

            throw new IndicatorSourceException('World Bank menolak permintaan: '.($reason ?: 'alasan tidak disebutkan').'.');
        }
        if (! isset($body[1]) || ! is_array($body[1])) {
            throw new IndicatorSourceException('World Bank tidak punya data untuk seri dan wilayah ini.');
        }

        $points = [];
        foreach ($body[1] as $row) {
            $date = is_array($row) ? $this->periodEnd((string) ($row['date'] ?? '')) : null;
            if ($date === null || ! is_numeric($row['value'] ?? null)) {
                continue;
            }
            $points[] = ['period' => (string) $row['date'], 'date' => $date, 'value' => (float) $row['value']];
        }

        return $points;
    }

    public function reference(string $series, string $area): string
    {
        return "{$series}/{$area}";
    }

    private function periodEnd(string $period): ?string
    {
        if (preg_match('/^(\d{4})$/', $period, $match)) {
            return "{$match[1]}-12-31";
        }
        if (preg_match('/^(\d{4})Q([1-4])$/', $period, $match)) {
            return Carbon::create((int) $match[1], (int) $match[2] * 3, 1)->endOfMonth()->toDateString();
        }
        if (preg_match('/^(\d{4})M(0[1-9]|1[0-2])$/', $period, $match)) {
            return Carbon::create((int) $match[1], (int) $match[2], 1)->endOfMonth()->toDateString();
        }

        return null;
    }
}
