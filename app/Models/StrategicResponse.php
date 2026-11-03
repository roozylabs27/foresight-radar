<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * A strategic response of one exercise (roadmap R1.8, step 8). Its fit is rated per quadrant;
 * approval (gate G7) makes it a decision.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $rationale
 * @property int|null $priority
 * @property int|null $owner_id
 * @property string|null $depends_on
 * @property string $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string $origin
 * @property Carbon|null $stale_since
 * @property string|null $stale_reason
 */
class StrategicResponse extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['proposed', 'approved', 'rejected'];

    public const FITS = ['applies', 'partial', 'not_applicable'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'title',
        'description',
        'rationale',
        'initiatives',
        'priority',
        'owner_id',
        'depends_on',
        'status',
        'approved_by',
        'approved_at',
        'origin',
        'stale_since',
        'stale_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'approved_at' => 'datetime',
        'stale_since' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($response) {
            if (empty($response->uuid)) {
                $response->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function implications(): BelongsToMany
    {
        return $this->belongsToMany(ScenarioImplication::class, 'response_implications');
    }

    /** Step 5 conditions this response relies on (roadmap R2.9). */
    public function conditions(): BelongsToMany
    {
        return $this->belongsToMany(ScenarioCondition::class, 'response_conditions');
    }

    public function fits(): HasMany
    {
        return $this->hasMany(ResponseScenario::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
