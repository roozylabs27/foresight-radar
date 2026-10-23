<?php

namespace App\Services;

use App\Enums\StatusActionType;
use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForceRating;
use App\Models\DrivingForceRatingHistory;
use App\Models\StatusAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 2 light empirical validation over driving_force_rating_histories and
 * action_reasons: how scores move per dimension over time, and how long a
 * force stays in Monitoring before it becomes a Decided Plan.
 *
 * Both read the ratings of currently APPROVED driving forces, optionally of
 * one dimension, so they describe the same set as the Overview.
 */
class RatingHistoryAnalyticsService
{
    /** Months with data a trend needs before it says anything. */
    public const MIN_TREND_MONTHS = 2;

    /**
     * Average impact and uncertainty per dimension at the end of each month,
     * using each rating's latest recorded state up to that moment.
     *
     * @param  array{0: string, 1: string}  $range  first and last day (Y-m-d)
     */
    public function scoreTrend(array $range, mixed $dimension = null, ?Collection $dimensions = null): array
    {
        $start = Carbon::parse($range[0])->startOfMonth();
        $end = Carbon::parse($range[1])->endOfDay();

        $ratings = $this->ratings($dimension);
        $histories = DrivingForceRatingHistory::query()
            ->whereIn('driving_force_rating_id', $ratings->keys())
            ->where('changed_at', '<=', $end)
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get()
            ->groupBy('driving_force_rating_id');

        $months = [];
        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $months[] = $month->copy();
        }

        $byDimension = [];
        $overall = [];
        foreach ($months as $month) {
            $monthEnd = $month->copy()->endOfMonth()->min($end);
            $states = $histories
                ->map(fn (Collection $rows) => $rows->last(fn ($row) => $row->changed_at->lte($monthEnd)))
                ->filter();

            $overall[] = $this->monthPoint($month, $states);
            foreach ($states->groupBy(fn ($state) => $ratings[$state->driving_force_rating_id]->driving_force?->dimension?->name) as $name => $group) {
                $byDimension[$name][$month->format('Y-m')] = $this->monthPoint($month, $group);
            }
        }

        $dimensionNames = ($dimensions ?? Dimension::orderBy('id')->get(['id', 'name']))->pluck('name');
        $dimensionSeries = $dimensionNames
            ->filter(fn ($name) => isset($byDimension[$name]))
            ->map(fn ($name) => $this->series($name, $months, $byDimension[$name]))
            ->values()
            ->all();

        $since = $histories->flatten()->min('changed_at');
        $updates = $histories->flatten()
            ->filter(fn ($row) => $row->event === 'updated' && $row->changed_at->gte($start))
            ->count();
        $monthsWithData = collect($overall)->where('n', '>', 0)->count();

