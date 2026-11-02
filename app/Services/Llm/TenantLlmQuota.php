<?php

namespace App\Services\Llm;

use App\Models\Tenant;
use App\Models\TenantLlmUsage;
use Illuminate\Support\Facades\DB;

/**
 * Monthly LLM token budget per tenant (tenants.llm_monthly_token_quota).
 * A quota of 0 means unlimited. Usage is what providers report; a call that
 * starts under the quota may finish over it, and the next call is refused.
 */
class TenantLlmQuota
{
    public function period(): string
    {
        return now()->format('Y-m');
    }

    public function used(Tenant $tenant): int
    {
        $usage = TenantLlmUsage::where('tenant_id', $tenant->id)->where('period', $this->period())->first();

        return $usage?->totalTokens() ?? 0;
    }

    public function isUnlimited(Tenant $tenant): bool
    {
        return (int) $tenant->llm_monthly_token_quota === 0;
    }

    public function isExceeded(Tenant $tenant): bool
    {
        return ! $this->isUnlimited($tenant) && $this->used($tenant) >= (int) $tenant->llm_monthly_token_quota;
    }

    /**
     * @param  array<string, int|null>  $usage  as reported in LlmResponse::$usage
     */
    public function record(Tenant $tenant, array $usage): void
    {
        $input = (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $period = $this->period();

        // One atomic statement, so concurrent jobs of the same tenant never lose an increment.
        DB::transaction(function () use ($tenant, $period, $input, $output) {
            TenantLlmUsage::firstOrCreate(['tenant_id' => $tenant->id, 'period' => $period]);
            TenantLlmUsage::where('tenant_id', $tenant->id)->where('period', $period)->update([
                'input_tokens' => DB::raw('input_tokens + '.$input),
                'output_tokens' => DB::raw('output_tokens + '.$output),
                'requests' => DB::raw('requests + 1'),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @return array{period: string, used: int, quota: int, unlimited: bool, exceeded: bool}
     */
    public function summary(Tenant $tenant): array
    {
        return [
            'period' => $this->period(),
            'used' => $this->used($tenant),
            'quota' => (int) $tenant->llm_monthly_token_quota,
            'unlimited' => $this->isUnlimited($tenant),
            'exceeded' => $this->isExceeded($tenant),
        ];
    }
}
