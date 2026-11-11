<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ScenarioPlanningProjectService;
use App\Support\ReportScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared by the firm reports: the Key Focal Issues the page may switch to (only for users who may
 * view scenario planning), and the header that tells the chart how many forces a scope left out.
 */
trait ReportsByScope
{
    /** @return array{scopes: array<int, array<string, mixed>>, scope: string} */
    protected function scopeProps(Request $request): array
    {
        $scope = $request->query('scope');

        return [
            'scopes' => $request->user()?->can('view-scenario') ? app(ScenarioPlanningProjectService::class)->reportScopes() : [],
            'scope' => is_string($scope) && $scope !== '' ? $scope : ReportScope::FIRM,
        ];
    }

    protected function withSkipped(JsonResponse $response, int $skipped): JsonResponse
    {
        return $response->header('X-Report-Skipped', (string) $skipped);
    }
}
