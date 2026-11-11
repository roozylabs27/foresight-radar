<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One threshold crossing of an early warning indicator (audit U4), recorded once per observation.
 * `numeric` crossings are decided by the server; `manual` ones are a person's flag on a qualitative
 * indicator. A deleted observation closes the event (`closed_at`); the event stays as history.
 *
 * @property int $id
 * @property string $uuid
 * @property int $early_warning_indicator_id
 * @property int|null $indicator_observation_id
 * @property string $kind
 * @property string|null $operator
 * @property float|null $threshold_value
 * @property float|null $observed_value
 * @property Carbon $observed_at
 * @property Carbon|null $closed_at
 */
class IndicatorThresholdEvent extends Model
{
    use BelongsToTenant;

    public const KINDS = ['numeric', 'manual'];

    protected $fillable = [
        'tenant_id', 'uuid', 'early_warning_indicator_id', 'indicator_observation_id', 'kind', 'operator',
        'threshold_value', 'observed_value', 'observed_at', 'closed_at',
    ];

    protected $casts = [
        'threshold_value' => 'float',
        'observed_value' => 'float',
        'observed_at' => 'date',
        'closed_at' => 'datetime',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EarlyWarningIndicator::class, 'early_warning_indicator_id');
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(IndicatorObservation::class, 'indicator_observation_id');
    }
}
