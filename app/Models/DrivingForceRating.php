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
use Illuminate\Database\Eloquent\SoftDeletes;

class DrivingForceRating extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'driving_force_id',
        'time_horizon_id',
        'status_action_id',
        'priority_id',
        'impact_analysis',
        'uncertainty_analysis',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Ramsey\Uuid\Uuid::uuid1();
            }
        });
    }

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

    public function calculatePriority(): ?int
    {
        if ($this->impact_analysis === null || $this->uncertainty_analysis === null) {
            return null;
        }

        if ($this->impact_analysis >= 6 && $this->uncertainty_analysis >= 6) {
            $priorityName = 'High';
            $fallbackId = 1;
        } elseif ($this->impact_analysis >= 6 || $this->uncertainty_analysis >= 6) {
            $priorityName = 'Medium';
            $fallbackId = 2;
        } else {
            $priorityName = 'Low';
            $fallbackId = 3;
        }

        return Priority::where('name', $priorityName)->value('id') ?? $fallbackId;
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    protected static function resolveDateRange(): array
    {
        return app(\App\Services\ForesightReportingService::class)->resolveDateRange();
    }

    public static function prioritizing()
    {
        return app(\App\Services\ForesightReportingService::class)->getPrioritizingDataset();
    }

    public static function registered_list()
    {
        return app(\App\Services\ForesightReportingService::class)->getRegisteredListQuery();
    }

    public static function foresight_radar()
    {
        return app(\App\Services\ForesightReportingService::class)->getRadarDataset();
    }
}