        return [
            'since' => $since ? Carbon::parse($since)->format('Y-m-d') : null,
            'updates' => $updates,
            'months_with_data' => $monthsWithData,
            'enough' => $monthsWithData >= self::MIN_TREND_MONTHS && $updates > 0,
            'overall' => $this->series('Semua dimensi', $months, collect($overall)->keyBy('period')->all()),
            'dimensions' => $dimensionSeries,
        ];
    }

    /**
     * Days from the first Monitoring decision to the first Decided Plan after
     * it, per force, plus how long forces still in Monitoring have waited.
     * Status decisions come from action_reasons and from rating history.
     */
    public function transitionVelocity(array $range, mixed $dimension = null): array
    {
        $end = Carbon::parse($range[1])->endOfDay();
        $start = Carbon::parse($range[0])->startOfDay();
        $ratings = $this->ratings($dimension);
        $actions = StatusAction::all()->keyBy('id')->map(fn ($action) => StatusActionType::fromModel($action));

        $events = ActionReason::query()
            ->whereIn('driving_force_rating_id', $ratings->keys())
            ->where('date', '<=', $end)
            ->get(['driving_force_rating_id', 'status_action_id', 'date'])
            ->map(fn ($reason) => [
                'rating' => $reason->driving_force_rating_id,
                'action' => $actions[$reason->status_action_id] ?? null,
                'at' => Carbon::parse($reason->date),
            ])
            ->concat(
                DrivingForceRatingHistory::query()
                    ->whereIn('driving_force_rating_id', $ratings->keys())
                    ->whereIn('event', ['created', 'updated'])
                    ->whereNotNull('status_action_id')
                    ->where('changed_at', '<=', $end)
                    ->get(['driving_force_rating_id', 'status_action_id', 'changed_at'])
                    ->map(fn ($row) => [
                        'rating' => $row->driving_force_rating_id,
                        'action' => $actions[$row->status_action_id] ?? null,
                        'at' => $row->changed_at,
                    ])
            )
            ->filter(fn ($event) => $event['action'] !== null)
            ->groupBy('rating');

        $transitions = [];
        $waiting = [];
        foreach ($events as $ratingId => $stream) {
            $monitoringSince = null;
            $decidedAt = null;
            foreach ($stream->sortBy(fn ($event) => $event['at']->getTimestamp()) as $event) {
                if ($event['action'] === StatusActionType::MONITORING) {
                    $monitoringSince ??= $event['at'];
                } elseif ($monitoringSince !== null) {
                    $decidedAt = $event['at'];
                    break;
                }
            }

            $rating = $ratings[$ratingId];
            $force = [
                'id' => $rating->driving_force?->uuid,
                'keyword' => $rating->driving_force?->keyword,
                'dimension' => $rating->driving_force?->dimension?->name,
            ];

            if ($decidedAt !== null && $decidedAt->gte($start)) {
                $transitions[] = $force + [
                    'monitoring_since' => $monitoringSince->format('Y-m-d'),
                    'decided_at' => $decidedAt->format('Y-m-d'),
                    'days' => $this->days($monitoringSince, $decidedAt),
                ];
            } elseif ($decidedAt === null && $monitoringSince !== null
                && StatusActionType::fromModel($rating->status_action) === StatusActionType::MONITORING) {
                $waiting[] = $force + [
                    'monitoring_since' => $monitoringSince->format('Y-m-d'),
                    'days' => $this->days($monitoringSince, $end->copy()->min(Carbon::now())),
                ];
            }
        }

        $transitions = collect($transitions)->sortByDesc('days')->values();
        $waiting = collect($waiting)->sortByDesc('days')->values();

        return [
            'transitions' => $transitions->all(),
            'count' => $transitions->count(),
            'median_days' => $this->median($transitions->pluck('days')->all()),
            'mean_days' => $transitions->isEmpty() ? null : round($transitions->avg('days'), 1),
            'still_monitoring' => $waiting->all(),
            'still_monitoring_median_days' => $this->median($waiting->pluck('days')->all()),
        ];
    }

    /**
     * Ratings of approved forces, keyed by id.
     */
    protected function ratings(mixed $dimension): Collection
    {
        return DrivingForceRating::query()
            ->whereHas('driving_force', function ($q) use ($dimension) {
                $q->where('status', 'APPROVED');
                if (! empty($dimension)) {
                    $q->where('dimension_id', $dimension);
                }
            })
            ->with(['driving_force.dimension', 'status_action'])
            ->get()
            ->keyBy('id');
    }

    protected function monthPoint(Carbon $month, Collection $states): array
    {
        $impact = $states->whereNotNull('impact_analysis')->avg('impact_analysis');
        $uncertainty = $states->whereNotNull('uncertainty_analysis')->avg('uncertainty_analysis');

        return [
            'period' => $month->format('Y-m'),
            'n' => $states->count(),
            'avg_impact' => $impact === null ? null : round($impact, 2),
            'avg_uncertainty' => $uncertainty === null ? null : round($uncertainty, 2),
        ];
    }

    /**
     * One point per month (empty months keep null averages) and the change
     * between the first and last month that have data.
     */
    protected function series(string $name, array $months, array $pointsByPeriod): array
    {
        $points = array_map(
            fn (Carbon $month) => $pointsByPeriod[$month->format('Y-m')]
                ?? ['period' => $month->format('Y-m'), 'n' => 0, 'avg_impact' => null, 'avg_uncertainty' => null],
            $months
        );
        $withData = array_values(array_filter($points, fn ($point) => $point['n'] > 0));
        $first = $withData[0] ?? null;
        $last = $withData[count($withData) - 1] ?? null;
        $change = fn (string $key) => count($withData) >= 2 && $first[$key] !== null && $last[$key] !== null
            ? round($last[$key] - $first[$key], 2)
            : null;

        return [
            'name' => $name,
            'points' => $points,
            'impact_change' => $change('avg_impact'),
            'uncertainty_change' => $change('avg_uncertainty'),
        ];
    }

    protected function days(Carbon $from, Carbon $to): float
    {
        return round(max(0, $from->diffInMinutes($to)) / 1440, 1);
    }

    protected function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return round(count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 1);
    }
}
