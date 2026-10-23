<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Ramsey\Uuid\Uuid;

class UserLlmSetting extends Model
{
    use HasFactory;

    protected $table = 'user_llm_settings';

    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'label',
        'api_key',
        'model',
        'base_url',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Uuid::uuid4();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Encrypt the API key before persisting.
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = ! empty($value) ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt the API key when retrieving.
     */
    public function getApiKeyAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mask the API key for safe frontend display.
     */
    public function getMaskedApiKeyAttribute(): ?string
    {
        $key = $this->api_key;
        if (empty($key)) {
            return null;
        }

        $length = strlen($key);
        if ($length <= 8) {
            return '••••••••';
        }

        return substr($key, 0, 4).'••••••••'.substr($key, -4);
    }

    /**
     * Return safe array representation with masked API key for frontend.
     */
    public function toMaskedArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'label' => $this->label,
            'model' => $this->model,
            'base_url' => $this->base_url,
            'is_active' => (bool) $this->is_active,
            'has_api_key' => ! empty($this->api_key),
            'masked_api_key' => $this->masked_api_key,
            'settings' => $this->settings ?? [],
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
