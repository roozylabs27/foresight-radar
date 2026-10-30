<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token usage of one tenant in one calendar month. Always queried by explicit
 * tenant id (jobs and operators read it too), so it carries no tenant scope.
 *
 * @property int $tenant_id
 * @property string $period
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $requests
 */
class TenantLlmUsage extends Model
{
    protected $fillable = ['tenant_id', 'period', 'input_tokens', 'output_tokens', 'requests'];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'requests' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function totalTokens(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
