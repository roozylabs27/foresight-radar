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

    public static function overall_status()
    {
        $pagination = request('pagination.pageSize');
        $dimension = request('dimension');
        $date_range = request('date');
        $date_start = $date_range[0] . ' 00:00:00';
        $date_end = $date_range[1] . ' 23:59:59';

        $overall_status = self::with(['driving_force' => function ($q) {
            $q->orderBy('dimension_id', 'ASC');
        }, 'action_reasons' => function ($q) {
            $q->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('action_reasons')
                    ->groupBy('driving_force_rating_id') // Ganti dengan foreign key yang relevan
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
            ->has('driving_force')
            ->whereHas('driving_force', function ($q) {
                $q->where('status', 'APPROVED');
            })
            ->whereNotNull('status_action_id')
            ->where('created_at', '>=', $date_start)
            ->where('created_at', '<=', $date_end)
            ->orderBy('created_at', 'ASC')
            ->paginate($pagination);

        // $dimensions = Dimension::query()->select('id', 'name')
        //     ->with(['driving_forces' => function ($q) {
        //         $q->where('status', 'approved')->with('rating');
        //     }])
        //     ->when($dimension, function ($q) use ($dimension) {
        //         $q->where('id', $dimension);
        //     })
        //     ->whereHas('driving_forces', function ($q) use ($date_start, $date_end) {
        //         $q->where('status', 'approved')
        //             ->where('created_at', '>=', $date_start)
        //             ->where('created_at', '<=', $date_end);
        //     })
        //     ->orderBy('id', 'asc')
        //     ->paginate($pagination);
        // $registered_list = $dimensions->map(function ($dimension) use($date_start, $date_end) {
        //     $signals = DrivingForceRating::query()
        //         ->whereNotNull('status_action_id')
        //         ->where('created_at', '>=', $date_start)
        //         ->where('created_at', '<=', $date_end)
        //         ->whereHas('driving_force', function ($q) use ($dimension) {
        //             $q->where('dimension_id', $dimension->id)->where('status', 'APPROVED');
        //         })
        //         ->orderBy('created_at', 'DESC')->get();

        //     return [
        //         'dimension' => $dimension->name,
        //         'signals' => $signals
        //     ];
        // });

        // dd($registered_list);

        return new OverallStatusCollection($overall_status);
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
