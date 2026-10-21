<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);

        $statusCode = $response->getStatusCode();

        // Pass through pure JSON API requests that are not Inertia requests
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return $response;
        }

        // Handle specific HTTP error status codes for Inertia visits
        if (in_array($statusCode, [403, 404, 419, 500, 503])) {
            // In local debug mode, let unexpected 500 exceptions show details unless requested via Inertia
            if ($statusCode === 500 && config('app.debug') && !$request->header('X-Inertia') && !app()->runningUnitTests()) {
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
    }
}
