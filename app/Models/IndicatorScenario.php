<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expected direction of an early warning indicator in one quadrant (roadmap R1.9). Tenant scope
 * comes through the indicator.
 *
 * @property int $early_warning_indicator_id
 * @property int $scenario_quadrant_id
 * @property string $expected_direction
 * @property string|null $note
 */
class IndicatorScenario extends Model
{
    public $timestamps = false;

    protected $fillable = ['early_warning_indicator_id', 'scenario_quadrant_id', 'expected_direction', 'note'];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EarlyWarningIndicator::class, 'early_warning_indicator_id');
    }

    public function quadrant(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuadrant::class, 'scenario_quadrant_id');
    }
}
