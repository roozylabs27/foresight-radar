<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TenantMemberController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display members of the active tenant workspace.
     */
    public function index(): Response
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

        $members = User::where('tenant_id', $tenant->id)
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
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

        // Check if user is already a member of this tenant
        $existingMember = User::where('email', $email)->where('tenant_id', $tenant->id)->first();
        if ($existingMember) {
            return back()->withErrors(['email' => 'Pengguna dengan surel ini sudah menjadi anggota organisasi ini.']);
        }

        // Check if user belongs to another tenant (Single-Tenant Membership Policy)
        $userOtherTenant = User::where('email', $email)->whereNotNull('tenant_id')->where('tenant_id', '!=', $tenant->id)->first();
        if ($userOtherTenant) {
            return back()->withErrors(['email' => 'Pengguna dengan surel ini sudah terdaftar di organisasi lain. Kebijakan keamanan 1-User-1-Tenant membatasi akun hanya untuk satu organisasi.']);
        }

        // Check tenant user capacity
        $currentCount = User::where('tenant_id', $tenant->id)->count();
        $pendingCount = TenantInvitation::where('tenant_id', $tenant->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();

        if (($currentCount + $pendingCount) >= $tenant->max_users) {
            return back()->withErrors(['email' => 'Batas maksimal kuota anggota organisasi ('.$tenant->max_users.') telah tercapai.']);
        }

        // Check if existing unassigned user exists
        $unassignedUser = User::where('email', $email)->whereNull('tenant_id')->first();
        if ($unassignedUser) {
            $unassignedUser->update(['tenant_id' => $tenant->id]);
            $unassignedUser->syncRoles([$validated['role']]);

            return back()->with('success', "Pengguna {$unassignedUser->name} berhasil ditambahkan langsung ke organisasi.");
        }

        // Create Invitation record
        $invitation = TenantInvitation::updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => $email],
            [
                'name' => $validated['name'] ?? null,
                'role' => $validated['role'],
                'token' => Str::random(40),
                'invited_by' => Auth::id(),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ]
        );

        // Also create the user account with a temporary secure password so they can log in immediately
        $tempPassword = Str::random(12);
        $newUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'] ?: explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make($tempPassword),
        ]);
        $newUser->syncRoles([$validated['role']]);
        $invitation->update(['accepted_at' => now()]);

        return back()->with('success', "Anggota {$newUser->name} berhasil diundang. Kata sandi sementara: {$tempPassword}");
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

        $user->syncRoles([$validated['role']]);

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
            return back()->withErrors(['message' => 'Anda tidak dapat menghapus akun Anda sendiri dari organisasi.']);
        }

        $user->update(['tenant_id' => null]);
        $user->roles()->detach();

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
