<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Ramsey\Uuid\Uuid;

/**
 * A driving force as used in one scenario planning exercise (roadmap R0.2):
 * whether it is included, what it means for this focal issue, and whether it
 * is a predetermined element. The force itself stays in the tenant's library.
 *
 * @property int $project_id
 * @property int $driving_force_id
 * @property string $inclusion_status
 * @property string|null $influence_note
 * @property bool $is_predetermined
 */
class ProjectDrivingForce extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['proposed', 'included', 'excluded'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'driving_force_id',
        'inclusion_status',
        'origin',
        'influence_note',
        'is_predetermined',
        'added_by',
    ];

    protected $casts = [
        'is_predetermined' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($link) {
            if (empty($link->uuid)) {
                $link->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function drivingForce(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'driving_force_id')->withTrashed();
    }

    /** The exercise's agreed score for this force (step 3). */
    public function consensus(): HasOne
    {
        return $this->hasOne(ForceAssessment::class, 'project_driving_force_id')->where('kind', ForceAssessment::CONSENSUS);
    }

    public function isIncluded(): bool
    {
        return $this->inclusion_status === 'included';
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
