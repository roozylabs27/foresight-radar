<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * An early warning indicator of one exercise (roadmap R1.9, step 9): what to monitor and how, with
 * the expected direction in each quadrant. Metric fields are optional (qualitative indicators).
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property int|null $critical_uncertainty_id
 * @property int|null $project_driving_force_id
 * @property string|null $unit
 * @property Carbon|null $baseline_date
 * @property string $frequency
 * @property int|null $owner_id
 * @property string $source_type
 * @property string|null $external_source
 * @property string|null $external_series
 * @property string|null $external_area
 * @property string|null $fetch_status
 * @property string|null $fetch_error
 * @property Carbon|null $last_fetched_at
 * @property string $status
 * @property string $origin
 * @property string $review_status
 */
class EarlyWarningIndicator extends Model
{
    use BelongsToTenant;

    public const FREQUENCIES = ['monthly', 'quarterly', 'ad_hoc'];

    public const SOURCE_TYPES = ['manual', 'signal_feed', 'external'];

    public const STATUSES = ['active', 'retired'];

    public const DIRECTIONS = ['rising', 'falling', 'stable', 'qualitative'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'name',
        'description',
        'critical_uncertainty_id',
        'project_driving_force_id',
        'metric',
        'unit',
        'baseline_value',
        'baseline_date',
        'frequency',
        'owner_id',
        'source_type',
        'external_source',
        'external_series',
        'external_area',
        'threshold_rule',
        'status',
        'origin',
        'review_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'baseline_value' => 'float',
        'baseline_date' => 'date',
        'last_fetched_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($indicator) {
            if (empty($indicator->uuid)) {
                $indicator->uuid = (string) Uuid::uuid4();
            }
        });
    }

    /** Observations over time, newest first (roadmap R2.3). */
    public function observations(): HasMany
    {
        return $this->hasMany(IndicatorObservation::class)->orderByDesc('observed_at')->orderByDesc('id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function uncertainty(): BelongsTo
    {
        return $this->belongsTo(CriticalUncertainty::class, 'critical_uncertainty_id');
    }

    public function projectForce(): BelongsTo
    {
        return $this->belongsTo(ProjectDrivingForce::class, 'project_driving_force_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function directions(): HasMany
    {
        return $this->hasMany(IndicatorScenario::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
