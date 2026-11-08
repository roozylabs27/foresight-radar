<?php

namespace App\Services;

use App\Models\Dimension;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
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
    /** Individual scores this far apart (max − min, on either axis) need a discussion before consensus. */
    public const WIDE_SPREAD = 4;

    /**
     * Save the consensus score of one included force. Returns true when a changed score withdrew G3;
     * editing only the rationale keeps it.
     *
     * @throws ValidationException before the force list is agreed, or for a force that is not included
     */
    public function score(ProjectDrivingForce $link, array $validated, User $user, string $origin = Provenance::HUMAN): bool
    {
        $project = $this->requireScorable($link);

        $assessment = $link->consensus()->firstOrNew([], ['kind' => ForceAssessment::CONSENSUS]);
        $assessment->fill([
            'impact' => (int) $validated['impact'],
            'uncertainty' => (int) $validated['uncertainty'],
            'impact_rationale' => $validated['impact_rationale'] ?? null,
            'uncertainty_rationale' => $validated['uncertainty_rationale'] ?? null,
            'origin' => $origin,
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
     * Roadmap R2.1: one participant's own score. It never changes the consensus, gate G3 or staleness.
     *
     * @throws ValidationException before the force list is agreed, or for a force that is not included
     */
    public function scoreMine(ProjectDrivingForce $link, array $validated, User $user): void
    {
        $this->requireScorable($link);

        $assessment = $link->individuals()->firstOrNew(['assessor_id' => $user->id], ['kind' => ForceAssessment::INDIVIDUAL]);
        $assessment->fill([
            'impact' => (int) $validated['impact'],
            'uncertainty' => (int) $validated['uncertainty'],
            'impact_rationale' => $validated['impact_rationale'] ?? null,
            'uncertainty_rationale' => $validated['uncertainty_rationale'] ?? null,
            'origin' => Provenance::HUMAN,
            'updated_by' => $user->id,
        ]);
        $assessment->project_driving_force_id = $link->id;
        $assessment->save();
    }

    public function withdrawMine(ProjectDrivingForce $link, User $user): void
    {
        $link->individuals()->where('assessor_id', $user->id)->delete();
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
    public function list(ScenarioPlanningProject $project, ?User $viewer = null): array
    {
        $order = Dimension::ordered()->pluck('id')->flip();

        return $project->projectForces()
            ->where('inclusion_status', 'included')
            ->with(['consensus.updater:id,name', 'individuals.assessor:id,name', 'suggestion', 'drivingForce.dimension.environment'])
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
                'individual' => $this->spread($link),
                'mine' => $this->mine($link, $viewer),
                'suggestion' => $this->suggestion($link),
                'force' => [
                    'keyword' => $link->drivingForce?->keyword,
                    'dimension' => $link->drivingForce?->dimension?->name,
                    'environment' => $link->drivingForce?->dimension?->environment?->name,
                ],
            ])
            ->all();
    }

    /**
     * Spread of the participants' own scores: count, min / max / median per axis, and whether it is
     * wide enough to discuss before agreeing the consensus.
     */
    private function spread(ProjectDrivingForce $link): array
    {
        $rows = $link->individuals;
        $axis = function (string $field) use ($rows): ?array {
            $values = $rows->pluck($field)->map(fn ($value) => (int) $value)->sort()->values();

            return $values->isEmpty() ? null : ['min' => $values->first(), 'max' => $values->last(), 'median' => $values->median()];
        };
        $impact = $axis('impact');
        $uncertainty = $axis('uncertainty');

        return [
            'count' => $rows->count(),
            'impact' => $impact,
            'uncertainty' => $uncertainty,
            'wide' => $rows->count() > 1 && max($impact['max'] - $impact['min'], $uncertainty['max'] - $uncertainty['min']) >= self::WIDE_SPREAD,
            'assessors' => $rows->sortBy(fn ($row) => $row->assessor?->name)->map(fn (ForceAssessment $row) => [
                'name' => $row->assessor?->name,
                'impact' => $row->impact,
                'uncertainty' => $row->uncertainty,
            ])->values()->all(),
        ];
    }

    /** The pending AI suggestion with the signals it cites (roadmap R2.4). */
    private function suggestion(ProjectDrivingForce $link): ?array
    {
        $row = $link->suggestion;
        if (! $row) {
            return null;
        }
        $ids = $row->cited_signal_ids ?? [];

        return [
            'impact' => $row->impact,
            'uncertainty' => $row->uncertainty,
            'impact_rationale' => $row->impact_rationale,
            'uncertainty_rationale' => $row->uncertainty_rationale,
            'signals' => $ids === [] ? [] : Signal::whereIn('id', $ids)->get(['uuid', 'title'])->map(fn (Signal $signal) => ['id' => $signal->uuid, 'title' => $signal->title])->values()->all(),
        ];
    }

    private function mine(ProjectDrivingForce $link, ?User $viewer): ?array
    {
        $row = $viewer ? $link->individuals->firstWhere('assessor_id', $viewer->id) : null;

        return $row ? [
            'impact' => $row->impact,
            'uncertainty' => $row->uncertainty,
            'impact_rationale' => $row->impact_rationale,
            'uncertainty_rationale' => $row->uncertainty_rationale,
        ] : null;
    }

    /** @throws ValidationException before the force list is agreed, or for a force that is not included */
    private function requireScorable(ProjectDrivingForce $link): ScenarioPlanningProject
    {
        $project = $link->project;
        if (! $project->forcesConfirmed()) {
            throw ValidationException::withMessages(['impact' => 'Sepakati daftar driving force dulu sebelum memberi skor.']);
        }
        if (! $link->isIncluded()) {
            throw ValidationException::withMessages(['impact' => 'Hanya driving force yang disertakan yang diberi skor.']);
        }

        return $project;
    }
}
