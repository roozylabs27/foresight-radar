<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Ramsey\Uuid\Uuid;

/**
 * One condition of a future (roadmap R1.5, step 5): how an axis pole shows, how a non-axis
 * force behaves, or an assumption, in one quadrant of an exercise framework.
 *
 * @property int $id
 * @property int $scenario_quadrant_id
 * @property string $kind
 * @property string $text
 * @property string|null $axis
 * @property int|null $project_driving_force_id
 * @property int $sort_order
 * @property string $origin
 * @property string $review_status
 */
class ScenarioCondition extends Model
{
    use BelongsToTenant;

    public const KINDS = ['pole_expression', 'force_behaviour', 'assumption'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'scenario_quadrant_id',
        'kind',
        'text',
        'axis',
        'project_driving_force_id',
        'sort_order',
        'origin',
        'review_status',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::creating(function ($condition) {
            if (empty($condition->uuid)) {
                $condition->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function quadrant(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuadrant::class, 'scenario_quadrant_id');
    }

    public function projectForce(): BelongsTo
    {
        return $this->belongsTo(ProjectDrivingForce::class, 'project_driving_force_id');
    }

    /** Strategic responses that rely on this condition (roadmap R2.9). */
    public function responses(): BelongsToMany
    {
        return $this->belongsToMany(StrategicResponse::class, 'response_conditions');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
