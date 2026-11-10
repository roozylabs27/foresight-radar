<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\FetchIndicatorSource;
use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Services\IndicatorSources\IndicatorSource;
use App\Services\IndicatorSources\IndicatorSourceException;
use App\Services\IndicatorSources\WorldBankSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Roadmap R3.6: values of an external series become observations of an indicator. Each new period
 * is stored once as a proposal (origin `external`) with its provenance; it counts only once a person
 * confirms it (IndicatorObservationController::confirm). The direction compares a value with the
 * previous period of the same source.
 */
class ExternalIndicatorService
{
    public const ORIGIN = 'external';

    /** Connectors by `external_source` key. */
    public const SOURCES = ['world_bank' => WorldBankSource::class];

    /** Indicators the daily command fetches: active, accepted and set up for a connector. */
    public static function due(): Collection
    {
        return EarlyWarningIndicator::where('source_type', 'external')
            ->whereIn('external_source', array_keys(self::SOURCES))
            ->whereNotNull('external_series')
            ->whereNotNull('external_area')
            ->where('status', 'active')
            ->where('review_status', 'accepted')
            ->get();
    }

    public function request(EarlyWarningIndicator $indicator): void
    {
        if ($indicator->source_type !== 'external' || ! isset(self::SOURCES[$indicator->external_source ?? ''])) {
            throw ValidationException::withMessages(['external' => 'Indikator ini tidak diatur untuk sumber eksternal.']);
        }
        if ($indicator->status !== 'active') {
            throw ValidationException::withMessages(['external' => 'Indikator yang tidak dipantau lagi tidak diambil datanya.']);
        }

        $this->queue($indicator);
    }

    /** Queue a fetch unless one is already waiting or running. */
    public function queue(EarlyWarningIndicator $indicator): bool
    {
        if (LlmJobStatus::tryFrom((string) $indicator->fetch_status)?->isPending()) {
            return false;
        }
        $indicator->forceFill(['fetch_status' => LlmJobStatus::QUEUED->value, 'fetch_error' => null])->save();
        FetchIndicatorSource::dispatch($indicator);

        return true;
    }

    /** Run by FetchIndicatorSource. Returns the number of new observations. */
    public function fetch(EarlyWarningIndicator $indicator): int
    {
        $source = $this->source((string) $indicator->external_source);
        $series = (string) $indicator->external_series;
        $area = (string) $indicator->external_area;

        try {
            $points = collect($source->fetch($series, $area))->sortBy('date')->values();
        } catch (IndicatorSourceException $e) {
            $this->fail($indicator, $e->getMessage());

            return 0;
        }

        $now = Carbon::now();
        $today = $now->toDateString();
        $known = $indicator->observations()->where('source', $indicator->external_source)->whereNotNull('source_period')->get(['source_period', 'observed_at', 'value_numeric']);
        $periods = $known->pluck('source_period')->flip();

        $created = DB::transaction(function () use ($indicator, $source, $series, $area, $points, $known, $periods, $now, $today) {
            $created = 0;
            foreach ($points as $point) {
                if ($periods->has($point['period'])) {
                    continue;
                }
                $previous = $this->previousValue($points, $known, $point['date']);
                $indicator->observations()->create([
                    'tenant_id' => $indicator->tenant_id,
                    'observed_at' => min($point['date'], $today),
                    'value_numeric' => $point['value'],
                    'direction' => $previous === null ? null : $this->direction($previous, $point['value']),
                    'crossed_threshold' => false,
                    'origin' => self::ORIGIN,
                    'review_status' => 'proposed',
                    'source' => $indicator->external_source,
                    'source_ref' => $source->reference($series, $area),
                    'source_period' => $point['period'],
                    'fetched_at' => $now,
                    'recorded_by' => null,
                ]);
                $created++;
            }

            return $created;
        });

        $indicator->forceFill(['fetch_status' => LlmJobStatus::COMPLETED->value, 'fetch_error' => null, 'last_fetched_at' => $now])->save();

        return $created;
    }

    public function fail(EarlyWarningIndicator $indicator, string $message): void
    {
        $indicator->forceFill(['fetch_status' => LlmJobStatus::FAILED->value, 'fetch_error' => Str::limit($message, 500, '')])->save();
    }

    private function source(string $key): IndicatorSource
    {
        return app(self::SOURCES[$key]);
    }

    /**
     * Value of the latest period before `$date`, from this fetch or from earlier stored values.
     *
     * @param  Collection<int, array{period: string, date: string, value: float}>  $points
     * @param  Collection<int, IndicatorObservation>  $known
     */
    private function previousValue(Collection $points, Collection $known, string $date): ?float
    {
        $latest = null;
        $value = null;
        foreach ($points as $point) {
            if ($point['date'] < $date && ($latest === null || $point['date'] > $latest)) {
                [$latest, $value] = [$point['date'], $point['value']];
            }
        }
        foreach ($known as $row) {
            $day = $row->observed_at->toDateString();
            if ($row->value_numeric !== null && $day < $date && ($latest === null || $day > $latest)) {
                [$latest, $value] = [$day, (float) $row->value_numeric];
            }
        }

        return $value;
    }

    private function direction(float $previous, float $value): string
    {
        if (abs($value - $previous) < 1e-9) {
            return 'stable';
        }

        return $value > $previous ? 'rising' : 'falling';
    }
}
