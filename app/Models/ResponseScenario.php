<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How well a strategic response fits one quadrant (roadmap R1.8): applies, partial or not_applicable.
 * Tenant scope comes through the response.
 *
 * @property int $strategic_response_id
 * @property int $scenario_quadrant_id
 * @property string $fit
 */
class ResponseScenario extends Model
{
    public $timestamps = false;

    protected $fillable = ['strategic_response_id', 'scenario_quadrant_id', 'fit'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(StrategicResponse::class, 'strategic_response_id');
    }

    public function quadrant(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuadrant::class, 'scenario_quadrant_id');
    }
}
