<?php

namespace App\Services;

use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    private ?int $tenantId = null;

    private bool $superuserBypass = false;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenantId = $tenant?->id;
    }

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        if ($this->tenant && $this->tenant->id !== $tenantId) {
            $this->tenant = null;
        }
    }

    public function getTenant(): ?Tenant
    {
        if ($this->tenant === null && $this->tenantId !== null) {
            $this->tenant = Tenant::find($this->tenantId);
        }

        return $this->tenant;
    }

    public function getTenantId(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        return $this->tenant?->id;
    }

    public function setSuperuserBypass(bool $bypass): void
    {
        $this->superuserBypass = $bypass;
    }

    public function isSuperuserBypass(): bool
    {
        return $this->superuserBypass;
    }

    /**
     * Run $callback as $tenant, then put back whatever context was active before,
     * so a job run inline (sync queue) does not change the dispatching request's tenant.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        [$previousTenant, $previousTenantId, $previousBypass] = [$this->tenant, $this->tenantId, $this->superuserBypass];

        $this->setTenant($tenant);
        $this->superuserBypass = false;

        try {
            return $callback();
        } finally {
            [$this->tenant, $this->tenantId, $this->superuserBypass] = [$previousTenant, $previousTenantId, $previousBypass];
        }
    }

    public function reset(): void
    {
        $this->tenant = null;
        $this->tenantId = null;
        $this->superuserBypass = false;
    }
}
