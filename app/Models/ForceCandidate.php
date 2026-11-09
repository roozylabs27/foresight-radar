<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * A driving force proposed by AI from a group of accepted signals (roadmap R2.4).
 *
 * @property int $id
 * @property int $project_id
 * @property string $keyword
 * @property string $description
 * @property int|null $dimension_id
 * @property array<int, int> $signal_ids
 * @property string|null $rationale
 * @property string $status
 * @property int|null $driving_force_id
 * @property Carbon|null $reviewed_at
 */
class ForceCandidate extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['proposed', 'accepted', 'rejected'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'keyword',
        'description',
        'dimension_id',
        'signal_ids',
        'rationale',
        'status',
        'generation_run_id',
        'driving_force_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'signal_ids' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($candidate) {
            if (empty($candidate->uuid)) {
                $candidate->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
