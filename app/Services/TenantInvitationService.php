<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Invitation lifecycle: pending -> accepted, or expired / revoked (deleted).
 * Only a SHA-256 hash of the token is stored; the raw token exists in the
 * emailed link alone. Invitations are read without the tenant scope because
 * the recipient is a guest with no tenant context.
 */
class TenantInvitationService
{
    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const ACCEPTED = 'accepted';

    public const UNAVAILABLE = 'unavailable';

    public const EXPIRY_DAYS = 7;

    /**
     * Create or refresh the pending invitation for this email and queue the email.
     * Re-inviting a pending email rotates its token, so earlier links stop working.
     */
    public function invite(Tenant $tenant, string $email, ?string $name, string $role, User $inviter): TenantInvitation
    {
        $rawToken = Str::random(64);

        $invitation = DB::transaction(function () use ($tenant, $email, $name, $role, $inviter, $rawToken) {
            // Serialise invites per tenant so concurrent requests cannot both pass the capacity check.
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();

            $existing = $this->invitations()->where('tenant_id', $tenant->id)->where('email', $email)->first();
            $members = User::where('tenant_id', $tenant->id)->count();
            $pending = $this->invitations()->where('tenant_id', $tenant->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->when($existing, fn (Builder $q) => $q->whereKeyNot($existing->id))
                ->count();

            if ($members + $pending >= $tenant->max_users) {
                throw ValidationException::withMessages([
                    'email' => 'Batas maksimal kuota anggota organisasi ('.$tenant->max_users.') telah tercapai.',
                ]);
            }

            $attributes = [
                'name' => $name,
                'role' => $role,
                'token' => hash('sha256', $rawToken),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
                'accepted_at' => null,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return TenantInvitation::create(['tenant_id' => $tenant->id, 'email' => $email] + $attributes);
        });

        Notification::route('mail', $email)->notify(new TenantInvitationNotification(
            URL::signedRoute('invitations.accept', ['invitation' => $invitation->id, 'token' => $rawToken]),
            $tenant->name,
            $inviter->name,
            (string) (Role::where('name', $role)->value('display_name') ?? $role),
            $invitation->expires_at,
        ));

        return $invitation;
    }

    /**
     * Where an invitation link stands for this token.
     *
     * @return array{0: string, 1: ?TenantInvitation}
     */
    public function resolve(int $invitationId, string $rawToken, bool $lock = false): array
    {
        $invitation = $this->invitations()->with('tenant')
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())
            ->find($invitationId);

        if (! $invitation || ! hash_equals($invitation->token, hash('sha256', $rawToken))) {
            return [self::INVALID, null];
        }
        if ($invitation->isAccepted()) {
            return [self::ACCEPTED, $invitation];
        }
        if ($invitation->isExpired()) {
            return [self::EXPIRED, $invitation];
        }
        if (! $invitation->tenant || ! $invitation->tenant->isActive()) {
            return [self::UNAVAILABLE, $invitation];
        }

        return [self::VALID, $invitation];
    }

    /**
     * Create the invited account and consume the invitation in one transaction.
     * Returns the new user, or the state that blocked acceptance.
     */
    public function accept(int $invitationId, string $rawToken, string $name, string $password): User|string
    {
        return DB::transaction(function () use ($invitationId, $rawToken, $name, $password) {
            [$state, $invitation] = $this->resolve($invitationId, $rawToken, lock: true);
            if ($state !== self::VALID) {
                return $state;
            }

            if (User::withTrashed()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Surel ini sudah terdaftar sebagai akun lain. Hubungi admin organisasi Anda.',
                ]);
            }

            // Holding the emailed token proves control of the mailbox, so the address counts as verified.
            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$invitation->role]);

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }

    /**
     * @return Builder<TenantInvitation>
     */
    private function invitations(): Builder
    {
        return TenantInvitation::withoutGlobalScope(TenantScope::class);
    }
}
