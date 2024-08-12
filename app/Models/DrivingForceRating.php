<?php

namespace App\Models;

use App\Http\Resources\ForesightRadarResource;
use App\Http\Resources\OverallStatusResource;
use App\Http\Resources\PrioritizingResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public static function prioritizing()
    {
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';
        $dimension = request('dimension');

        $prioritizing = self::with('driving_force')
            ->when($dimension, function ($q) use ($dimension) {
                $q->whereHas('driving_force', function ($q) use ($dimension) {
                    $q->where('dimension_id', $dimension);
                });
            })
            ->has('driving_force')
            ->where('created_at', '>=', $date_start)
            ->whereNotNull('status_action_id')
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'ASC')
            ->limit(20)
            ->get();

        return PrioritizingResource::collection($prioritizing);
    }

    public static function overall_status()
    {
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $overall_status = self::with(['driving_force' => function ($q) {
            $q->orderBy('dimension_id', 'ASC');
        }])
            ->has('driving_force')
            ->where('created_at', '>=', $date_start)
            ->whereNotNull('status_action_id')
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'ASC')
            ->limit(20)
            ->get();

        return OverallStatusResource::collection($overall_status);
    }

    public static function foresight_radar()
    {
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';
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
            ->where('created_at', '>=', $date_start)
            ->whereNotNull('status_action_id')
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'ASC')
            ->limit(20)
            ->get();

        return ForesightRadarResource::collection($overall_status);
    }
}
