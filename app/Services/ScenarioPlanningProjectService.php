<?php

namespace App\Services;

use App\Models\ScenarioPlanningProject;
use App\Models\User;

/**
 * Scenario planning exercises (roadmap R0.1): the key focal issue, gate G1
 * (focal issue confirmed) and the status of the nine method steps.
 */
class ScenarioPlanningProjectService
{
    /**
     * The nine steps of the method (slide 13). `module` is the existing page that covers the step
     * today, tenant-wide; it becomes exercise-scoped in later roadmap items.
     */
    public const STEPS = [
        1 => ['key' => 'focal_issue', 'title' => 'Key Focal Issue', 'module' => null],
        2 => ['key' => 'driving_forces', 'title' => 'Driving Forces', 'module' => 'driving-force.'],
        3 => ['key' => 'critical_uncertainties', 'title' => 'Critical Uncertainties', 'module' => 'visualization.prioritizing.'],
        4 => ['key' => 'scenario_framework', 'title' => 'Scenario Framework', 'module' => 'scenarios.index'],
        5 => ['key' => 'develop_scenarios', 'title' => 'Develop Scenarios', 'module' => null],
        6 => ['key' => 'narratives', 'title' => 'Narratives Imagination', 'module' => 'scenarios.index'],
        7 => ['key' => 'challenges_opportunities', 'title' => 'Challenges & Opportunities', 'module' => null],
        8 => ['key' => 'strategic_response', 'title' => 'Strategic Response', 'module' => null],
        9 => ['key' => 'early_warning', 'title' => 'Early Warning Signal', 'module' => null],
    ];

    public function create(array $validated, User $user): ScenarioPlanningProject
    {
        return ScenarioPlanningProject::create($this->attributes($validated) + [
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Save the changes. Returns true when the focal issue itself changed after it was confirmed:
     * the confirmation is then withdrawn and the exercise goes back to draft until confirmed again.
     */
    public function update(ScenarioPlanningProject $project, array $validated, User $user): bool
    {
        $project->fill($this->attributes($validated) + ['updated_by' => $user->id]);
        if (isset($validated['status'])) {
            $project->status = $validated['status'];
        }

        $reconfirm = $project->isConfirmed() && $project->isDirty(ScenarioPlanningProject::FOCAL_FIELDS);
        if ($reconfirm) {
            // The force list was agreed for the old focal issue, so gate G2 goes too.
            $project->confirmed_at = null;
            $project->confirmed_by = null;
            $project->forces_confirmed_at = null;
            $project->forces_confirmed_by = null;
            $project->scores_confirmed_at = null;
            $project->scores_confirmed_by = null;
            $project->status = 'draft';
        }

        $project->save();
        if ($reconfirm) {
            $project->withdrawUncertaintyApprovals();
        }

        return $reconfirm;
    }

    /** Gate G1. Confirming an already confirmed exercise changes nothing. */
    public function confirm(ScenarioPlanningProject $project, User $user): void
    {
        if ($project->isConfirmed()) {
            return;
        }

        $project->update([
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
            'status' => $project->status === 'draft' ? 'active' : $project->status,
            'updated_by' => $user->id,
        ]);
    }

    /** Impact and uncertainty from this score up count as high on the matrix. */
    public const HIGH_SCORE = 6;

    /** Steps with their own exercise screen, by step number. */
    private const SCOPED_ROUTES = [
        2 => 'scenario-planning.forces.index',
        3 => 'scenario-planning.assessment.index',
    ];

    /**
     * Status per step. Gate G1 (focal issue confirmed) opens step 2, G2 (force list agreed) opens
     * step 3, and step 3 is done at G4 (two critical uncertainties approved, after G3 scores agreed),
     * which opens steps 4–9. `href` is the exercise's own screen when the step has one, otherwise the
     * existing tenant-wide page (`scoped` false).
     *
     * @return array<int, array{number: int, key: string, title: string, status: string, href: ?string, scoped: bool}>
     */
    public function steps(ScenarioPlanningProject $project): array
    {
        $focal = $project->isConfirmed();
        $forces = $project->forcesConfirmed();
        $axes = $project->uncertaintiesApproved();
        $included = $project->projectForces()->where('inclusion_status', 'included');
        $hasForces = (clone $included)->exists();
        $started = (clone $included)->whereHas('consensus')->exists() || $project->criticalUncertainties()->exists();

        return collect(self::STEPS)->map(function (array $step, int $number) use ($project, $focal, $forces, $axes, $hasForces, $started) {
            $status = match (true) {
                $number === 1 => $focal ? 'done' : 'in_progress',
                $number === 2 => ! $focal ? 'locked' : ($forces ? 'done' : ($hasForces ? 'in_progress' : 'available')),
                $number === 3 => ! $forces ? 'locked' : ($axes ? 'done' : ($started ? 'in_progress' : 'available')),
                default => $axes ? 'available' : 'locked',
            };
            $scoped = isset(self::SCOPED_ROUTES[$number]);

            return [
                'number' => $number,
                'key' => $step['key'],
                'title' => $step['title'],
                'status' => $status,
                'href' => $scoped ? route(self::SCOPED_ROUTES[$number], $project) : ($step['module'] ? route($step['module']) : null),
                'scoped' => $scoped,
            ];
        })->values()->all();
    }

    public function present(ScenarioPlanningProject $project): array
    {
        $project->loadMissing(['owner:id,uuid,name', 'confirmer:id,name', 'forcesConfirmer:id,name', 'scoresConfirmer:id,name', 'creator:id,name']);

        return [
            'id' => $project->uuid,
            'name' => $project->name,
            'focal_question' => $project->focal_question,
            'purpose' => $project->purpose,
            'target_year' => $project->target_year,
            'geography' => $project->geography,
            'industry' => $project->industry,
            'owner_unit' => $project->owner_unit,
            'owner' => $project->owner ? ['id' => $project->owner->uuid, 'name' => $project->owner->name] : null,
            'background' => $project->background,
            'objectives' => $project->objectives,
            'status' => $project->status,
            'confirmed_at' => $project->confirmed_at?->toISOString(),
            'confirmed_by' => $project->confirmer?->name,
            'forces_confirmed_at' => $project->forces_confirmed_at?->toISOString(),
            'forces_confirmed_by' => $project->forcesConfirmer?->name,
            'scores_confirmed_at' => $project->scores_confirmed_at?->toISOString(),
            'scores_confirmed_by' => $project->scoresConfirmer?->name,
            'created_by' => $project->creator?->name,
            'updated_at' => $project->updated_at?->toISOString(),
        ];
    }

    private function attributes(array $validated): array
    {
        $ownerId = empty($validated['owner']) ? null : User::where('uuid', $validated['owner'])->value('id');

        return [
            'name' => $validated['name'],
            'focal_question' => $validated['focal_question'],
            'purpose' => $validated['purpose'],
            'target_year' => (int) $validated['target_year'],
            'geography' => $validated['geography'] ?? null,
            'industry' => $validated['industry'] ?? null,
            'owner_unit' => $validated['owner_unit'] ?? null,
            'owner_user_id' => $ownerId,
            'background' => $validated['background'] ?? null,
            'objectives' => $validated['objectives'] ?? null,
        ];
    }
}
