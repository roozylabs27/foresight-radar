<?php

namespace App\Services;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Support\Provenance;
use App\Support\RowLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 2 of an exercise (roadmap R0.2): its driving force list, picked from the
 * tenant library, and gate G2 (list agreed). Changing which forces are included
 * withdraws G2, because later steps were based on the agreed list.
 */
class ProjectDrivingForceService
{
    /**
     * Add library forces as included. Forces already in the list are left as they are.
     *
     * @param  array<int, string>  $uuids
     *
     * @throws ValidationException before the focal issue is confirmed
     */
    public function add(ScenarioPlanningProject $project, array $uuids, User $user): int
    {
        if (! $project->isConfirmed()) {
            throw ValidationException::withMessages(['forces' => 'Konfirmasi focal issue dulu sebelum menyusun driving force.']);
        }

        return DB::transaction(function () use ($project, $uuids, $user) {
            $added = 0;
            foreach (DrivingForce::whereIn('uuid', $uuids)->pluck('id') as $forceId) {
                $link = ProjectDrivingForce::firstOrCreate(
                    ['project_id' => $project->id, 'driving_force_id' => $forceId],
                    ['inclusion_status' => 'included', 'added_by' => $user->id],
                );
                if ($link->wasRecentlyCreated) {
                    $this->copyOrganizationRating($link);
                    $added++;
                }
            }
            if ($added > 0) {
                $this->withdrawAgreement($project);
            }

            return $added;
        });
    }

    /**
     * Save the per-exercise fields. Returns true when the change withdrew gate G2.
     */
    public function update(ProjectDrivingForce $link, array $validated): bool
    {
        $link->fill(array_intersect_key($validated, array_flip(['inclusion_status', 'influence_note', 'is_predetermined'])));
        $withdraws = $link->isDirty('inclusion_status') && $link->project->forcesConfirmed();
        $link->save();

        if ($withdraws) {
            $this->withdrawAgreement($link->project);
        }

        return $withdraws;
    }

    /** Remove a force from the list. Returns true when it withdrew gate G2. */
    public function remove(ProjectDrivingForce $link): bool
    {
        $withdraws = $link->isIncluded() && $link->project->forcesConfirmed();
        $link->delete();

        if ($withdraws) {
            $this->withdrawAgreement($link->project);
        }

        return $withdraws;
    }

    /**
     * Gate G2. Agreeing an already agreed list changes nothing.
     *
     * @throws ValidationException without a confirmed focal issue or an included force
     */
    public function confirm(ScenarioPlanningProject $project, User $user): void
    {
        RowLock::run($project, function () use ($project, $user) {
            if ($project->forcesConfirmed()) {
                return;
            }
            if (! $project->isConfirmed()) {
                throw ValidationException::withMessages(['forces' => 'Konfirmasi focal issue dulu.']);
            }
            if (! $project->projectForces()->where('inclusion_status', 'included')->exists()) {
                throw ValidationException::withMessages(['forces' => 'Sertakan minimal satu driving force sebelum menyepakati daftar.']);
            }

            $project->update(['forces_confirmed_by' => $user->id, 'forces_confirmed_at' => now(), 'updated_by' => $user->id]);
        });
    }

    /** Withdraw gate G2, and with it G3 and G4: scores and axes were agreed for the old list. */
    public function withdrawAgreement(ScenarioPlanningProject $project): void
    {
        if ($project->forcesConfirmed() || $project->scoresConfirmed()) {
            $project->update([
                'forces_confirmed_by' => null, 'forces_confirmed_at' => null,
                'scores_confirmed_by' => null, 'scores_confirmed_at' => null,
            ]);
        }
        $project->withdrawUncertaintyApprovals();
    }

    /**
     * Start the exercise score from the force's organization rating, marked as copied so the
     * team reviews it for this focal issue (step 3). Forces without both scores start empty.
     */
    private function copyOrganizationRating(ProjectDrivingForce $link): void
    {
        $rating = DrivingForceRating::where('driving_force_id', $link->driving_force_id)
            ->whereNotNull('impact_analysis')
            ->whereNotNull('uncertainty_analysis')
            ->first();

        if ($rating) {
            ForceAssessment::create([
                'project_driving_force_id' => $link->id,
                'kind' => ForceAssessment::CONSENSUS,
                'impact' => $rating->impact_analysis,
                'uncertainty' => $rating->uncertainty_analysis,
                'origin' => Provenance::LIBRARY,
            ]);
        }
    }

    /** The exercise's list, in method category order, then keyword. */
    public function list(ScenarioPlanningProject $project): array
    {
        $order = Dimension::ordered()->pluck('id')->flip();

        return $project->projectForces()
            ->with(['drivingForce' => fn ($q) => $q->withCount('supporting_signals')->with('dimension.environment')])
            ->withCount(ProjectDrivingForce::openCommentsCount())
            ->get()
            ->sortBy(fn ($link) => sprintf('%03d-%s', $order[$link->drivingForce?->dimension_id] ?? 999, strtolower((string) $link->drivingForce?->keyword)))
            ->values()
            ->map(fn (ProjectDrivingForce $link) => [
                'id' => $link->uuid,
                'inclusion_status' => $link->inclusion_status,
                'origin' => $link->origin,
                'influence_note' => $link->influence_note,
                'is_predetermined' => $link->is_predetermined,
                'open_comments' => (int) $link->getAttribute('open_comments'),
                'force' => $this->forceSummary($link->drivingForce),
            ])
            ->all();
    }

    /** Library forces not yet in the list, from any month; deleted forces are not offered. */
    public function library(ScenarioPlanningProject $project): array
    {
        return DrivingForce::whereNotIn('id', $project->projectForces()->select('driving_force_id'))
            ->with('dimension.environment')
            ->withCount('supporting_signals')
            ->orderBy('keyword')
            ->get()
            ->map(fn (DrivingForce $force) => $this->forceSummary($force))
            ->all();
    }

    /**
     * Included forces per method category, so empty categories stand out.
     *
     * @return array<int, array{code: ?string, name: string, environment: ?string, included: int}>
     */
    public function coverage(ScenarioPlanningProject $project): array
    {
        $counts = $project->projectForces()
            ->where('inclusion_status', 'included')
            ->join('driving_forces', 'driving_forces.id', '=', 'project_driving_forces.driving_force_id')
            ->groupBy('driving_forces.dimension_id')
            ->pluck(DB::raw('count(*)'), 'driving_forces.dimension_id');

        return Dimension::ordered()->with('environment')->get()->map(fn (Dimension $dimension) => [
            'code' => $dimension->code,
            'name' => $dimension->name,
            'environment' => $dimension->environment?->name,
            'included' => (int) ($counts[$dimension->id] ?? 0),
        ])->all();
    }

    private function forceSummary(?DrivingForce $force): ?array
    {
        return $force ? [
            'id' => $force->uuid,
            'keyword' => $force->keyword,
            'description' => $force->description,
            'dimension' => $force->dimension?->name,
            'environment' => $force->dimension?->environment?->name,
            'status' => $force->status,
            'signals_count' => (int) ($force->supporting_signals_count ?? 0),
            'is_deleted' => $force->trashed(),
        ] : null;
    }
}
