<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'environment_id',
        'name'
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function driving_forces() : HasMany
    {
        return $this->hasMany(DrivingForce::class);
    }
}
