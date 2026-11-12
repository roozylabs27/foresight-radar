<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * A quadrant narrative as it was before an edit or a regeneration replaced it (audit U3, AUD-03).
 * Written once, never changed.
 *
 * @property int $id
 * @property string $uuid
 * @property int $scenario_quadrant_id
 * @property string|null $title
 * @property string|null $summary
 * @property string|null $narrative
 * @property array|null $narrative_statements
 * @property array|null $cited_signal_ids
 * @property string|null $origin
 * @property string|null $provider
 * @property string|null $review_status
 * @property int|null $generation_run_id
 * @property string $reason
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
class ScenarioNarrativeRevision extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const REASONS = ['edit', 'regenerate'];

    protected $fillable = [
        'tenant_id', 'uuid', 'scenario_quadrant_id', 'title', 'summary', 'narrative', 'narrative_statements',
        'implications', 'early_indicators', 'cited_signal_ids', 'origin', 'provider', 'review_status',
        'generation_run_id', 'reason', 'user_id',
    ];

    protected $casts = [
        'narrative_statements' => 'array',
        'early_indicators' => 'array',
        'cited_signal_ids' => 'array',
    ];

    /** Keep the quadrant's current text before `$reason` replaces it; nothing to keep for an empty quadrant. */
    public static function capture(ScenarioQuadrant $quadrant, string $reason, ?int $userId): ?self
    {
        if (blank($quadrant->getOriginal('narrative'))) {
            return null;
        }

        return self::create([
            'uuid' => (string) Uuid::uuid4(),
            'tenant_id' => $quadrant->getAttribute('tenant_id'),
            'scenario_quadrant_id' => $quadrant->id,
            'reason' => $reason,
            'user_id' => $userId,
        ] + collect([
            'title', 'summary', 'narrative', 'narrative_statements', 'implications', 'early_indicators',
            'cited_signal_ids', 'origin', 'provider', 'review_status', 'generation_run_id',
        ])->mapWithKeys(fn ($column) => [$column => $quadrant->getOriginal($column)])->all());
    }

    public function quadrant(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuadrant::class, 'scenario_quadrant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
