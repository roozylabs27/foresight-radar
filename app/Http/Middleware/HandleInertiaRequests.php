<?php

namespace App\Http\Middleware;

use App\Enums\LlmJobStatus;
use App\Models\Source;
use App\Services\SignalExtractionService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $data = [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'supported_locales' => [
                'id' => ['code' => 'id', 'name' => 'Bahasa Indonesia', 'short' => 'ID'],
                'en' => ['code' => 'en', 'name' => 'English', 'short' => 'EN'],
            ],
            'auth' => [
                'user' => $user,
            ],
            'canRegister' => (bool) config('auth.allow_registration', false),
            // back()->with('success'|'error') messages; AuthenticatedLayout shows them as toasts.
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];

        if ($user) {
            $activeExtractions = Source::whereIn('extraction_status', [LlmJobStatus::QUEUED, LlmJobStatus::PROCESSING])
                ->where('created_at', '>=', now()->subHours(12))
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id);
                    if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['developer', 'super-admin', 'admin'])) {
                        $q->orWhereNull('created_by');
                    }
                })
                ->latest()
                ->get()
                ->map(fn ($source) => app(SignalExtractionService::class)->progress($source))
                ->values();

            $data = [
                ...$data,
                'active_extractions' => $activeExtractions,
                'auth' => [
                    'user' => [
                        'id' => $user->id,
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                    ],
                    'tenant' => function () {
                        $tenant = app(TenantContext::class)->getTenant();

                        return $tenant ? [
                            'id' => $tenant->id,
                            'uuid' => $tenant->uuid,
                            'name' => $tenant->name,
                            'slug' => $tenant->slug,
                            'plan' => $tenant->plan,
                        ] : null;
                    },
                    'role' => $user->roles->first()?->name ?? null,
                    'permissions' => method_exists($user, 'getAllPermissions')
                        ? $user->getAllPermissions()->pluck('name')->toArray()
                        : ($user->permissions ? $user->permissions->pluck('name')->toArray() : []),
                ],
            ];
        }

        return $data;
    }
}
