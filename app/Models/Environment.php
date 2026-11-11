<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of dimensions of one organisation (by default Macro and Mezzo).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property int $sort_order
 */
class Environment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'sort_order',
    ];

    public function dimensions(): HasMany
    {
        return $this->hasMany(Dimension::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('sort_order = 0')->orderBy('sort_order')->orderBy('id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
