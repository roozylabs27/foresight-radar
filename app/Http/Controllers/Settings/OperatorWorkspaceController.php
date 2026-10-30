<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceTenantContext;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a platform operator (no tenant of their own) pick the one workspace they
 * work in, or leave it (TENANT-007). The choice lives in the session only.
 */
class OperatorWorkspaceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->tenant_id === null && $user->hasAnyRole(EnforceTenantContext::PLATFORM_ROLES), 403);

        $validated = $request->validate([
            'tenant' => ['nullable', 'uuid', 'exists:tenants,uuid,deleted_at,NULL'],
        ]);

        if (empty($validated['tenant'])) {
            $request->session()->forget(EnforceTenantContext::WORKSPACE_SESSION_KEY);

            return back()->with('success', 'Keluar dari workspace. Pilih workspace untuk melihat dan mengubah data organisasi.');
        }

        $tenant = Tenant::where('uuid', $validated['tenant'])->firstOrFail();
        $request->session()->put(EnforceTenantContext::WORKSPACE_SESSION_KEY, $tenant->id);

        return back()->with('success', "Workspace aktif: {$tenant->name}.");
    }
}
