<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Ramsey\Uuid\Uuid;

/**
 * An opportunity or challenge of one future (roadmap R1.7, step 7), linked to exercise forces and
 * to conditions of the same quadrant. An imported narrative text has no type until reviewed.
 *
 * @property int $id
 * @property int $scenario_quadrant_id
 * @property string|null $type
 * @property string $title
 * @property string|null $description
 * @property string|null $impact_level
 * @property int|null $priority
 * @property string $origin
 * @property string $review_status
 */
class ScenarioImplication extends Model
{
    use BelongsToTenant;

    public const TYPES = ['opportunity', 'challenge'];

    public const IMPACT_LEVELS = ['low', 'medium', 'high'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'scenario_quadrant_id',
        'type',
        'title',
        'description',
        'impact_level',
        'priority',
        'origin',
        'review_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($implication) {
            if (empty($implication->uuid)) {
                $implication->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function quadrant(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuadrant::class, 'scenario_quadrant_id');
    }

    public function projectForces(): BelongsToMany
    {
        return $this->belongsToMany(ProjectDrivingForce::class, 'implication_links', 'scenario_implication_id', 'project_driving_force_id');
    }

    public function conditions(): BelongsToMany
    {
        return $this->belongsToMany(ScenarioCondition::class, 'implication_links', 'scenario_implication_id', 'scenario_condition_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
