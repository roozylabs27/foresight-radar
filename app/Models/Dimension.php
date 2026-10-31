<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A driving force category (App\Support\DimensionTaxonomy lists the eleven).
 */
class Dimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'code',
        'environment_id',
        'name',
        'sort_order',
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function driving_forces(): HasMany
    {
        return $this->hasMany(DrivingForce::class);
    }

    /**
     * Method order: macro categories first, then mezzo. Rows without a position keep id order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('sort_order = 0')->orderBy('sort_order')->orderBy('id');
    }
}
