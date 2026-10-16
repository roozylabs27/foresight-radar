<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

/**
 * A 2x2 scenario matrix built on two driving forces. Quadrant positions are
 * two letters: axis A pole then axis B pole (H = high, L = low).
 */
class Scenario extends Model
{
    public const POSITIONS = ['HH', 'HL', 'LH', 'LL'];

    protected $fillable = [
        'uuid',
        'name',
        'focal_question',
        'axis_a_force_id',
        'axis_b_force_id',
        'axis_a_low_label',
        'axis_a_high_label',
        'axis_b_low_label',
        'axis_b_high_label',
        'status',
        'provider',
        'model',
        'prompt_version',
        'input_signal_ids',
        'generated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'input_signal_ids' => 'array',
        'generated_at' => 'datetime',
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
        return $this->belongsTo(DrivingForce::class, 'axis_a_force_id');
    }

    public function axis_b(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'axis_b_force_id');
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
