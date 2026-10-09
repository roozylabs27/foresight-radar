<?php

namespace App\Models;

use App\Http\Resources\ClosedItemsResource;
use App\Http\Resources\ForesightRadarResource;
use App\Http\Resources\OverallStatusCollection;
use App\Http\Resources\OverallStatusResource;
use App\Http\Resources\PrioritizingResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrivingForceRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'driving_force_id',
        'time_horizon_id',
        'status_action_id',
        'priority_id',
        'impact_analysis',
        'uncertainty_analysis',
    ];

    public function driving_force(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'driving_force_id');
    }

    public function time_horizon(): BelongsTo
    {
        return $this->belongsTo(TimeHorizon::class, 'time_horizon_id');
    }

    public function status_action(): BelongsTo
    {
        return $this->belongsTo(StatusAction::class, 'status_action_id');
    }

    public function action_reasons(): HasMany
    {
        return $this->hasMany(ActionReason::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    protected static function resolveDateRange(): array
    {
        $date_range = request('date');
        if (!empty($date_range) && is_array($date_range) && count($date_range) >= 2 && !empty($date_range[0]) && !empty($date_range[1])) {
            return [
                $date_range[0] . ' 00:00:00',
                $date_range[1] . ' 23:59:59',
            ];
        }

        return [
            \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
            \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d 23:59:59'),
        ];
    }

    public static function prioritizing()
    {
        [$date_start, $date_end] = self::resolveDateRange();
        $dimension = request('dimension');

        $prioritizing = self::with('driving_force')
            ->when($dimension, function ($q) use ($dimension) {
                $q->whereHas('driving_force', function ($q) use ($dimension) {
                    $q->where('dimension_id', $dimension);
                });
            })
            ->has('driving_force')
            ->whereHas('driving_force', function ($q) {
                $q->where('status', 'APPROVED');
            })
            ->where('created_at', '>=', $date_start)
            ->whereNotNull('status_action_id')
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'DESC')
            ->get();

        return PrioritizingResource::collection($prioritizing);
    }

    public static function registered_list()
    {
        $dimension = request('dimension');
        $time_horizon = request('time_horizon');
        $priority = request('priority');
        $status_action = request('status_action');
        [$date_start, $date_end] = self::resolveDateRange();

        $registered_list = self::with(['driving_force' => function ($q) {
            $q->orderBy('dimension_id', 'ASC');
        }, 'action_reasons' => function ($q) {
            $q->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('action_reasons')
                    ->groupBy('driving_force_rating_id')
                    ->orderBy('date', 'DESC');
            });
        }, 'status_action' => function ($q) {
            $q->select('id', 'code');
        }],)
            ->when($dimension, function ($q) use ($dimension) {
                $q->whereHas('driving_force', function ($q) use ($dimension) {
                    $q->where('dimension_id', $dimension);
                });
            })
            ->when($time_horizon, function ($q) use ($time_horizon) {
                $q->where('time_horizon_id', $time_horizon);
            })
            ->when($priority, function ($q) use ($priority) {
                $q->where('priority_id', $priority);
            })
            ->when($status_action, function ($q) use ($status_action) {
                $q->where('status_action_id', $status_action);
            })
            ->has('driving_force')
            ->whereHas('driving_force', function ($q) {
                $q->where('status', 'APPROVED');
            })
            ->whereNotNull('status_action_id')
            ->where('created_at', '>=', $date_start)
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'ASC');

        return $registered_list;
    }

    public static function foresight_radar()
    {
        [$date_start, $date_end] = self::resolveDateRange();
        $dimension = request('dimension');

        $overall_status = self::with(['driving_force' => function ($q) {
            $q->orderBy('dimension_id', 'ASC');
        }])
            ->when($dimension, function ($q) use ($dimension) {
                $q->whereHas('driving_force', function ($q) use ($dimension) {
                    $q->where('dimension_id', $dimension);
                });
            })
            ->has('driving_force')
            ->whereHas('driving_force', function ($q) {
                $q->where('status', 'APPROVED');
            })
            ->where('created_at', '>=', $date_start)
            ->whereNotNull('status_action_id')
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'ASC')
            ->get();

        return ForesightRadarResource::collection($overall_status);
    }
}
