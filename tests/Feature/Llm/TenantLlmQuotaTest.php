<?php

namespace Tests\Feature\Llm;

use App\Models\Tenant;
use App\Models\TenantLlmSetting;
use App\Models\TenantLlmUsage;
use App\Models\User;
use App\Models\UserLlmSetting;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\QuotaLimitedLlmClient;
use App\Services\Llm\StructuredLlmClient;
use App\Services\Llm\TenantLlmQuota;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for TENANT-008: the organization's AI key is used, and every
 * LLM call counts against the tenant's monthly token quota and stops once it
 * is spent. See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class TenantLlmQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'llm_monthly_token_quota' => 1000]);
    }

    public function test_calls_in_a_tenant_context_are_counted(): void
    {
        $client = QuotaLimitedLlmClient::wrap($this->stub(600, 150));

        $this->asTenant(fn () => $client->generateJson($this->request()));

        $usage = TenantLlmUsage::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(now()->format('Y-m'), $usage->period);
        $this->assertSame(750, $usage->totalTokens());
        $this->assertSame(1, $usage->requests);
    }

    public function test_calls_stop_once_the_quota_is_spent(): void
    {
        $inner = $this->stub(800, 300);
        $client = QuotaLimitedLlmClient::wrap($inner);

        // The first call starts under the quota and may finish over it.
        $this->asTenant(fn () => $client->generateJson($this->request()));

        try {
            $this->asTenant(fn () => $client->generateJson($this->request()));
            $this->fail('A call over the quota must be refused.');
        } catch (LlmException $e) {
            $this->assertSame('quota_exceeded', $e->reason);
            $this->assertStringContainsString('Kuota token AI', $e->userMessage());
        }

        $this->assertSame(1, $inner->calls);
    }

    public function test_zero_quota_means_unlimited(): void
    {
        $this->tenant->update(['llm_monthly_token_quota' => 0]);
        $client = QuotaLimitedLlmClient::wrap($this->stub(5000, 5000));

        $this->asTenant(fn () => $client->generateJson($this->request()));
        $this->asTenant(fn () => $client->generateJson($this->request()));

        $this->assertFalse(app(TenantLlmQuota::class)->isExceeded($this->tenant->fresh()));
    }

    public function test_usage_of_other_tenants_and_months_does_not_count(): void
    {
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        TenantLlmUsage::create(['tenant_id' => $other->id, 'period' => now()->format('Y-m'), 'input_tokens' => 99999]);
        TenantLlmUsage::create(['tenant_id' => $this->tenant->id, 'period' => now()->subMonth()->format('Y-m'), 'input_tokens' => 99999]);

        $this->assertSame(0, app(TenantLlmQuota::class)->used($this->tenant));
        $this->assertFalse(app(TenantLlmQuota::class)->isExceeded($this->tenant));
    }

    public function test_organization_key_is_used_when_personal_keys_are_not_allowed(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->userKey($user);
        TenantLlmSetting::create([
            'tenant_id' => $this->tenant->id, 'provider' => 'openai', 'model' => 'org-model',
            'api_key' => 'sk-org-key', 'is_enabled' => true, 'allow_user_byok' => false,
        ]);

        $client = app(LlmClientResolver::class)->resolveForUser($user);

        $this->assertInstanceOf(QuotaLimitedLlmClient::class, $client);
        $this->assertSame('openai', $client->provider());
        $this->assertSame('org-model', $client->model());
    }

    public function test_personal_key_wins_when_the_organization_allows_it(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->userKey($user);
        TenantLlmSetting::create([
            'tenant_id' => $this->tenant->id, 'provider' => 'openai', 'model' => 'org-model',
            'api_key' => 'sk-org-key', 'is_enabled' => true, 'allow_user_byok' => true,
        ]);

        $this->assertSame('gemini', app(LlmClientResolver::class)->resolveForUser($user)->provider());
    }

    public function test_disabled_organization_key_falls_back_to_the_platform_client(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        TenantLlmSetting::create([
            'tenant_id' => $this->tenant->id, 'provider' => 'openai', 'model' => 'org-model',
            'api_key' => 'sk-org-key', 'is_enabled' => false, 'allow_user_byok' => false,
        ]);

        $this->assertSame(app(StructuredLlmClient::class), app(LlmClientResolver::class)->resolveForUser($user));
        $this->assertInstanceOf(QuotaLimitedLlmClient::class, app(StructuredLlmClient::class));
    }

    public function test_admin_saves_organization_key_without_exposing_it(): void
    {
        $admin = $this->member('admin', ['manage-organization']);

        $this->actingAs($admin)
            ->patch(route('settings.organization.ai.update'), [
                'provider' => 'openai', 'model' => 'gpt-test', 'api_key' => 'sk-secret-org-key',
                'is_enabled' => true, 'allow_user_byok' => false,
            ])
            ->assertSessionHasNoErrors();

        $setting = TenantLlmSetting::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('sk-secret-org-key', $setting->api_key);
        $this->assertNotSame('sk-secret-org-key', $setting->getRawOriginal('api_key'));

        // An empty key field keeps the stored key; the page never receives it.
        $this->actingAs($admin)
            ->patch(route('settings.organization.ai.update'), ['provider' => 'openai', 'model' => 'gpt-test-2', 'api_key' => '', 'is_enabled' => true]);
        $this->assertSame('sk-secret-org-key', $setting->fresh()->api_key);

        $this->actingAs($admin)->get(route('settings.organization.index'))
            ->assertDontSee('sk-secret-org-key')
            ->assertInertia(fn ($page) => $page->where('ai.setting.has_api_key', true)->where('ai.usage.quota', 1000));
    }

    public function test_members_without_permission_cannot_change_the_organization_key(): void
    {
        $bod = $this->member('bod', []);

        $this->actingAs($bod)
            ->patch(route('settings.organization.ai.update'), ['provider' => 'openai', 'api_key' => 'sk-x', 'is_enabled' => true])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenant_llm_settings', ['tenant_id' => $this->tenant->id]);
    }

    private function member(string $role, array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'organization', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => ucfirst($role)])
            ->givePermissionTo($permissions);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    private function userKey(User $user): void
    {
        $this->asTenant(fn () => UserLlmSetting::create([
            'user_id' => $user->id, 'provider' => 'gemini', 'label' => 'Gemini', 'model' => 'gemini-test',
            'api_key' => 'AIzaPersonalKey123', 'is_active' => true,
        ]));
    }

    private function asTenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runAs($this->tenant, $callback);
    }

    private function request(): LlmRequest
    {
        return new LlmRequest(purpose: 'test', system: 'system', user: 'user', schema: ['type' => 'object'], maxTokens: 100, effort: 'low');
    }

    private function stub(int $input, int $output): StructuredLlmClient
    {
        return new class($input, $output) implements StructuredLlmClient
        {
            public int $calls = 0;

            public function __construct(private int $input, private int $output) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function provider(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function model(): string
            {
                return 'stub-1';
            }

            public function generateJson(LlmRequest $request): LlmResponse
            {
                $this->calls++;

                return new LlmResponse(['ok' => true], 'stub', 'stub-1', 'end_turn', ['input_tokens' => $this->input, 'output_tokens' => $this->output]);
            }

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                return $this->generateJson($request);
            }
        };
    }
}
