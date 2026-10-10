<?php

namespace App\Services;

use App\Http\Resources\ForesightRadarResource;
use App\Http\Resources\PrioritizingResource;
use App\Models\DrivingForceRating;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ForesightReportingService
{
    /**
     * Resolves a sanitized start and end date range.
     */
    public function resolveDateRange(?array $dateRange = null): array
    {
        $date = $dateRange ?? request('date');
        if (!empty($date) && is_array($date) && count($date) >= 2 && !empty($date[0]) && !empty($date[1])) {
            return [
                $date[0] . ' 00:00:00',
                $date[1] . ' 23:59:59',
            ];
        }

        return [
            Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
            Carbon::now()->endOfMonth()->format('Y-m-d 23:59:59'),
        ];
    }

    /**
     * Common query scope for approved driving forces with status action.
     */
    public function baseApprovedRatingQuery(?array $dateRange = null, mixed $dimension = null): Builder
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($dateRange);
        $dimension = $dimension ?? request('dimension');

        return DrivingForceRating::query()
            ->has('driving_force')
            ->whereHas('driving_force', function ($q) use ($dimension) {
                $q->where('status', 'APPROVED');
                if (!empty($dimension)) {
                    $q->where('dimension_id', $dimension);
                }
            })
            ->whereNotNull('status_action_id')
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd);
    }

    /**
     * Retrieve Foresight Radar dataset.
     */
    public function getRadarDataset(?array $dateRange = null, mixed $dimension = null)
    {
        $ratings = $this->baseApprovedRatingQuery($dateRange, $dimension)
            ->with([
                'driving_force' => function ($q) {
                    $q->orderBy('dimension_id', 'ASC')
                        ->with([
                            'dimension',
                            'pic_user',
                            'source_signal.source',
                            'supporting_signals.source',
                        ]);
                },
                'time_horizon',
                'priority',
                'status_action',
                'action_reasons' => function ($q) {
                    $q->whereIn('id', function ($query) {
                        $query->selectRaw('MAX(id)')
                            ->from('action_reasons')
                            ->groupBy('driving_force_rating_id')
                            ->orderBy('date', 'DESC');
                    });
                },
            ])
            ->orderBy('created_at', 'ASC')
            ->get();

        return ForesightRadarResource::collection($ratings);
    }

    /**
     * Retrieve Prioritizing dataset.
     */
    public function getPrioritizingDataset(?array $dateRange = null, mixed $dimension = null)
    {
        $ratings = $this->baseApprovedRatingQuery($dateRange, $dimension)
            ->with([
                'driving_force' => function ($q) {
                    $q->with([
                        'dimension',
                        'pic_user',
                        'source_signal.source',
                        'supporting_signals.source',
                    ]);
                },
                'time_horizon',
                'priority',
                'status_action',
                'action_reasons' => function ($q) {
                    $q->whereIn('id', function ($query) {
                        $query->selectRaw('MAX(id)')
                            ->from('action_reasons')
                            ->groupBy('driving_force_rating_id')
                            ->orderBy('date', 'DESC');
                    });
                },
            ])
            ->orderBy('created_at', 'DESC')
            ->get();

        return PrioritizingResource::collection($ratings);
    }

    /**
     * Query builder for Registered List report and export.
     */
    public function getRegisteredListQuery(
        ?array $dateRange = null,
        mixed $dimension = null,
        mixed $timeHorizon = null,
        mixed $priority = null,
        mixed $statusAction = null
    ): Builder {
        $timeHorizon = $timeHorizon ?? request('time_horizon');
        $priority = $priority ?? request('priority');
        $statusAction = $statusAction ?? request('status_action');

        return $this->baseApprovedRatingQuery($dateRange, $dimension)
            ->with([
                'driving_force' => function ($q) {
                    $q->orderBy('dimension_id', 'ASC');
                },
                'action_reasons' => function ($q) {
                    $q->whereIn('id', function ($query) {
                        $query->selectRaw('MAX(id)')
                            ->from('action_reasons')
                            ->groupBy('driving_force_rating_id')
                            ->orderBy('date', 'DESC');
                    });
                },
                'status_action' => function ($q) {
                    $q->select('id', 'code');
                },
            ])
            ->when($timeHorizon, fn($q) => $q->where('time_horizon_id', $timeHorizon))
            ->when($priority, fn($q) => $q->where('priority_id', $priority))
            ->when($statusAction, fn($q) => $q->where('status_action_id', $statusAction))
            ->orderBy('created_at', 'ASC');
    }
}
