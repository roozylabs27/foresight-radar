<?php

namespace App\Models;

use App\Enums\LlmJobStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

/**
 * A 2x2 scenario matrix. Legacy scenarios use one driving force per axis; the
 * framework of an exercise (roadmap R0.5, `project_id` set) uses its two
 * approved critical uncertainties and has its own approval (gate G5).
 * Quadrant positions are two letters: axis A pole then axis B pole (H = high, L = low).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property int|null $project_id
 * @property string|null $framework_status
 * @property LlmJobStatus|null $generation_status
 */
class Scenario extends Model
{
    use BelongsToTenant;

    public const POSITIONS = ['HH', 'HL', 'LH', 'LL'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'name',
        'focal_question',
        'axis_a_force_id',
        'axis_b_force_id',
        'axis_a_uncertainty_id',
        'axis_b_uncertainty_id',
        'axis_a_low_label',
        'axis_a_high_label',
        'axis_b_low_label',
        'axis_b_high_label',
        'status',
        'framework_status',
        'approved_by',
        'approved_at',
        'stale_since',
        'stale_reason',
        'stale_acknowledged_by',
        'stale_acknowledged_at',
        'provider',
        'model',
        'prompt_version',
        'input_signal_ids',
        'generated_at',
        'generation_status',
        'generation_error',
        'generation_notice',
        'generation_run_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'input_signal_ids' => 'array',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'stale_since' => 'datetime',
        'stale_acknowledged_at' => 'datetime',
        'generation_status' => LlmJobStatus::class,
    ];

    protected static function booted()
    {
        static::creating(function ($scenario) {
            if (empty($scenario->uuid)) {
                $scenario->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function axis_a(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'axis_a_force_id')->withTrashed();
    }

    public function axis_b(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'axis_b_force_id')->withTrashed();
    }

    public function axis_a_uncertainty(): BelongsTo
    {
        return $this->belongsTo(CriticalUncertainty::class, 'axis_a_uncertainty_id');
    }

    public function axis_b_uncertainty(): BelongsTo
    {
        return $this->belongsTo(CriticalUncertainty::class, 'axis_b_uncertainty_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isFramework(): bool
    {
        return $this->project_id !== null;
    }

    public function isFrameworkApproved(): bool
    {
        return $this->framework_status === 'approved';
    }

    /** The uncertainty behind axis A or B of a framework; null for legacy scenarios. */
    public function axisUncertainty(string $axis): ?CriticalUncertainty
    {
        return $axis === 'A' ? $this->axis_a_uncertainty : $this->axis_b_uncertainty;
    }

    /** The force behind axis A or B of a legacy scenario; null once hard-deleted or for frameworks. */
    public function axisForce(string $axis): ?DrivingForce
    {
        return $axis === 'A' ? $this->axis_a : $this->axis_b;
    }

    /** Axis name: the uncertainty's name, else the force keyword, else null when the force is gone. */
    public function axisName(string $axis): ?string
    {
        return $this->axisUncertainty($axis)?->name ?? $this->axisForce($axis)?->keyword;
    }

    /**
     * Driving forces whose signals are the evidence for an axis: the uncertainty's member forces,
     * or the legacy axis force.
     *
     * @return array<int, int>
     */
    public function axisForceIds(string $axis): array
    {
        $uncertainty = $this->axisUncertainty($axis);
        if ($uncertainty) {
            return $uncertainty->projectForces()->pluck('project_driving_forces.driving_force_id')->map(fn ($id) => (int) $id)->all();
        }
        $id = $axis === 'A' ? $this->axis_a_force_id : $this->axis_b_force_id;

        return $id ? [(int) $id] : [];
    }

    /** "{pole A} · {pole B}", the name a quadrant gets until the team renames it. */
    public function defaultTitle(string $position): string
    {
        return implode(' · ', $this->poles($position));
    }

    public function quadrants(): HasMany
    {
        return $this->hasMany(ScenarioQuadrant::class)->orderByRaw("CASE position WHEN 'HH' THEN 1 WHEN 'HL' THEN 2 WHEN 'LH' THEN 3 ELSE 4 END");
    }

    /**
     * Pole labels of one quadrant, e.g. ['Regulasi ketat', 'Adopsi cepat'].
     *
     * @return array{0: string, 1: string}
     */
    public function poles(string $position): array
    {
        return [
            $position[0] === 'H' ? $this->axis_a_high_label : $this->axis_a_low_label,
            $position[1] === 'H' ? $this->axis_b_high_label : $this->axis_b_low_label,
        ];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
