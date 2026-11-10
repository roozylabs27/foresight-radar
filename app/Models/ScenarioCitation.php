<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signal cited by one quadrant narrative (roadmap R2.7), with the kind and position of the
 * statement that cites it when the narrative is typed.
 *
 * @property int $id
 * @property int $scenario_quadrant_id
 * @property int $signal_id
 * @property string|null $ref
 * @property string|null $statement_kind
 * @property int|null $statement_index
 */
class ScenarioCitation extends Model
{
    use BelongsToTenant;

    /** Statement kinds, from evidence to the model's own reading. */
    public const KINDS = ['fact', 'analysis', 'assumption', 'interpretation'];

    protected $fillable = [
        'tenant_id',
        'scenario_quadrant_id',
        'signal_id',
        'ref',
        'statement_kind',
        'statement_index',
    ];

    protected $casts = [
        'statement_index' => 'integer',
    ];

    public function quadrant(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuadrant::class, 'scenario_quadrant_id');
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}
