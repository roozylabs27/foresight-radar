<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * A scenario planning exercise: one key focal issue and, in later steps, the
 * driving forces, critical uncertainties, scenarios, responses and indicators
 * that answer it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $name
 * @property string $focal_question
 * @property string $purpose
 * @property int $target_year
 * @property string $status
 * @property int|null $owner_user_id
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property int|null $created_by
 */
class ScenarioPlanningProject extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'monitoring', 'archived'];

    /** Changing any of these changes what the exercise is about, so it must be confirmed again. */
    public const FOCAL_FIELDS = ['focal_question', 'purpose', 'target_year'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'focal_question',
        'purpose',
        'target_year',
        'geography',
        'industry',
        'owner_unit',
        'owner_user_id',
        'background',
        'objectives',
        'status',
        'confirmed_by',
        'confirmed_at',
        'forces_confirmed_by',
        'forces_confirmed_at',
        'scores_confirmed_by',
        'scores_confirmed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_year' => 'integer',
        'confirmed_at' => 'datetime',
        'forces_confirmed_at' => 'datetime',
        'scores_confirmed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id')->withTrashed();
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Step 2: the driving forces of this exercise (route binding scopes `{projectForce}` to it). */
    public function projectForces(): HasMany
    {
        return $this->hasMany(ProjectDrivingForce::class, 'project_id');
    }

    public function forcesConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forces_confirmed_by')->withTrashed();
    }

    /** Gate G1: the key focal issue is confirmed. */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function scoresConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scores_confirmed_by')->withTrashed();
    }

    /** Gate G2: the driving force list is agreed. */
    public function forcesConfirmed(): bool
    {
        return $this->forces_confirmed_at !== null;
    }

    /** Gate G3: the impact and uncertainty scores are agreed. */
    public function scoresConfirmed(): bool
    {
        return $this->scores_confirmed_at !== null;
    }

    /** Step 3, second half: the critical uncertainties (route binding scopes `{uncertainty}` to it). */
    public function criticalUncertainties(): HasMany
    {
        return $this->hasMany(CriticalUncertainty::class, 'project_id');
    }

    /** Same relation under the name scoped route binding derives from `{uncertainty}`. */
    public function uncertainties(): HasMany
    {
        return $this->criticalUncertainties();
    }

    /** Gate G4: two critical uncertainties are approved as axis A and B. */
    public function uncertaintiesApproved(): bool
    {
        return $this->criticalUncertainties()->where('status', 'approved')->whereNotNull('axis')->count() === 2;
    }

    /**
     * Withdraw gate G4: approved uncertainties go back to proposed. Called whenever an upstream
     * gate (G1, G2, G3) is withdrawn, because the approval rested on it.
     */
    /** Step 9 early warning indicators (roadmap R1.9); also the scoped binding of `{indicator}`. */
    public function indicators(): HasMany
    {
        return $this->hasMany(EarlyWarningIndicator::class, 'project_id');
    }

    /** Step 8 strategic responses (roadmap R1.8). */
    public function strategicResponses(): HasMany
    {
        return $this->hasMany(StrategicResponse::class, 'project_id');
    }

    /** Alias for scoped route binding of `{response}`. */
    public function responses(): HasMany
    {
        return $this->strategicResponses();
    }

    /** The exercise's 2x2 framework (step 4), once saved. */
    public function framework(): HasOne
    {
        return $this->hasOne(Scenario::class, 'project_id');
    }

    public function frameworkApproved(): bool
    {
        return $this->framework()->where('framework_status', 'approved')->exists();
    }

    /** Gate G5 falls back to draft; called whenever an axis it rests on is withdrawn. */
    public function withdrawFrameworkApproval(): void
    {
        $this->framework()->where('framework_status', 'approved')->update(['framework_status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
    }

    public function withdrawUncertaintyApprovals(): void
    {
        $this->withdrawFrameworkApproval();
        $this->criticalUncertainties()->where('status', 'approved')->update([
            'status' => 'proposed', 'axis' => null, 'approved_by' => null, 'approved_at' => null,
        ]);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
