<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * A comment on an exercise artefact (roadmap R2.2).
 *
 * @property int $id
 * @property int $project_id
 * @property string $body
 * @property int|null $author_id
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 */
class Comment extends Model
{
    use BelongsToTenant;

    /** Artefacts that take comments, by the type name the client sends. */
    public const TYPES = [
        'force' => ProjectDrivingForce::class,
        'uncertainty' => CriticalUncertainty::class,
        'quadrant' => ScenarioQuadrant::class,
        'response' => StrategicResponse::class,
    ];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'body',
        'author_id',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($comment) {
            if (empty($comment->uuid)) {
                $comment->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
