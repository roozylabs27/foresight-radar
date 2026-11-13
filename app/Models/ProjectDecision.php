<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One gate decision of an exercise (roadmap R2.2), written by GateDecisionObserver.
 *
 * @property int $project_id
 * @property string $gate
 * @property string $action
 * @property string|null $subject
 * @property int|null $actor_id
 * @property string|null $artefact_type
 * @property int|null $artefact_id
 * @property string|null $rationale
 * @property Carbon $created_at
 */
class ProjectDecision extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const ACTIONS = ['approved', 'rejected', 'withdrawn'];

    /** Roadmap #13: exercise status changes are logged under this code, with the new status as action. */
    public const STATUS = 'ST';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'gate',
        'action',
        'subject',
        'actor_id',
        'artefact_type',
        'artefact_id',
        'rationale',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
