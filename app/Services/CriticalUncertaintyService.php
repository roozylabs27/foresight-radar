<?php

namespace App\Services;

use App\Models\CriticalUncertainty;
use App\Models\CrossImpact;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 3, second half (roadmap R0.4): the team groups exercise forces into
 * named critical uncertainties and approves exactly two as axis A and B
 * (gate G4). The choice is a human decision; scores and cross-impact only
 * inform it. Interim rules pending Pak Jaka (client questions 3–5): grouping is
 * allowed, predetermined forces are excluded, the coordinator approves.
 */
class CriticalUncertaintyService
{
    /** Axes this strongly linked in the cross-impact matrix (|score|) need an acknowledgement. */
    public const MAX_AXIS_INFLUENCE = ScenarioService::MAX_AXIS_INFLUENCE;

    public function create(ScenarioPlanningProject $project, array $validated, User $user): CriticalUncertainty
    {
        $this->requireScores($project);
        $links = $this->resolveForces($project, $validated['forces']);

        return DB::transaction(function () use ($project, $validated, $links, $user) {
            $uncertainty = CriticalUncertainty::create($this->attributes($validated) + [
                'project_id' => $project->id,
                'status' => 'proposed',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $uncertainty->projectForces()->sync($links->pluck('id'));

            return $uncertainty;
        });
    }

    public function update(CriticalUncertainty $uncertainty, array $validated, User $user): void
    {
        $this->requireProposed($uncertainty);
        $links = $this->resolveForces($uncertainty->project, $validated['forces']);

        DB::transaction(function () use ($uncertainty, $validated, $links, $user) {
            $uncertainty->update($this->attributes($validated) + ['updated_by' => $user->id]);
            $uncertainty->projectForces()->sync($links->pluck('id'));
        });
    }

    public function delete(CriticalUncertainty $uncertainty): void
    {
        $this->requireProposed($uncertainty);
        $uncertainty->delete();
    }

    /**
     * Approve as axis A or B.
     *
     * @throws ValidationException when scores are not agreed, two are already approved, the axis is
     *                             taken, a member force no longer qualifies, or the axes are strongly
     *                             linked and the link was not acknowledged
     */
    public function approve(CriticalUncertainty $uncertainty, string $axis, bool $acknowledged, User $user): void
    {
        $project = $uncertainty->project;
        $this->requireScores($project);

        DB::transaction(function () use ($project, $uncertainty, $axis, $acknowledged, $user) {
            ScenarioPlanningProject::whereKey($project->id)->lockForUpdate()->first();

            $others = $project->criticalUncertainties()->where('status', 'approved')->whereKeyNot($uncertainty->id)->get();
            if ($others->count() >= 2) {
                throw ValidationException::withMessages(['axis' => 'Sudah ada dua critical uncertainty yang disetujui. Batalkan salah satunya dulu.']);
            }
            if ($others->contains('axis', $axis)) {
                throw ValidationException::withMessages(['axis' => "Sumbu {$axis} sudah dipakai critical uncertainty lain."]);
            }
            $this->resolveForces($project, $uncertainty->projectForces()->pluck('project_driving_forces.uuid')->all());

            $other = $others->first();
            if ($other) {
                $influence = $this->influence($uncertainty, $other);
                if ($influence >= self::MAX_AXIS_INFLUENCE && ! $acknowledged) {
                    throw ValidationException::withMessages([
                        'acknowledge_dependency' => "Driving force di kedua sumbu saling memengaruhi kuat (skor cross-impact {$influence}). Sumbu skenario sebaiknya independen; konfirmasi bila tetap ingin memakai pasangan ini.",
                    ]);
                }
            }

            $uncertainty->update(['status' => 'approved', 'axis' => $axis, 'approved_by' => $user->id, 'approved_at' => now(), 'updated_by' => $user->id]);
        });
    }

    public function withdraw(CriticalUncertainty $uncertainty, User $user): void
    {
        $uncertainty->update(['status' => 'proposed', 'axis' => null, 'approved_by' => null, 'approved_at' => null, 'updated_by' => $user->id]);
    }

    /** The exercise's uncertainties: approved first by axis, then proposals, newest first. */
    public function list(ScenarioPlanningProject $project): array
    {
        return $project->criticalUncertainties()
            ->with(['projectForces' => fn ($q) => $q->with(['consensus', 'drivingForce.dimension']), 'approver:id,name'])
            ->orderByRaw("CASE status WHEN 'approved' THEN 0 ELSE 1 END")
            ->orderBy('axis')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CriticalUncertainty $uncertainty) => [
                'id' => $uncertainty->uuid,
                'name' => $uncertainty->name,
                'description' => $uncertainty->description,
                'low_pole_label' => $uncertainty->low_pole_label,
                'high_pole_label' => $uncertainty->high_pole_label,
                'selection_rationale' => $uncertainty->selection_rationale,
                'status' => $uncertainty->status,
                'axis' => $uncertainty->axis,
                'approved_by' => $uncertainty->approver?->name,
                'approved_at' => $uncertainty->approved_at?->toISOString(),
                'forces' => $uncertainty->projectForces->map(fn ($link) => $this->forceSummary($link))->values()->all(),
            ])
            ->all();
    }

