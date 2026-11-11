<?php

namespace App\Support;

use App\Services\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for tenant-owned tables. A plain `exists:` rule queries the
 * table directly, outside Eloquent's TenantScope, so it would accept (and
 * reveal) another tenant's ids. Only the platform bypass has no tenant id and
 * keeps the unscoped check.
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        $tenantId = app(TenantContext::class)->getTenantId();

        return Rule::exists($table, $column)->when($tenantId !== null, fn (Exists $rule) => $rule->where('tenant_id', $tenantId));
    }

    /**
     * A dimension of the organisation that takes new data: not archived, except `$keep` (the
     * dimension a record already sits on, which an edit may leave in place).
     */
    public static function activeDimension(?int $keep = null): Exists
    {
        return self::exists('dimensions')->where(fn ($query) => $query->whereNull('archived_at')->when($keep !== null, fn ($q) => $q->orWhere('id', $keep)));
    }
}
