<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a rating each time its scores change, written by
 * DrivingForceRatingObserver. Rows are never updated.
 */
class DrivingForceRatingHistory extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'changed_at';

    protected $fillable = [
        'tenant_id',
        'driving_force_rating_id',
        'driving_force_id',
        'event',
        'time_horizon_id',
        'status_action_id',
        'priority_id',
        'impact_analysis',
        'uncertainty_analysis',
        'changed_by',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'impact_analysis' => 'integer',
        'uncertainty_analysis' => 'integer',
    ];

    public function rating(): BelongsTo
    {
        return $this->belongsTo(DrivingForceRating::class, 'driving_force_rating_id');
    }

    public function changed_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
