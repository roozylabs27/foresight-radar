<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\TenantInvitationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest side of the invitation lifecycle. The `signed` middleware rejects any
 * link not issued by the app; the service then checks the token, expiry,
 * single use, and tenant status.
 */
class AcceptInvitationController extends Controller
{
    public function __construct(
        protected TenantInvitationService $invitations
    ) {}

    public function show(Request $request, int $invitation): Response
    {
        [$state, $found] = $this->invitations->resolve($invitation, (string) $request->query('token'));

        // The same role name the email uses, not the role key.
        $roleName = $found ? (string) (Role::where('name', $found->role)->value('display_name') ?? $found->role) : null;

        return $this->page($request, $state, $found?->email, $found?->tenant?->name, $roleName, $found?->name);
    }

    public function store(Request $request, int $invitation): Response
    {
        if (Auth::check()) {
            return $this->page($request, 'signed_in', status: Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $result = $this->invitations->accept($invitation, (string) $request->query('token'), $validated['name'], $validated['password']);

        if (is_string($result)) {
            return $this->page($request, $result);
        }

        event(new Registered($result));
        Auth::login($result);
        $request->session()->regenerate();

        return redirect(RouteServiceProvider::HOME);
    }

    private function page(Request $request, string $state, ?string $email = null, ?string $organization = null, ?string $role = null, ?string $name = null, ?int $status = null): Response
    {
        if ($state === TenantInvitationService::VALID && Auth::check()) {
            $state = 'signed_in';
        }

        $status ??= match ($state) {
            TenantInvitationService::VALID => Response::HTTP_OK,
            'signed_in' => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_GONE,
        };

        $response = Inertia::render('Auth/AcceptInvitation', [
            'state' => $state,
            'invitation' => $state === TenantInvitationService::VALID ? [
                'email' => $email,
                'organization' => $organization,
                'role' => $role,
                'name' => $name,
            ] : null,
            // The signed link itself; the form posts back to it so the signature still covers the token.
            'submitUrl' => $request->fullUrl(),
        ])->toResponse($request)->setStatusCode($status);

        // The token sits in the query string: never leak it to other origins.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
