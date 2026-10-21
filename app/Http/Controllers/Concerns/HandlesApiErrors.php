<?php

namespace App\Http\Controllers\Concerns;

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
        Log::error("Failed {$context}", [
            'error' => $th->getMessage(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
        ]);

        return [
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => $userMessage ?? "An error occurred during {$context}. Please try again.",
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
