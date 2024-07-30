<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrivingForceRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'driving_force_id',
        'status_action_id',
        'priority_id',
        'impact_analysis',
        'uncertainty_analysis',
    ];
}
