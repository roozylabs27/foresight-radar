<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Controller;
use App\Models\DrivingForce;
use App\Models\Scenario;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\TenantLlmSetting;
use App\Models\User;
use App\Rules\PublicHttpsUrl;
use App\Services\Llm\TenantLlmQuota;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use HandlesApiErrors;

    /** Providers the organization key may use; same ids as LlmClientResolver::createClient. */
    private const AI_PROVIDERS = ['anthropic', 'openai', 'gemini', 'openai_compatible'];

    public function __construct(
        protected TenantContext $tenantContext,
        protected TenantLlmQuota $quota,
    ) {}

    /**
     * Display the organization / workspace settings.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant && $user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            $this->tenantContext->setTenant($tenant);
        }

        if (! $tenant) {
            abort(403, 'Akses ditolak: Tidak ada workspace aktif.');
        }

        $stats = [
            'members_count' => User::where('tenant_id', $tenant->id)->count(),
            'sources_count' => Source::where('tenant_id', $tenant->id)->count(),
            'signals_count' => Signal::where('tenant_id', $tenant->id)->count(),
            'driving_forces_count' => DrivingForce::where('tenant_id', $tenant->id)->count(),
            'scenarios_count' => Scenario::where('tenant_id', $tenant->id)->count(),
        ];

        return Inertia::render('Settings/Organization', [
            'organization' => [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'status' => $tenant->status,
                'plan' => $tenant->plan,
                'max_users' => $tenant->max_users,
                'storage_quota_mb' => $tenant->storage_quota_mb,
                'llm_monthly_token_quota' => $tenant->llm_monthly_token_quota,
                'settings' => $tenant->settings,
                'created_at' => $tenant->created_at?->toISOString(),
            ],
            'stats' => $stats,
            'ai' => $this->aiSettings($tenant),
            'title' => 'Pengaturan Organisasi & Workspace',
        ]);
    }

    /**
     * Update the organization profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            abort(403, 'Akses ditolak: Tidak ada workspace aktif.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain,'.$tenant->id],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Profil organisasi berhasil diperbarui.');
    }

    /**
     * Save the organization's own AI key and policy (TENANT-008). The key is write-only:
     * an empty field keeps the stored key, `clear_api_key` removes it.
     */
    public function updateAi(Request $request): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            abort(403, 'Akses ditolak: Tidak ada workspace aktif.');
        }

        $validated = $request->validate([
            'provider' => ['required', Rule::in(self::AI_PROVIDERS)],
            'model' => ['nullable', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'clear_api_key' => ['boolean'],
            'base_url' => ['nullable', 'url', 'max:255', 'required_if:provider,openai_compatible', new PublicHttpsUrl],
            'is_enabled' => ['boolean'],
            'allow_user_byok' => ['boolean'],
        ]);

        $setting = TenantLlmSetting::firstOrNew(['tenant_id' => $tenant->id]);
        $setting->fill([
            'provider' => $validated['provider'],
            'model' => $validated['model'] ?? null,
            'base_url' => $validated['base_url'] ?? null,
            'is_enabled' => (bool) ($validated['is_enabled'] ?? false),
            'allow_user_byok' => (bool) ($validated['allow_user_byok'] ?? false),
        ]);
        if (! empty($validated['api_key'])) {
            $setting->api_key = $validated['api_key'];
        } elseif ($request->boolean('clear_api_key')) {
            $setting->api_key = null;
        }
        $setting->save();

        return back()->with('success', 'Pengaturan AI organisasi disimpan.');
    }

    /**
     * Usage against the plan quotas and the organization's AI configuration, without the key.
     */
    private function aiSettings(Tenant $tenant): array
    {
        $setting = TenantLlmSetting::where('tenant_id', $tenant->id)->first();

        return [
            'usage' => $this->quota->summary($tenant),
            // Shown for the plan only; storage use is not measured or limited yet.
            'storage_quota_mb' => $tenant->storage_quota_mb,
            'providers' => self::AI_PROVIDERS,
            'setting' => $setting ? [
                'provider' => $setting->provider,
                'model' => $setting->model,
                'base_url' => $setting->base_url,
                'is_enabled' => $setting->is_enabled,
                'allow_user_byok' => $setting->allow_user_byok,
                'has_api_key' => ! empty($setting->api_key),
            ] : null,
        ];
    }
}
