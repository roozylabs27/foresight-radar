<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TenantSecurityException extends RuntimeException
{
    public const WORKSPACE_REQUIRED = 'Pilih workspace organisasi terlebih dahulu sebelum mengubah data.';

    /**
     * Refused as 403 rather than a 500: the request is not allowed in this tenant context.
     * Only the workspace message is shown; other violations stay generic.
     */
    public function render(Request $request): Response
    {
        $message = $this->getMessage() === self::WORKSPACE_REQUIRED ? self::WORKSPACE_REQUIRED : 'Akses ditolak.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => $message], Response::HTTP_FORBIDDEN);
        }

        return back(Response::HTTP_SEE_OTHER)->with('error', $message);
    }
}
