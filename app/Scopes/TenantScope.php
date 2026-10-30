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
        } elseif ($context->isWorkspaceRequired() || ! app()->runningInConsole()) {
            // Defensive starvation: a web/API request without tenant context, or a platform
            // operator who has not picked a workspace (TENANT-007), sees 0 rows.
            $builder->whereRaw('0 = 1');
        }
    }
}
