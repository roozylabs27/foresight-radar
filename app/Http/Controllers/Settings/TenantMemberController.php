<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantInvitationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TenantMemberController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        protected TenantContext $tenantContext,
        protected TenantInvitationService $invitations,
    ) {}

    /**
     * Display members of the active tenant workspace.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            $user = Auth::user();
            if ($user && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                $this->tenantContext->setTenant($tenant);
            }
        }

        if (! $tenant) {
            abort(403, 'Akses ditolak: Tidak ada workspace aktif.');
        }

        $search = trim((string) $request->input('search', ''));
        $perPage = min(100, max(5, (int) $request->input('per_page', 10)));

        $membersQuery = User::where('tenant_id', $tenant->id)
            ->with('roles')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $members = $membersQuery->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'uuid' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->roles->first()?->name ?? 'member',
                'role_display' => (string) ($u->roles->first()?->getAttribute('display_name') ?? 'Member'),
                'created_at' => $u->getRawOriginal('created_at') ? Carbon::parse($u->getRawOriginal('created_at'))->toISOString() : null,
                'is_current_user' => $u->id === Auth::id(),
            ]);

        $invitations = TenantInvitation::where('tenant_id', $tenant->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('inviter')
            ->latest()
            ->get()
            ->map(fn (TenantInvitation $inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'name' => $inv->name,
                'role' => $inv->role,
                'invited_by' => $inv->inviter?->name ?? 'Admin',
                'expires_at' => Carbon::parse($inv->expires_at)->toISOString(),
                'created_at' => Carbon::parse($inv->created_at)->toISOString(),
            ]);

        $availableRoles = Role::whereIn('name', ['admin', 'bod'])
            ->get()
            ->map(fn ($r) => [
                'name' => (string) $r->getAttribute('name'),
                'display_name' => (string) ($r->getAttribute('display_name') ?? ucfirst((string) $r->getAttribute('name'))),
            ]);

        return Inertia::render('Settings/Members', [
            'members' => $members,
            'invitations' => $invitations,
            'availableRoles' => $availableRoles,
            'maxUsers' => $tenant->max_users,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'title' => 'Kelola Anggota & Undangan Tim',
        ]);
    }

    /**
     * Invite a new member to the organization.
     */
    public function invite(Request $request): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            abort(403, 'Akses ditolak: Tidak ada workspace aktif.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:150'],
            'role' => ['required', 'string', 'in:admin,bod'],
        ]);

        $email = strtolower(trim($validated['email']));

        // An invite only ever creates a new account. Existing accounts, including platform users,
        // accounts without a tenant, and soft-deleted ones, are never attached or changed here.
        $existingUser = User::withTrashed()->where('email', $email)->first();
        if ($existingUser && ! $existingUser->trashed() && (int) $existingUser->tenant_id === (int) $tenant->id) {
            return back()->withErrors(['email' => 'Pengguna dengan surel ini sudah menjadi anggota organisasi ini.']);
        }
        if ($existingUser) {
            return back()->withErrors(['email' => 'Surel ini sudah terdaftar sebagai akun lain sehingga tidak dapat diundang. Kebijakan keamanan 1-User-1-Tenant membatasi akun hanya untuk satu organisasi.']);
        }

        // Capacity, token, and the queued email live in the service. Persisting the invitation
        // is not delivery: the message only says the email was scheduled.
        $this->invitations->invite($tenant, $email, $validated['name'] ?? null, $validated['role'], $request->user());

        return back()->with('success', "Undangan untuk {$email} telah dibuat. Email undangan dijadwalkan untuk dikirim dan berlaku ".TenantInvitationService::EXPIRY_DAYS.' hari.');
    }

    /**
     * Update a member's role within the organization.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant || (int) $user->tenant_id !== (int) $tenant->id) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,bod'],
        ]);

        // Direct grants would otherwise outlive the role, as in UserController::update.
        $user->syncRoles([$validated['role']]);
        $user->syncPermissions([]);

        return back()->with('success', 'Peran anggota berhasil diperbarui.');
    }

    /**
     * Remove a member from the organization.
     */
    public function removeMember(User $user): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant || (int) $user->tenant_id !== (int) $tenant->id) {
            abort(403, 'Akses ditolak.');
        }

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri dari organisasi.');
        }

        $user->update(['tenant_id' => null]);
        $user->roles()->detach();
        $user->syncPermissions([]);

        return back()->with('success', 'Anggota berhasil dikeluarkan dari organisasi.');
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvite(TenantInvitation $invitation): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant || (int) $invitation->tenant_id !== (int) $tenant->id) {
            abort(403, 'Akses ditolak.');
        }

        $invitation->delete();

        return back()->with('success', 'Undangan berhasil dibatalkan.');
    }
}
