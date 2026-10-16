<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class ScenarioQuadrant extends Model
{
    protected $fillable = [
        'uuid',
        'scenario_id',
        'position',
        'title',
        'narrative',
        'implications',
        'early_indicators',
        'cited_signal_ids',
        'is_ai_generated',
        'provider',
        'edited_by',
        'edited_at',
    ];

    protected $casts = [
        'early_indicators' => 'array',
        'cited_signal_ids' => 'array',
        'is_ai_generated' => 'boolean',
        'edited_at' => 'datetime',
    ];

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

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
