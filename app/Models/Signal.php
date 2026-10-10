<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Ramsey\Uuid\Uuid;

class Signal extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

    protected $casts = [
        'is_ai_generated' => 'boolean',
        'confidence_score' => 'float',
        'reviewed_at' => 'datetime',
        'preliminary_impact' => 'integer',
        'preliminary_uncertainty' => 'integer',
    ];

    protected static function booted()
    {
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
