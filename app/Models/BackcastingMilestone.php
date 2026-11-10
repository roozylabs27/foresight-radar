<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * One step on the path back from the preferred future to now (roadmap R4.1, deck slides 2 and 26).
 *
 * @property int $id
 * @property int $project_id
 * @property int $year
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property int|null $owner_id
 * @property int|null $strategic_response_id
 * @property int|null $early_warning_indicator_id
 */
class BackcastingMilestone extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['planned', 'in_progress', 'done'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'year',
        'title',
        'description',
        'status',
        'owner_id',
        'strategic_response_id',
        'early_warning_indicator_id',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($milestone) {
            if (empty($milestone->uuid)) {
                $milestone->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(StrategicResponse::class, 'strategic_response_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EarlyWarningIndicator::class, 'early_warning_indicator_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
