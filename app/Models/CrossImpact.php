<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analyst judgement of how driving force `source` affects `target`:
 * -3 strongly suppresses, +3 strongly amplifies. A missing row means 0.
 */
class CrossImpact extends Model
{
    public const MIN_SCORE = -3;
    public const MAX_SCORE = 3;

    protected $fillable = [
        'uuid',
        'source_driving_force_id',
        'target_driving_force_id',
        'score',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Ramsey\Uuid\Uuid::uuid4();
            }
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'source_driving_force_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'target_driving_force_id');
    }
}
