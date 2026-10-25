<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string $status
 * @property string $plan
 * @property int $max_users
 * @property int $storage_quota_mb
 * @property int $llm_monthly_token_quota
 * @property array|null $settings
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'domain',
        'status',
        'plan',
        'max_users',
        'storage_quota_mb',
        'llm_monthly_token_quota',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'max_users' => 'integer',
        'storage_quota_mb' => 'integer',
        'llm_monthly_token_quota' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }

    public function drivingForces(): HasMany
    {
        return $this->hasMany(DrivingForce::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class);
    }

    public function tenantLlmSetting(): HasOne
    {
        return $this->hasOne(TenantLlmSetting::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
