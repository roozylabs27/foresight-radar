<?php

namespace App\Services;

use App\Enums\TimeHorizonType;
use App\Http\Resources\ForesightRadarResource;
use App\Http\Resources\PrioritizingResource;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Priority;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class ForesightReportingService
{
    /**
     * Resolves a sanitized start and end date range.
     */
    public function resolveDateRange(?array $dateRange = null): array
    {
        $date = $dateRange ?? request('date');
        if (! empty($date) && is_array($date) && count($date) >= 2 && ! empty($date[0]) && ! empty($date[1])) {
            return [
                $date[0].' 00:00:00',
                $date[1].' 23:59:59',
            ];
        }

        return [
            // Same default as the analysis pages: the last 12 calendar months, this month included.
            Carbon::now()->subMonthsNoOverflow(11)->startOfMonth()->format('Y-m-d 00:00:00'),
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
                if (! empty($dimension)) {
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
                            'source_signal.source:id,uuid,title,url',
                            'supporting_signals.source:id,uuid,title,url',
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

        $dimensionOrder = Dimension::ordered()->pluck('id')->all();

        return ForesightRadarResource::collection(
            $ratings->map(fn ($rating) => (new ForesightRadarResource($rating))->withDimensionOrder($dimensionOrder))
        );
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
                        'source_signal.source:id,uuid,title,url',
                        'supporting_signals.source:id,uuid,title,url',
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
     * Key Focal Issue radar: the exercise's scored forces that have a time horizon (from the firm
     * rating), since the radar places a force in a horizon band.
     *
     * @return array{0: AnonymousResourceCollection, 1: int} the dataset and how many forces were left out
     */
    public function getExerciseRadarDataset(ScenarioPlanningProject $project, mixed $dimension = null): array
    {
        [$ratings, $skipped] = $this->exerciseRatings($project, $dimension);
        $placed = $ratings->filter(fn (DrivingForceRating $rating) => TimeHorizonType::fromModel($rating->time_horizon) !== null)->values();
        $dimensionOrder = Dimension::ordered()->pluck('id')->all();

        return [
            ForesightRadarResource::collection($placed->map(fn ($rating) => (new ForesightRadarResource($rating))->withDimensionOrder($dimensionOrder))),
            $skipped + ($ratings->count() - $placed->count()),
        ];
    }

    /**
     * Key Focal Issue prioritizing matrix: the exercise's scored forces.
     *
     * @return array{0: AnonymousResourceCollection, 1: int} the dataset and how many unscored forces were left out
     */
    public function getExercisePrioritizingDataset(ScenarioPlanningProject $project, mixed $dimension = null): array
    {
        [$ratings, $skipped] = $this->exerciseRatings($project, $dimension);

        return [PrioritizingResource::collection($ratings), $skipped];
    }

    /**
     * Key Focal Issue registered list, with the same horizon, priority and status action filters as
     * the firm list (priority is the one recalculated from the consensus).
     *
     * @return Collection<int, DrivingForceRating>
     */
    public function getExerciseRegisteredList(
        ScenarioPlanningProject $project,
        mixed $dimension = null,
        mixed $timeHorizon = null,
        mixed $priority = null,
        mixed $statusAction = null
    ): Collection {
        $timeHorizon = $timeHorizon ?? request('time_horizon');
        $priority = $priority ?? request('priority');
        $statusAction = $statusAction ?? request('status_action');

        return $this->exerciseRatings($project, $dimension)[0]
            ->when($timeHorizon, fn ($rows) => $rows->filter(fn ($rating) => (string) $rating->time_horizon_id === (string) $timeHorizon))
            ->when($priority, fn ($rows) => $rows->filter(fn ($rating) => (string) $rating->priority_id === (string) $priority))
            ->when($statusAction, fn ($rows) => $rows->filter(fn ($rating) => (string) $rating->status_action_id === (string) $statusAction))
            ->values();
    }

    /**
     * The included forces of an exercise that have a consensus score, each shown as a firm rating
     * whose impact and uncertainty are the exercise consensus and whose priority is recalculated from
     * them. Horizon, status action and the latest action reason come from the firm's latest rating of
     * the force; a force the firm never rated gets an empty one. Nothing is saved.
     *
     * @return array{0: Collection<int, DrivingForceRating>, 1: int} the rows (by dimension order, then keyword) and how many included forces have no consensus
     */
    public function exerciseRatings(ScenarioPlanningProject $project, mixed $dimension = null): array
    {
        $dimension = $dimension ?? request('dimension');

        $links = $project->projectForces()
            ->where('inclusion_status', 'included')
            ->when(! empty($dimension), fn ($q) => $q->whereHas('drivingForce', fn ($force) => $force->where('dimension_id', $dimension)))
            ->with([
                'consensus',
                'drivingForce' => fn ($q) => $q->with([
                    'dimension',
                    'pic_user',
                    'source_signal.source:id,uuid,title,url',
                    'supporting_signals.source:id,uuid,title,url',
                ]),
            ])
            ->get();
        $scored = $links->filter(fn (ProjectDrivingForce $link) => $link->consensus !== null && $link->drivingForce !== null);

        // The firm's latest rating per force, preferring one with a status action.
        $firmRatings = DrivingForceRating::whereIn('driving_force_id', $scored->pluck('driving_force_id'))
            ->with(['time_horizon', 'status_action', 'action_reasons' => fn ($q) => $q->orderByDesc('date')->orderByDesc('id')])
            ->get()
            ->sortBy(fn (DrivingForceRating $rating) => [$rating->status_action_id === null ? 1 : 0, -$rating->id])
            ->unique('driving_force_id')
            ->keyBy('driving_force_id');
        $priorities = Priority::all()->keyBy('id');
        $dimensionPosition = array_flip(Dimension::ordered()->pluck('id')->all());

        $ratings = $scored->map(function (ProjectDrivingForce $link) use ($firmRatings, $priorities) {
            $rating = $firmRatings->get($link->driving_force_id) ?? $this->emptyRating($link);
            $rating->setRelation('action_reasons', $rating->action_reasons->take(1)->values());
            $rating->setRelation('driving_force', $link->drivingForce);
            $rating->impact_analysis = $link->consensus->impact;
            $rating->uncertainty_analysis = $link->consensus->uncertainty;
            $rating->priority_id = $rating->calculatePriority();
            $rating->setRelation('priority', $priorities->get($rating->priority_id));

            return $rating;
        })
            ->sortBy(fn (DrivingForceRating $rating) => [$dimensionPosition[$rating->driving_force->dimension_id] ?? PHP_INT_MAX, $rating->driving_force->keyword])
            ->values();

        return [$ratings, $links->count() - $scored->count()];
    }

    /** A rating for a force the firm never rated: no horizon, status action or reason. */
    private function emptyRating(ProjectDrivingForce $link): DrivingForceRating
    {
        $rating = new DrivingForceRating;
        $rating->driving_force_id = $link->driving_force_id;
        $rating->uuid = $link->uuid;
        $rating->setRelation('time_horizon', null);
        $rating->setRelation('status_action', null);
        $rating->setRelation('action_reasons', new EloquentCollection);

        return $rating;
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
                'driving_force.dimension',
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
            ->when($timeHorizon, fn ($q) => $q->where('time_horizon_id', $timeHorizon))
            ->when($priority, fn ($q) => $q->where('priority_id', $priority))
            ->when($statusAction, fn ($q) => $q->where('status_action_id', $statusAction))
            ->orderBy(
                DrivingForce::select('dimension_id')
                    ->whereColumn('driving_forces.id', 'driving_force_ratings.driving_force_id')
                    ->limit(1),
                'ASC'
            )
            ->orderBy('created_at', 'ASC');
    }
}
