<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Controller;
use App\Models\DrivingForce;
use App\Models\Scenario;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        protected TenantContext $tenantContext
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
}
