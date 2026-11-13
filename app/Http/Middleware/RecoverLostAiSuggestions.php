<?php

namespace App\Http\Middleware;

use App\Models\ScenarioPlanningProject;
use App\Services\Llm\SuggestionRecovery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PI-10: before an exercise page is shown, AI assists lost with their queue worker are marked failed
 * and released (SuggestionRecovery), so the page never shows a button that stays disabled forever.
 * Runs after route-model binding, on GET requests that carry an exercise; it writes only when an
 * assist is past StaleJob's window.
 */
class RecoverLostAiSuggestions
{
    public function __construct(private readonly SuggestionRecovery $recovery) {}

    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');
        if ($request->isMethod('GET') && $project instanceof ScenarioPlanningProject) {
            $this->recovery->recover($project);
        }

        return $next($request);
    }
}