    /** Included, scored, non-predetermined forces that may join an uncertainty. */
    public function candidates(ScenarioPlanningProject $project): array
    {
        return $project->projectForces()
            ->where('inclusion_status', 'included')
            ->where('is_predetermined', false)
            ->with(['consensus', 'drivingForce.dimension'])
            ->get()
            ->map(fn ($link) => $this->forceSummary($link))
            ->sortByDesc(fn ($force) => ($force['impact'] ?? 0) * ($force['uncertainty'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * Pairs of the exercise's uncertainties, strongest first, with how much their forces influence
     * each other. Advice only: the team decides.
     */
    public function pairs(ScenarioPlanningProject $project): array
    {
        $uncertainties = $project->criticalUncertainties()->where('status', '!=', 'retired')
            ->with(['projectForces.consensus'])
            ->get()
            ->values();

        $pairs = [];
        foreach ($uncertainties as $i => $a) {
            foreach ($uncertainties->slice($i + 1) as $b) {
                $influence = $this->influence($a, $b);
                $pairs[] = [
                    'a' => ['id' => $a->uuid, 'name' => $a->name],
                    'b' => ['id' => $b->uuid, 'name' => $b->name],
                    'influence' => $influence,
                    'dependent' => $influence >= self::MAX_AXIS_INFLUENCE,
                    'strength' => round($this->strength($a) + $this->strength($b), 1),
                ];
            }
        }
        usort($pairs, fn ($x, $y) => [$x['dependent'], -$x['strength']] <=> [$y['dependent'], -$y['strength']]);

        return array_slice($pairs, 0, 5);
    }

    /** Strongest |cross-impact| between the forces of two uncertainties, either direction. */
    public function influence(CriticalUncertainty $a, CriticalUncertainty $b): int
    {
        $forcesA = $a->projectForces()->pluck('project_driving_forces.driving_force_id');
        $forcesB = $b->projectForces()->pluck('project_driving_forces.driving_force_id');

        return (int) CrossImpact::where(fn ($q) => $q->whereIn('source_driving_force_id', $forcesA)->whereIn('target_driving_force_id', $forcesB))
            ->orWhere(fn ($q) => $q->whereIn('source_driving_force_id', $forcesB)->whereIn('target_driving_force_id', $forcesA))
            ->get(['score'])
            ->max(fn ($impact) => abs((int) $impact->score));
    }

    /** Mean impact × uncertainty of the uncertainty's forces. */
    private function strength(CriticalUncertainty $uncertainty): float
    {
        $scores = $uncertainty->projectForces->map(fn ($link) => ($link->consensus?->impact ?? 0) * ($link->consensus?->uncertainty ?? 0));

        return $scores->isEmpty() ? 0.0 : (float) $scores->avg();
    }

    /**
     * The exercise forces behind the given link uuids. Each must belong to the exercise, be included
     * and not be predetermined (a predetermined element holds in every scenario, so it is no axis).
     *
     * @param  array<int, string>  $uuids
     * @return Collection<int, ProjectDrivingForce>
     */
    private function resolveForces(ScenarioPlanningProject $project, array $uuids): Collection
    {
        $links = $project->projectForces()->whereIn('uuid', $uuids)->get();

        if ($links->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages(['forces' => 'Pilih driving force dari latihan ini.']);
        }
        if ($links->contains(fn ($link) => ! $link->isIncluded())) {
            throw ValidationException::withMessages(['forces' => 'Hanya driving force yang disertakan yang bisa menjadi critical uncertainty.']);
        }
        if ($links->contains('is_predetermined', true)) {
            throw ValidationException::withMessages(['forces' => 'Driving force predetermined berlaku di semua skenario, jadi tidak dipakai sebagai critical uncertainty.']);
        }

        return $links;
    }

    private function requireScores(ScenarioPlanningProject $project): void
    {
        if (! $project->scoresConfirmed()) {
            throw ValidationException::withMessages(['forces' => 'Sepakati skor impact dan uncertainty dulu.']);
        }
    }

    private function requireProposed(CriticalUncertainty $uncertainty): void
    {
        if ($uncertainty->isApproved()) {
            throw ValidationException::withMessages(['name' => 'Critical uncertainty ini sudah disetujui. Batalkan persetujuannya dulu untuk mengubah.']);
        }
    }

    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'low_pole_label' => $validated['low_pole_label'],
            'high_pole_label' => $validated['high_pole_label'],
            'selection_rationale' => $validated['selection_rationale'],
        ];
    }

    private function forceSummary(ProjectDrivingForce $link): array
    {
        return [
            'id' => $link->uuid,
            'keyword' => $link->drivingForce?->keyword,
            'dimension' => $link->drivingForce?->dimension?->name,
            'impact' => $link->consensus?->impact,
            'uncertainty' => $link->consensus?->uncertainty,
        ];
    }
}
