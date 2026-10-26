<?php

use App\Http\Middleware\EnforceTenantContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'app_locale',
        ]);

        $middleware->web(append: [
            SetAppLocale::class,
            EnforceTenantContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant context must exist before implicit route-model binding, otherwise
        // TenantScope sees no tenant and every bound model resolves to 404.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnforceTenantContext::class,
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $statusCode = $response->getStatusCode();

            // Pass through pure JSON API requests that are not Inertia requests
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            // Handle specific HTTP error status codes for Inertia visits
            if (in_array($statusCode, [403, 404, 419, 500, 503])) {
                // In local debug mode, let unexpected 500 exceptions show details unless requested via Inertia
                if ($statusCode === 500 && config('app.debug') && ! $request->header('X-Inertia') && ! app()->runningUnitTests()) {
                    return $response;
                }

                // If it is an Inertia client request, render the Inertia React Error component
                if ($request->header('X-Inertia')) {
                    $customMessage = null;
                    if ($e instanceof HttpExceptionInterface) {
                        $customMessage = $e->getMessage() ?: null;
                    }

                    return Inertia::render('Error', [
                        'status' => $statusCode,
                        'message' => $customMessage,
                    ])->toResponse($request)->setStatusCode($statusCode);
                }
            }

            return $response;
        });
    })->create();
