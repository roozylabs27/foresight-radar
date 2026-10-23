<?php

namespace App\Services;

use App\Http\Resources\ApprovalItemsCollection;
use App\Http\Resources\ClosedItemsCollection;
use App\Http\Resources\DrivingForceCollection;
use App\Http\Resources\RatingUrgencyCollection;
use App\Http\Resources\StatusActionCollection;
use App\Http\Resources\TimeHorizonCollection;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\User;
use App\Support\TableSort;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DrivingForceService
{
    /**
     * Resolve date range from params or default to current month.
     */
    private function resolveDateRange(array $params): array
    {
        if (! empty($params['date']) && is_array($params['date']) && count($params['date']) === 2) {
            return [
                $params['date'][0].' 00:00:00',
                $params['date'][1].' 23:59:59',
            ];
        }

        return [
            Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
            Carbon::now()->endOfMonth()->format('Y-m-d 23:59:59'),
        ];
    }

    /**
     * Resolve pagination page size.
     */
    private function resolvePageSize(array $params): int
    {
        $size = 10;

        if (isset($params['pagination']['pageSize'])) {
            $size = (int) $params['pagination']['pageSize'];
        } elseif (isset($params['pageSize'])) {
            $size = (int) $params['pageSize'];
        }

        return min(100, max(1, $size > 0 ? $size : 10));
    }

    /**
     * Sortable related values for the DataTable columns (see TableSort).
     */
    private function dimensionName(): Builder
    {
        return Dimension::select('name')->whereColumn('dimensions.id', 'driving_forces.dimension_id');
    }

    private function picName(): Builder
    {
        return User::withTrashed()->select('name')->whereColumn('users.id', 'driving_forces.pic');
    }

    private function ratingValue(string $column): Builder
    {
        return DrivingForceRating::select($column)
            ->whereColumn('driving_force_ratings.driving_force_id', 'driving_forces.id')
            ->limit(1);
    }

    public function filter(array $params): DrivingForceCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;
        $status = $params['status'] ?? null;

        $drivingForces = DrivingForce::with([
            'dimension',
            'created_by_user',
            'updated_by_user',
            'pic_user',
            'source_signal.source:id,uuid,title,url',
            'supporting_signals.source:id,uuid,title,url',
            'rating',
        ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search.'%')
                        ->orWhere('description', 'LIKE', $search.'%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->tap(fn ($q) => TableSort::apply(
                $q,
                $params,
                [
                    'date_created' => 'created_at',
                    'keyword' => 'keyword',
                    'dimension' => $this->dimensionName(),
                    'pic' => $this->picName(),
                ],
                fn ($q) => $q->orderBy('created_at', 'DESC')
            ))
            ->paginate($pageSize);

        return new DrivingForceCollection($drivingForces);
    }

    public function timeHorizon(array $params): TimeHorizonCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $timeHorizons = DrivingForce::with(['dimension', 'rating'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search.'%')
                        ->orWhere('description', 'LIKE', $search.'%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->tap(fn ($q) => TableSort::apply(
                $q,
                $params,
                [
                    'keyword' => 'keyword',
                    'dimension' => $this->dimensionName(),
                ],
                fn ($q) => $q->orderBy('created_at', 'DESC')
            ))
            ->paginate($pageSize);

        return new TimeHorizonCollection($timeHorizons);
    }

    public function ratingUrgency(array $params): RatingUrgencyCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $ratings = DrivingForce::with(['dimension', 'rating'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search.'%')
                        ->orWhere('description', 'LIKE', $search.'%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $dateStart)
            ->has('rating')
            ->where('created_at', '<=', $dateEnd)
            ->tap(fn ($q) => TableSort::apply(
                $q,
                $params,
                [
                    'keyword' => 'keyword',
                    'uncertainty' => $this->ratingValue('uncertainty_analysis'),
                    'impact' => $this->ratingValue('impact_analysis'),
                ],
                fn ($q) => $q->orderBy('created_at', 'DESC')
            ))
            ->paginate($pageSize);

        return new RatingUrgencyCollection($ratings);
    }

    public function statusAction(array $params): StatusActionCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $statusActions = DrivingForce::with([
            'dimension',
            'rating.time_horizon',
            'rating.priority',
            'rating.status_action',
            'rating.action_reasons' => function ($q) {
                $q->select('driving_force_rating_id', 'date', 'reason', 'status_action_id')
                    ->with('status_action')
                    ->orderBy('date', 'ASC');
            },
        ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search.'%')
                        ->orWhere('description', 'LIKE', $search.'%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $dateStart)
            ->has('rating')
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('impact_analysis')
                    ->whereNotNull('uncertainty_analysis');
            })
            ->where('created_at', '<=', $dateEnd)
            ->tap(fn ($q) => TableSort::apply(
                $q,
                $params,
                [
                    'keyword' => 'keyword',
                    'dimension' => $this->dimensionName(),
                ],
                fn ($q) => $q->orderBy('created_at', 'DESC')
            ))
            ->paginate($pageSize);

        return new StatusActionCollection($statusActions);
    }

    /**
     * Approval and Closed Items share their columns (Components/ReviewColumns.jsx).
     * Time horizon, priority and status action ids run in their natural order
     * (Short < Mid < Long, High < Medium < Low, Monitoring < Decided Plan).
     */
    private function reviewSortable(): array
    {
        return [
            'date_created' => 'created_at',
            'keyword' => 'keyword',
            'dimension' => $this->dimensionName(),
            'pic' => $this->picName(),
            'time_horizon' => $this->ratingValue('time_horizon_id'),
            'priority' => $this->ratingValue('priority_id'),
            'uncertainty_analysis' => $this->ratingValue('uncertainty_analysis'),
            'impact_analysis' => $this->ratingValue('impact_analysis'),
            'status' => 'status',
            'status_action' => $this->ratingValue('status_action_id'),
        ];
    }

    public function approvalItems(array $params): ApprovalItemsCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $approvalItems = DrivingForce::with([
            'dimension',
            'pic_user',
            'rating.time_horizon',
            'rating.priority',
            'rating.status_action',
            'rating.action_reasons',
        ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search.'%')
                        ->orWhere('description', 'LIKE', $search.'%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('status_action_id');
            })
            ->has('rating')
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->tap(fn ($q) => TableSort::apply(
                $q,
                $params,
                $this->reviewSortable(),
                fn ($q) => $q->orderBy('created_at', 'DESC')->orderBy('status', 'DESC')
            ))
            ->paginate($pageSize);

        return new ApprovalItemsCollection($approvalItems);
    }

    public function closedItems(array $params): ClosedItemsCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $closedItems = DrivingForce::with([
            'dimension',
            'pic_user',
            'rating.time_horizon',
            'rating.priority',
            'rating.status_action',
            'rating.action_reasons',
        ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search.'%')
                        ->orWhere('description', 'LIKE', $search.'%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('status_action_id');
            })
            ->has('rating')
            ->where('status', 'CLOSED')
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->tap(fn ($q) => TableSort::apply(
                $q,
                $params,
                $this->reviewSortable(),
                fn ($q) => $q->orderBy('created_at', 'DESC')
            ))
            ->paginate($pageSize);

        return new ClosedItemsCollection($closedItems);
    }
}
