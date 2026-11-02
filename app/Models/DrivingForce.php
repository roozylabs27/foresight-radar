<?php

namespace App\Models;

use App\Enums\DrivingForceStatus;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrivingForce extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'dimension_id',
        'pic',
        'created_by',
        'updated_by',
        'keyword',
        'description',
        'status',
        'remark',
        'approved_at',
        'closed_at',
    ];

    protected static function booted()
    {
        static::deleting(function ($drivingForce) {
            if ($drivingForce->isForceDeleting() && $drivingForce->usedByApprovedFramework()) {
                throw ValidationException::withMessages(['driving_force' => 'Driving force ini dipakai kerangka skenario yang sudah disetujui, jadi tidak bisa dihapus permanen.']);
            }
            if ($drivingForce->isForceDeleting()) {
                $drivingForce->rating()->withTrashed()->forceDelete();
            } else {
                $drivingForce->rating()->delete();
            }
        });

        static::restoring(function ($drivingForce) {
            $drivingForce->rating()->withTrashed()->restore();
        });
    }

    /** Member of an axis uncertainty of an approved exercise framework (gate G5). */
    public function usedByApprovedFramework(): bool
    {
        $uncertaintyIds = DB::table('critical_uncertainty_forces')
            ->join('project_driving_forces', 'project_driving_forces.id', '=', 'critical_uncertainty_forces.project_driving_force_id')
            ->where('project_driving_forces.driving_force_id', $this->id)
            ->pluck('critical_uncertainty_forces.critical_uncertainty_id');

        return $uncertaintyIds->isNotEmpty() && Scenario::withoutGlobalScopes()
            ->where('framework_status', 'approved')
            ->where(fn ($q) => $q->whereIn('axis_a_uncertainty_id', $uncertaintyIds)->orWhereIn('axis_b_uncertainty_id', $uncertaintyIds))
            ->exists();
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_id');
    }

    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function pic_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic')->withTrashed();
    }

    public function updated_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function rating(): HasOne
    {
        return $this->hasOne(DrivingForceRating::class);
    }

    public function source_signal(): HasOne
    {
        return $this->hasOne(Signal::class, 'created_driving_force_id');
    }

    public function supporting_signals(): BelongsToMany
    {
        return $this->belongsToMany(Signal::class, 'signal_driving_force')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->translatedFormat('D, d F Y')
        );
    }

    public function approvedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->translatedFormat('d F Y') : null
        );
    }

    public function closedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->translatedFormat('D, d F Y') : null
        );
    }

    public function getStatusEnum(): DrivingForceStatus
    {
        $statusStr = $this->status ?? 'PENDING';

        return DrivingForceStatus::tryFrom($statusStr) ?? DrivingForceStatus::PENDING;
    }

    public function transitionTo(DrivingForceStatus|string $targetStatus): self
    {
        $targetEnum = $this->getStatusEnum()->validateTransitionTo($targetStatus);
        $this->status = $targetEnum->value;

        return $this;
    }

    /** Signals that support this force (roadmap R1.3: signals contribute to forces). */
    public function signals(): BelongsToMany
    {
        return $this->belongsToMany(Signal::class, 'signal_driving_force')->withPivot('notes')->withTimestamps();
    }

    /** The force this duplicate was merged into (a tombstone keeps the pointer). */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(DrivingForce::class, 'merged_into_id')->withTrashed();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
