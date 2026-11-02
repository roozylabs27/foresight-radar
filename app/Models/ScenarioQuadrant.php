<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class ScenarioQuadrant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'scenario_id',
        'position',
        'title',
        'summary',
        'narrative',
        'implications',
        'early_indicators',
        'cited_signal_ids',
        'is_ai_generated',
        'origin',
        'provider',
        'generation_run_id',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'stale_since',
        'stale_reason',
        'edited_by',
        'edited_at',
    ];

    protected $casts = [
        'early_indicators' => 'array',
        'cited_signal_ids' => 'array',
        'is_ai_generated' => 'boolean',
        'edited_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'stale_since' => 'datetime',
    ];

    /** Narrative review states; `approved` is gate G6 (roadmap R1.6). */
    public const REVIEW_STATUSES = ['draft', 'reviewed', 'approved'];

    protected static function booted()
    {
        static::creating(function ($quadrant) {
            if (empty($quadrant->uuid)) {
                $quadrant->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /** Step 5 conditions, in the team's order (roadmap R1.5). */
    public function conditions(): HasMany
    {
        return $this->hasMany(ScenarioCondition::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Step 7 opportunities and challenges (roadmap R1.7): by priority, then entry order. */
    public function implicationItems(): HasMany
    {
        return $this->hasMany(ScenarioImplication::class)->orderByRaw('priority IS NULL')->orderBy('priority')->orderBy('id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Reviewed or approved: a regeneration would throw that review away. */
    public function isReviewed(): bool
    {
        return in_array($this->review_status, ['reviewed', 'approved'], true);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
