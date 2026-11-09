<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * One observation of an early warning indicator (roadmap R2.3), confirmed by the person who
 * recorded it.
 *
 * @property int $id
 * @property int $early_warning_indicator_id
 * @property Carbon $observed_at
 * @property float|null $value_numeric
 * @property string|null $value_text
 * @property string|null $direction
 * @property bool $crossed_threshold
 * @property int|null $signal_id
 * @property string|null $note
 * @property int|null $recorded_by
 * @property string $origin
 * @property string $review_status
 */
class IndicatorObservation extends Model
{
    use BelongsToTenant;

    /** Directions an observation can show; compared with each quadrant's expected direction. */
    public const DIRECTIONS = ['rising', 'falling', 'stable'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'early_warning_indicator_id',
        'observed_at',
        'value_numeric',
        'value_text',
        'direction',
        'crossed_threshold',
        'signal_id',
        'note',
        'origin',
        'review_status',
        'recorded_by',
    ];

    protected $casts = [
        'observed_at' => 'date',
        'value_numeric' => 'float',
        'crossed_threshold' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($observation) {
            if (empty($observation->uuid)) {
                $observation->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EarlyWarningIndicator::class, 'early_warning_indicator_id');
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
