<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\TenantSecurityException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

trait HandlesApiErrors
{
    /**
     * Log the detailed exception and return a sanitized API response.
     */
    protected function handleError(\Throwable $th, string $context = 'operation', ?string $userMessage = null): array
    {
        // A refused tenant context is a 403, not a server error (e.g. an operator without a workspace).
        if ($th instanceof TenantSecurityException) {
            $message = $th->getMessage() === TenantSecurityException::WORKSPACE_REQUIRED
                ? TenantSecurityException::WORKSPACE_REQUIRED
                : 'Akses ditolak.';

            return ['statusCode' => Response::HTTP_FORBIDDEN, 'message' => $message, 'errors' => $message];
        }

        Log::error("Failed {$context}", [
            'error' => $th->getMessage(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
        ]);

        $message = $userMessage ?? "An error occurred during {$context}. Please try again.";

        return [
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => $message,
            'errors' => $message,
        ];
    }

    /**
     * Return a standardized JSON error response.
     * Preserves domain ValidationException and HttpException responses.
     */
    protected function errorJsonResponse(\Throwable $th, string $context = 'operation', ?string $userMessage = null): JsonResponse
    {
        if ($th instanceof ValidationException || $th instanceof HttpExceptionInterface) {
            throw $th;
        }

        $payload = $this->handleError($th, $context, $userMessage);

        return response()->json($payload, $payload['statusCode']);
    }
}
