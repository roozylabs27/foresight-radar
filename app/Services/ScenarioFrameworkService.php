<?php

namespace App\Services;

use App\Models\CriticalUncertainty;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\User;
use App\Rules\DifferentPole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 4 (roadmap R0.5): the exercise's 2x2 framework on its two approved critical uncertainties.
 * Saving creates the four quadrants at once, named "{pole A} · {pole B}" until the team renames
 * them. Approval (gate G5) locks poles and names until reopened; withdrawing an axis (G4) withdraws
 * it, and approving a replacement axis moves the framework onto it.
 */
class ScenarioFrameworkService
{
    public function create(ScenarioPlanningProject $project, array $validated, User $user): Scenario
    {
        return DB::transaction(function () use ($project, $validated, $user) {
            ScenarioPlanningProject::whereKey($project->id)->lockForUpdate()->first();

            if ($project->framework()->exists()) {
                throw ValidationException::withMessages(['framework' => 'Latihan ini sudah punya kerangka skenario.']);
            }
            [$axisA, $axisB] = $this->approvedAxes($project);

            $scenario = Scenario::create([
                'project_id' => $project->id,
                'name' => $validated['name'] ?? $project->name,
                'focal_question' => $project->focal_question,
                'status' => 'DRAFT',
                'framework_status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ] + $this->axisAttributes($axisA, $axisB));

            foreach (Scenario::POSITIONS as $position) {
                $scenario->quadrants()->create(['position' => $position, 'title' => $scenario->defaultTitle($position)]);
            }

            return $scenario;
        });
    }

    /** New pole labels; quadrants still named after the old poles follow them. */
    public function updatePoles(Scenario $scenario, array $poles, User $user): void
    {
        $this->requireDraft($scenario);

        DB::transaction(function () use ($scenario, $poles, $user) {
            $defaults = $this->defaultTitles($scenario);
            $scenario->update($poles + ['updated_by' => $user->id]);
            $this->renameDefaults($scenario, $defaults);
            app(StalenessService::class)->markQuadrants($scenario->quadrants()->get(), 'Kutub sumbu berubah setelah narasi dibuat', onlyWithNarrative: true);
        });
    }

    /** Name and one-line summary. Not a narrative edit, so `edited_at` stays untouched. */
    public function updateQuadrant(ScenarioQuadrant $quadrant, array $validated, User $user): void
    {
        $scenario = $quadrant->scenario;
        $this->requireDraft($scenario);

        $taken = $scenario->quadrants()->whereKeyNot($quadrant->id)->pluck('title')->map(fn ($title) => mb_strtolower(trim($title)));
        if ($taken->contains(mb_strtolower(trim($validated['title'])))) {
            throw ValidationException::withMessages(['title' => 'Nama ini sudah dipakai kuadran lain.']);
        }

        $quadrant->update(['title' => $validated['title'], 'summary' => $validated['summary'] ?? null]);
        $scenario->update(['updated_by' => $user->id]);
    }

    /**
     * Gate G5.
     *
     * @throws ValidationException when already approved or the axes are no longer the two approved uncertainties
     */
    public function approve(Scenario $scenario, User $user): void
    {
        $this->requireDraft($scenario);
        [$axisA, $axisB] = $this->approvedAxes($scenario->project);
        if ($scenario->axis_a_uncertainty_id !== $axisA->id || $scenario->axis_b_uncertainty_id !== $axisB->id) {
            throw ValidationException::withMessages(['framework' => 'Sumbu kerangka tidak sama dengan critical uncertainty yang disetujui.']);
        }

        $scenario->update(['framework_status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'updated_by' => $user->id]);
    }

    public function reopen(Scenario $scenario, User $user): void
    {
        $scenario->update(['framework_status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'updated_by' => $user->id]);
    }

    /**
     * After G4 is complete again, move a draft framework onto the approved uncertainties. A replaced
     * axis takes its uncertainty's poles; quadrants still carrying a default name follow.
     */
    public function syncAxes(ScenarioPlanningProject $project): void
    {
        $scenario = $project->framework()->first();
        if (! $scenario || $scenario->isFrameworkApproved() || ! $project->uncertaintiesApproved()) {
            return;
        }
        [$axisA, $axisB] = $this->approvedAxes($project);
        if ($scenario->axis_a_uncertainty_id === $axisA->id && $scenario->axis_b_uncertainty_id === $axisB->id) {
            return;
        }

        DB::transaction(function () use ($scenario, $axisA, $axisB) {
            $defaults = $this->defaultTitles($scenario);
            $attributes = $this->axisAttributes($axisA, $axisB);
            // Keep poles the team already adjusted on an axis that did not change.
            if ($scenario->axis_a_uncertainty_id === $axisA->id) {
                unset($attributes['axis_a_low_label'], $attributes['axis_a_high_label']);
            }
            if ($scenario->axis_b_uncertainty_id === $axisB->id) {
                unset($attributes['axis_b_low_label'], $attributes['axis_b_high_label']);
            }
            $scenario->update($attributes);
            $this->renameDefaults($scenario, $defaults);
        });
        app(StalenessService::class)->markFramework($scenario->project, 'Sumbu skenario diganti critical uncertainty lain');
    }

    /** The framework for the page, or null before it is saved. */
    public function present(ScenarioPlanningProject $project): ?array
    {
        $scenario = $project->framework()->with([
            'axis_a_uncertainty.projectForces.drivingForce', 'axis_b_uncertainty.projectForces.drivingForce', 'quadrants', 'approver:id,name',
        ])->first();
        if (! $scenario) {
            return null;
        }

        $axis = fn (string $letter) => [
            'axis' => $letter,
            'name' => $scenario->axisName($letter),
            'forces' => $scenario->axisUncertainty($letter)?->projectForces->map(fn ($link) => $link->drivingForce?->keyword)->filter()->values()->all() ?? [],
            'approved' => $scenario->axisUncertainty($letter)?->isApproved() ?? false,
            'low' => $letter === 'A' ? $scenario->axis_a_low_label : $scenario->axis_b_low_label,
            'high' => $letter === 'A' ? $scenario->axis_a_high_label : $scenario->axis_b_high_label,
        ];

        return [
            'id' => $scenario->uuid,
            'name' => $scenario->name,
            'status' => $scenario->framework_status,
            'approved_by' => $scenario->approver?->name,
            'approved_at' => $scenario->approved_at?->toISOString(),
            'axes' => [$axis('A'), $axis('B')],
            'quadrants' => $scenario->quadrants->map(fn (ScenarioQuadrant $quadrant) => [
                'id' => $quadrant->uuid,
                'position' => $quadrant->position,
                'poles' => $scenario->poles($quadrant->position),
                'title' => $quadrant->title,
                'summary' => $quadrant->summary,
                'has_narrative' => filled($quadrant->narrative),
            ])->values()->all(),
            'poleChecks' => $this->poleChecks($scenario),
            'suggestion' => $scenario->framework_suggestion,
            'suggestionStatus' => $project->framework_suggestion_status,
            'suggestionError' => $project->framework_suggestion_error,
        ];
    }

    /**
     * Matrix row 4.7: rule checks of the current poles that warn without blocking. A pole label used
     * on both axes makes quadrant names such as "Favorable · Favorable" ambiguous.
     *
     * @return array<int, array{issue: string, message: string}>
     */
    public function poleChecks(Scenario $scenario): array
    {
        $axisA = [$scenario->axis_a_low_label, $scenario->axis_a_high_label];
        $axisB = collect([$scenario->axis_b_low_label, $scenario->axis_b_high_label])->map(fn ($label) => DifferentPole::normalize((string) $label));

        return collect($axisA)
            ->filter(fn ($label) => $axisB->contains(DifferentPole::normalize((string) $label)))
            ->map(fn ($label) => [
                'issue' => 'shared',
                'message' => "Kutub \"{$label}\" dipakai di kedua sumbu, sehingga nama kuadran sulit dibedakan. Beri kutub tiap sumbu keadaan yang khas.",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: CriticalUncertainty, 1: CriticalUncertainty}
     */
    private function approvedAxes(ScenarioPlanningProject $project): array
    {
        $approved = $project->criticalUncertainties()->where('status', 'approved')->whereNotNull('axis')->get()->keyBy('axis');
        if (! isset($approved['A'], $approved['B'])) {
            throw ValidationException::withMessages(['framework' => 'Setujui dua critical uncertainty sebagai sumbu A dan B dulu.']);
        }

        return [$approved['A'], $approved['B']];
    }

    private function axisAttributes(CriticalUncertainty $axisA, CriticalUncertainty $axisB): array
    {
        return [
            'axis_a_uncertainty_id' => $axisA->id,
            'axis_b_uncertainty_id' => $axisB->id,
            'axis_a_low_label' => $axisA->low_pole_label,
            'axis_a_high_label' => $axisA->high_pole_label,
            'axis_b_low_label' => $axisB->low_pole_label,
            'axis_b_high_label' => $axisB->high_pole_label,
        ];
    }

    /** @return array<string, string> position => current default name */
    private function defaultTitles(Scenario $scenario): array
    {
        return collect(Scenario::POSITIONS)->mapWithKeys(fn ($position) => [$position => $scenario->defaultTitle($position)])->all();
    }

    /** @param  array<string, string>  $previous */
    private function renameDefaults(Scenario $scenario, array $previous): void
    {
        foreach ($scenario->quadrants()->get() as $quadrant) {
            if ($quadrant->title === ($previous[$quadrant->position] ?? null)) {
                $quadrant->update(['title' => $scenario->defaultTitle($quadrant->position)]);
            }
        }
    }

    private function requireDraft(Scenario $scenario): void
    {
        if ($scenario->isFrameworkApproved()) {
            throw ValidationException::withMessages(['framework' => 'Kerangka sudah disetujui. Buka kembali untuk mengubah kutub atau nama kuadran.']);
        }
    }
}
