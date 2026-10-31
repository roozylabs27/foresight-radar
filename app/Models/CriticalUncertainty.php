<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * A critical uncertainty of one exercise (roadmap R0.4): one or more of its
 * driving forces under a name, with two opposing poles. Two approved ones are
 * the axes of the scenario framework (gate G4).
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $low_pole_label
 * @property string $high_pole_label
 * @property string $selection_rationale
 * @property string|null $axis
 * @property string $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class CriticalUncertainty extends Model
{
    use BelongsToTenant;

    public const AXES = ['A', 'B'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'name',
        'description',
        'low_pole_label',
        'high_pole_label',
        'selection_rationale',
        'axis',
        'status',
        'approved_by',
        'approved_at',
        'origin',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($uncertainty) {
            if (empty($uncertainty->uuid)) {
                $uncertainty->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    /** The exercise forces this uncertainty groups. */
    public function projectForces(): BelongsToMany
    {
        return $this->belongsToMany(ProjectDrivingForce::class, 'critical_uncertainty_forces', 'critical_uncertainty_id', 'project_driving_force_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
