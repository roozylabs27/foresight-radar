<?php

namespace App\Services;

use App\Http\Resources\ApprovalItemsCollection;
use App\Http\Resources\ClosedItemsCollection;
use App\Http\Resources\DrivingForceCollection;
use App\Http\Resources\RatingUrgencyCollection;
use App\Http\Resources\StatusActionCollection;
use App\Http\Resources\TimeHorizonCollection;
use App\Models\DrivingForce;
use Carbon\Carbon;

class DrivingForceService
{
    /**
     * Resolve date range from params or default to current month.
     */
    private function resolveDateRange(array $params): array
    {
        if (!empty($params['date']) && is_array($params['date']) && count($params['date']) === 2) {
            return [
                $params['date'][0] . ' 00:00:00',
                $params['date'][1] . ' 23:59:59',
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
        if (isset($params['pagination']['pageSize'])) {
            return (int) $params['pagination']['pageSize'];
        }

        if (isset($params['pageSize'])) {
            return (int) $params['pageSize'];
        }

        return 10;
    }

    public function filter(array $params): DrivingForceCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;
        $status = $params['status'] ?? null;

        $drivingForces = DrivingForce::with(['dimension', 'created_by_user', 'updated_by_user'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search . '%')
                        ->orWhere('description', 'LIKE', $search . '%');
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
            ->orderBy('created_at', 'DESC')
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
                    $sub->where('keyword', 'LIKE', $search . '%')
                        ->orWhere('description', 'LIKE', $search . '%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->orderBy('created_at', 'DESC')
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
                    $sub->where('keyword', 'LIKE', $search . '%')
                        ->orWhere('description', 'LIKE', $search . '%');
                });
            })
            ->when($dimension, function ($q) use ($dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $dateStart)
            ->has('rating')
            ->where('created_at', '<=', $dateEnd)
            ->orderBy('created_at', 'DESC')
            ->paginate($pageSize);

        return new RatingUrgencyCollection($ratings);
    }

    public function statusAction(array $params): StatusActionCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $statusActions = DrivingForce::with(['dimension', 'rating.action_reasons' => function ($q) {
            $q->select('driving_force_rating_id', 'date', 'reason', 'status_action_id')->orderBy('date', 'ASC');
        }])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search . '%')
                        ->orWhere('description', 'LIKE', $search . '%');
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
            ->orderBy('created_at', 'DESC')
            ->paginate($pageSize);

        return new StatusActionCollection($statusActions);
    }

    public function approvalItems(array $params): ApprovalItemsCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $approvalItems = DrivingForce::with(['rating'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search . '%')
                        ->orWhere('description', 'LIKE', $search . '%');
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
            ->orderBy('created_at', 'DESC')
            ->orderBy('status', 'DESC')
            ->paginate($pageSize);

        return new ApprovalItemsCollection($approvalItems);
    }

    public function closedItems(array $params): ClosedItemsCollection
    {
        [$dateStart, $dateEnd] = $this->resolveDateRange($params);
        $pageSize = $this->resolvePageSize($params);
        $search = $params['search'] ?? null;
        $dimension = $params['dimension'] ?? null;

        $closedItems = DrivingForce::with(['rating'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('keyword', 'LIKE', $search . '%')
                        ->orWhere('description', 'LIKE', $search . '%');
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
            ->orderBy('created_at', 'DESC')
            ->paginate($pageSize);

        return new ClosedItemsCollection($closedItems);
    }
}
