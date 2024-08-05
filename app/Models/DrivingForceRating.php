<?php

namespace App\Models;

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

    public function priority_id(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
