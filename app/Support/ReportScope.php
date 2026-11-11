<?php

namespace App\Support;

use App\Models\ScenarioPlanningProject;
use Illuminate\Http\Request;

/**
 * What the firm reports (Foresight Radar, Prioritizing, Registered List) show: the whole firm
 * (the default) or one Key Focal Issue, a scenario planning exercise, chosen with `?scope=<uuid>`.
 */
final class ReportScope
{
    public const FIRM = 'firm';

    private function __construct(public readonly ?ScenarioPlanningProject $project) {}

    public static function firm(): self
    {
        return new self(null);
    }

    public static function of(ScenarioPlanningProject $project): self
    {
        return new self($project);
    }

    /**
     * Read `scope` from the request. An exercise needs `view-scenario` (403) and must belong to the
     * current tenant (404; the tenant scope hides other tenants' exercises).
     */
    public static function fromRequest(Request $request): self
    {
        // Query string for the report reads, request body for the dashboard simulation.
        $value = $request->input('scope');
        if (! is_string($value) || $value === '' || $value === self::FIRM) {
            return self::firm();
        }

        abort_unless($request->user()?->can('view-scenario'), 403, 'Anda tidak memiliki akses ke Key Focal Issue.');
        $project = ScenarioPlanningProject::where('uuid', $value)->first();
        abort_if($project === null, 404, 'Key Focal Issue tidak ditemukan.');

        return self::of($project);
    }

    public function isFirm(): bool
    {
        return $this->project === null;
    }
}
