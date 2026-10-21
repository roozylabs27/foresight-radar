<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

trait HandlesApiErrors
{
    /**
     * Log the detailed exception and return a sanitized API response.
     */
    protected function handleError(\Throwable $th, string $context = 'operation'): array
    {
        Log::error("Failed {$context}", [
            'error' => $th->getMessage(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
        ]);

        return [
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => "An error occurred during {$context}. Please try again.",
        ];
    }
}
