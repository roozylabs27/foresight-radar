<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A driving force category of one organisation. Every organisation starts from the default
 * taxonomy (App\Support\DimensionTaxonomy) and may rename, reorder, add and archive its own.
 * `code` is fixed once created: seed data and AI prompts refer to it.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $code
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property string|null $color
 */
class Dimension extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'uuid',
        'code',
        'environment_id',
        'name',
        'sort_order',
        'archived_at',
        'color',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function driving_forces(): HasMany
    {
        return $this->hasMany(DrivingForce::class);
    }

    /** Colour keys an organisation may give a dimension; the frontend maps them to palette tokens. */
    public const COLORS = ['blue', 'teal', 'green', 'lime', 'amber', 'orange', 'red', 'pink', 'purple', 'indigo', 'cyan', 'slate'];

    /**
     * Display order: by environment (macro first, then mezzo, as the organisation ordered them),
     * then by position within it. Rows without a position keep id order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy(Environment::withoutGlobalScopes()->select('sort_order')->whereColumn('environments.id', 'dimensions.environment_id')->limit(1))
            ->orderByRaw('dimensions.sort_order = 0')
            ->orderBy('dimensions.sort_order')
            ->orderBy('dimensions.id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /** Dimensions that take new forces and signals; archived ones stay on existing data. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * The organisation's dimensions in display order, as select options with what the radar and
     * forms need: archived ones stay (old data sits on them) and are flagged.
     *
     * @return Collection<int, array{value: int, label: string, archived: bool, color: string|null}>
     */
    public static function options(): Collection
    {
        return self::ordered()->get()->map(fn (Dimension $dimension) => [
            'value' => $dimension->id,
            'label' => $dimension->name,
            'archived' => $dimension->isArchived(),
            'color' => $dimension->color,
        ])->values();
    }
}
