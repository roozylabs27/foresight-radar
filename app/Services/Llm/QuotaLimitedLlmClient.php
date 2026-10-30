<?php

namespace App\Services\Llm;

use App\Services\TenantContext;

/**
 * Wraps any client so every call made inside a tenant context counts against
 * that tenant's monthly token quota, and is refused once the quota is spent
 * (TENANT-008). Callers already treat LlmException as "use the non-AI path".
 * Outside a tenant context (console, operator without a workspace) nothing is
 * counted.
 */
final class QuotaLimitedLlmClient implements StructuredLlmClient
{
    public function __construct(
        private readonly StructuredLlmClient $inner,
        private readonly TenantLlmQuota $quota,
    ) {}

    public static function wrap(StructuredLlmClient $client): StructuredLlmClient
    {
        return $client instanceof self ? $client : new self($client, app(TenantLlmQuota::class));
    }

    /** The wrapped provider client. */
    public function inner(): StructuredLlmClient
    {
        return $this->inner;
    }

    public function isAvailable(): bool
    {
        return $this->inner->isAvailable();
    }

    public function provider(): string
    {
        return $this->inner->provider();
    }

    public function label(): string
    {
        return $this->inner->label();
    }

    public function model(): string
    {
        return $this->inner->model();
    }

    public function generateJson(LlmRequest $request): LlmResponse
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant && $this->quota->isExceeded($tenant)) {
            throw new LlmException('quota_exceeded', "Tenant {$tenant->id} reached its monthly token quota.");
        }

        $response = $this->inner->generateJson($request);

        if ($tenant) {
            $this->quota->record($tenant, $response->usage);
        }

        return $response;
    }

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return $this->generateJson($request);
    }
}
