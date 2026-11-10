<?php

namespace App\Models;

use App\Services\StalenessService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Ramsey\Uuid\Uuid;

/**
 * @property string|null $horizon
 * @property bool $wild_card
 * @property int|null $classified_by
 */
class Signal extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * Roadmap R4.2 (deck slide 26): likely = current trend, could = current knowledge,
     * might = weak signal / future knowledge.
     */
    public const HORIZONS = ['likely', 'could', 'might'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'source_id',
        'dimension_id',
        'title',
        'summary',
        'significance',
        'evidence_quote',
        'suggested_time_horizon_id',
        'preliminary_impact',
        'preliminary_uncertainty',
        'confidence_score',
        'confidence_rationale',
        'is_ai_generated',
        'review_status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'created_driving_force_id',
        'extraction_provider',
        'extraction_model',
        'prompt_version',
        'generation_run_id',
        'quote_verified',
        'suggested_driving_force_id',
        'horizon',
        'wild_card',
        'classified_by',
        'classified_at',
    ];

    protected $casts = [
        'is_ai_generated' => 'boolean',
        'confidence_score' => 'float',
        'reviewed_at' => 'datetime',
        'preliminary_impact' => 'integer',
        'preliminary_uncertainty' => 'integer',
        'quote_verified' => 'boolean',
        'wild_card' => 'boolean',
        'classified_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Evidence withdrawn: flag the scenario quadrants that cite this signal (roadmap R1.10).
        static::updated(function ($signal) {
            if ($signal->wasChanged('review_status') && $signal->getOriginal('review_status') === 'ACCEPTED') {
                app(StalenessService::class)->signalWithdrawn($signal);
            }
        });
        // Before the delete: the citation rows (R2.7) go with the signal through the foreign key.
        static::deleting(fn ($signal) => app(StalenessService::class)->signalWithdrawn($signal));

        static::creating(function ($signal) {
            if (empty($signal->uuid)) {
                $signal->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function suggested_time_horizon(): BelongsTo
    {
        return $this->belongsTo(TimeHorizon::class, 'suggested_time_horizon_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function created_driving_force(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'created_driving_force_id');
    }

    /** Existing driving force the extractor thinks this signal supports. */
    public function suggested_driving_force(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'suggested_driving_force_id');
    }

    public function driving_forces(): BelongsToMany
    {
        return $this->belongsToMany(DrivingForce::class, 'signal_driving_force')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
