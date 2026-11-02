<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * One AI call (roadmap R1.12): what it was for, which provider, model and prompt version ran, on
 * which inputs, and how it ended. Artefacts it produced reference it via `generation_run_id`.
 *
 * @property int $id
 * @property int|null $project_id
 * @property string $purpose
 * @property string|null $provider
 * @property string|null $model
 * @property string|null $prompt_version
 * @property array<string, mixed>|null $input_refs
 * @property string $status
 * @property string|null $error
 * @property array<string, mixed>|null $usage
 * @property int|null $duration_ms
 * @property int|null $created_by
 */
class AiGenerationRun extends Model
{
    use BelongsToTenant;

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /** The model answered but the answer was refused (e.g. citations outside the offered evidence). */
    public const REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'project_id',
        'purpose',
        'provider',
        'model',
        'prompt_version',
        'input_refs',
        'status',
        'error',
        'usage',
        'duration_ms',
        'created_by',
    ];

    protected $casts = [
        'input_refs' => 'array',
        'usage' => 'array',
        'duration_ms' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($run) {
            if (empty($run->uuid)) {
                $run->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ScenarioPlanningProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
