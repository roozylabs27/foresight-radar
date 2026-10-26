<?php

namespace App\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isSuperuserBypass()) {
            return;
        }

        $tenantId = $context->getTenantId();

        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        } elseif (! app()->runningInConsole()) {
            // Defensive starvation: In web/API request without tenant context, return 0 rows
            $builder->whereRaw('0 = 1');
        }
    }
}
