<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * One person taking part in an exercise (roadmap R2.2). Viewers read; the other roles give input.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $role
 * @property string|null $division
 */
class ProjectParticipant extends Model
{
    use BelongsToTenant;

    public const ROLES = ['coordinator', 'contributor', 'expert', 'viewer'];

    /** Roles that give input (own scores, comments). */
    public const CONTRIBUTING = ['coordinator', 'contributor', 'expert'];

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'user_id',
        'role',
        'division',
        'created_by',
    ];

    protected static function booted()
    {
        static::creating(function ($participant) {
            if (empty($participant->uuid)) {
                $participant->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
