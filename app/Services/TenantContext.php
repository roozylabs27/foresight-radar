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

    public function reset(): void
    {
        $this->tenant = null;
        $this->tenantId = null;
        $this->superuserBypass = false;
    }
}
