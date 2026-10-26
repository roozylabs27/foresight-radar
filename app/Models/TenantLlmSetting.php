<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $provider
 * @property string|null $model
 * @property string|null $api_key
 * @property string|null $base_url
 * @property float $temperature
 * @property int $max_tokens
 * @property bool $is_enabled
 * @property bool $allow_user_byok
 * @property int $monthly_token_usage
 */
class TenantLlmSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider',
        'model',
        'api_key',
        'base_url',
        'temperature',
        'max_tokens',
        'is_enabled',
        'allow_user_byok',
        'monthly_token_usage',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'is_enabled' => 'boolean',
        'allow_user_byok' => 'boolean',
        'monthly_token_usage' => 'integer',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
