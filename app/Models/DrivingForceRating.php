<?php

namespace App\Models;

use App\Observers\DrivingForceRatingObserver;
use App\Services\ForesightReportingService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * @property Carbon|null $assessed_at time of the latest assessment (PI-07): the FIRM reporting period filters on it
 */
class DrivingForceRating extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'driving_force_id',
        'time_horizon_id',
        'status_action_id',
        'priority_id',
        'impact_analysis',
        'uncertainty_analysis',
    ];

    protected $casts = [
        'assessed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Uuid::uuid1();
            }
        });

        // PI-07: an assessment is a change of a scored field (the same fields the rating history
        // tracks). A save that changes none of them, or only touches the row, is not an assessment.
        static::saving(function (self $model) {
            if (! $model->exists) {
                $model->assessed_at ??= $model->freshTimestamp();
            } elseif ($model->isDirty(DrivingForceRatingObserver::TRACKED)) {
                $model->assessed_at = $model->freshTimestamp();
            }
        });
    }

    /**
     * PI-07: set `assessed_at` where it is missing, from the latest rating history event, else the
     * creation time. Ratings created before the history existed got a 'baseline' history row (their
     * updated_at or created_at) in 2026_10_16_000001, so the fallback only covers rows with no history.
     */
    public static function backfillAssessedAt(): int
    {
        return DB::table('driving_force_ratings')->whereNull('assessed_at')->update([
            'assessed_at' => DB::raw('COALESCE((SELECT MAX(h.changed_at) FROM driving_force_rating_histories h WHERE h.driving_force_rating_id = driving_force_ratings.id), driving_force_ratings.created_at)'),
        ]);
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

    public function histories(): HasMany
    {
        return $this->hasMany(DrivingForceRatingHistory::class)->orderBy('changed_at')->orderBy('id');
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
        return app(ForesightReportingService::class)->resolveDateRange();
    }

    public static function prioritizing()
    {
        return app(ForesightReportingService::class)->getPrioritizingDataset();
    }

    public static function registered_list()
    {
        return app(ForesightReportingService::class)->getRegisteredListQuery();
    }

    public static function foresight_radar()
    {
        return app(ForesightReportingService::class)->getRadarDataset();
    }
}
