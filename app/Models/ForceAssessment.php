<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * Impact and uncertainty (1–10) of one driving force within one exercise
 * (roadmap R1.1). The organization-wide rating in driving_force_ratings is
 * never changed from here.
 *
 * @property int $project_driving_force_id
 * @property string $kind
 * @property int|null $assessor_id
 * @property int $impact
 * @property int $uncertainty
 * @property string|null $impact_rationale
 * @property string|null $uncertainty_rationale
 * @property string $origin
 */
class ForceAssessment extends Model
{
    use BelongsToTenant;

    public const CONSENSUS = 'consensus';

    /** A score proposed by AI (roadmap R2.4); applies only when the coordinator accepts it. */
    public const AI_SUGGESTION = 'ai_suggestion';

    /** One participant's own score (roadmap R2.1); never plotted, never part of gate G3. */
    public const INDIVIDUAL = 'individual';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_driving_force_id',
        'kind',
        'assessor_id',
        'impact',
        'uncertainty',
        'impact_rationale',
        'uncertainty_rationale',
        'origin',
        'generation_run_id',
        'cited_signal_ids',
        'updated_by',
    ];

    protected $casts = [
        'impact' => 'integer',
        'uncertainty' => 'integer',
        'cited_signal_ids' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($assessment) {
            if (empty($assessment->uuid)) {
                $assessment->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function projectForce(): BelongsTo
    {
        return $this->belongsTo(ProjectDrivingForce::class, 'project_driving_force_id');
    }
}
