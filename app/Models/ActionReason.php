<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'driving_force_rating_id',
        'status_action_id',
        'date',
        'reason'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function driving_force_rating() : BelongsTo
    {
        return $this->belongsTo(DrivingForceRating::class, 'driving_force_rating_id');
    }

    public function status_action() : BelongsTo
    {
        return $this->belongsTo(StatusAction::class, 'status_action_id');
    }
}
