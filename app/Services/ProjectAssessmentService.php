<?php

namespace App\Services;

use App\Models\Dimension;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Validation\ValidationException;

/**
 * Step 3 of an exercise, first half (roadmap R1.1): the agreed impact and
 * uncertainty of each included force, and gate G3 (scores agreed). Choosing
 * the two critical uncertainties follows in R0.4.
 */
class ProjectAssessmentService
{
    /**
     * Save the consensus score of one included force. Returns true when a changed score withdrew G3;
     * editing only the rationale keeps it.
     *
     * @throws ValidationException before the force list is agreed, or for a force that is not included
     */
    public function score(ProjectDrivingForce $link, array $validated, User $user): bool
    {
        $project = $link->project;
        if (! $project->forcesConfirmed()) {
            throw ValidationException::withMessages(['impact' => 'Sepakati daftar driving force dulu sebelum memberi skor.']);
        }
        if (! $link->isIncluded()) {
            throw ValidationException::withMessages(['impact' => 'Hanya driving force yang disertakan yang diberi skor.']);
        }

        $assessment = $link->consensus()->firstOrNew([], ['kind' => ForceAssessment::CONSENSUS]);
        $assessment->fill([
            'impact' => (int) $validated['impact'],
            'uncertainty' => (int) $validated['uncertainty'],
            'impact_rationale' => $validated['impact_rationale'] ?? null,
            'uncertainty_rationale' => $validated['uncertainty_rationale'] ?? null,
            'origin' => Provenance::HUMAN,
            'updated_by' => $user->id,
        ]);
        $assessment->project_driving_force_id = $link->id;

        $changed = $assessment->exists && $assessment->isDirty(['impact', 'uncertainty']);
        $withdraws = $project->scoresConfirmed() && $changed;
        $assessment->save();

        if ($changed) {
            app(StalenessService::class)->markIfAxisForce($project, $link->id, 'Skor driving force '.($link->drivingForce?->keyword ?? '').' berubah');
        }

        if ($withdraws) {
            $project->update(['scores_confirmed_by' => null, 'scores_confirmed_at' => null]);
            $project->withdrawUncertaintyApprovals();
        }

        return $withdraws;
    }

    /**
     * Gate G3.
     *
     * @throws ValidationException before the force list is agreed or while an included force has no score
     */
    public function confirm(ScenarioPlanningProject $project, User $user): void
    {
        if (! $project->forcesConfirmed()) {
            throw ValidationException::withMessages(['scores' => 'Sepakati daftar driving force dulu.']);
        }
        $missing = $project->projectForces()->where('inclusion_status', 'included')->whereDoesntHave('consensus')->count();
        if ($missing > 0) {
            throw ValidationException::withMessages(['scores' => "{$missing} driving force yang disertakan belum diberi skor."]);
        }

        $project->update(['scores_confirmed_by' => $user->id, 'scores_confirmed_at' => now(), 'updated_by' => $user->id]);
    }

    /**
     * Included forces with their score, numbered in method category order (the numbers on the matrix).
     */
    public function list(ScenarioPlanningProject $project): array
    {
        $order = Dimension::ordered()->pluck('id')->flip();

        return $project->projectForces()
            ->where('inclusion_status', 'included')
            ->with(['consensus.updater:id,name', 'drivingForce.dimension.environment'])
            ->get()
            ->sortBy(fn ($link) => sprintf('%03d-%s', $order[$link->drivingForce?->dimension_id] ?? 999, strtolower((string) $link->drivingForce?->keyword)))
            ->values()
            ->map(fn (ProjectDrivingForce $link, int $index) => [
                'id' => $link->uuid,
                'number' => $index + 1,
                'is_predetermined' => $link->is_predetermined,
                'impact' => $link->consensus?->impact,
                'uncertainty' => $link->consensus?->uncertainty,
                'impact_rationale' => $link->consensus?->impact_rationale,
                'uncertainty_rationale' => $link->consensus?->uncertainty_rationale,
                'origin' => $link->consensus?->origin,
                'updated_by' => $link->consensus?->updater?->name,
                'force' => [
                    'keyword' => $link->drivingForce?->keyword,
                    'dimension' => $link->drivingForce?->dimension?->name,
                    'environment' => $link->drivingForce?->dimension?->environment?->name,
                ],
            ])
            ->all();
    }
}
