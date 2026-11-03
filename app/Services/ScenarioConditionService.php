<?php

namespace App\Services;

use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 5 (roadmap R1.5): the conditions of each future, an ordered list per quadrant of the
 * approved framework. A pole expression names its axis; a force behaviour links an included
 * force that is not an axis member (axis forces are described by their poles). The exercise's
 * predetermined forces hold in every quadrant and are shown, not entered per quadrant.
 */
class ScenarioConditionService
{
    /** Conditions per quadrant before step 5 counts as done. */
    public const MIN_PER_QUADRANT = 2;

    public function create(ScenarioQuadrant $quadrant, array $validated, User $user): ScenarioCondition
    {
        $project = $this->requireApproved($quadrant->scenario);
        $attributes = $this->attributes($project, $validated);

        $condition = DB::transaction(function () use ($quadrant, $attributes, $user) {
            ScenarioQuadrant::whereKey($quadrant->id)->lockForUpdate()->first();

            return $quadrant->conditions()->create($attributes + [
                'sort_order' => (int) $quadrant->conditions()->max('sort_order') + 1,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
        $this->flagNarrative($quadrant);

        return $condition;
    }

    public function update(ScenarioCondition $condition, array $validated, User $user): void
    {
        $project = $this->requireApproved($condition->quadrant->scenario);

        $condition->update($this->attributes($project, $validated) + ['updated_by' => $user->id]);
        $this->flagNarrative($condition->quadrant);
        if ($condition->wasChanged(['kind', 'text', 'axis', 'project_driving_force_id'])) {
            app(StalenessService::class)->conditionChanged($condition, 'Kondisi diubah: '.Str::limit($condition->text, 80));
        }
    }

    public function delete(ScenarioCondition $condition): void
    {
        $this->requireApproved($condition->quadrant->scenario);

        app(StalenessService::class)->conditionChanged($condition, 'Kondisi dihapus: '.Str::limit($condition->text, 80));
        $condition->delete();
        $this->flagNarrative($condition->quadrant);
    }

    /** A narrative written before this change may no longer reflect the conditions (roadmap R1.10). */
    private function flagNarrative(ScenarioQuadrant $quadrant): void
    {
        app(StalenessService::class)->markQuadrants(collect([$quadrant->fresh() ?? $quadrant]), 'Kondisi berubah setelah narasi dibuat', onlyWithNarrative: true);
    }

    /** Swap with the neighbour above or below; at either end nothing changes. */
    public function move(ScenarioCondition $condition, string $direction): void
    {
        $this->requireApproved($condition->quadrant->scenario);

        DB::transaction(function () use ($condition, $direction) {
            $siblings = $condition->quadrant->conditions()->lockForUpdate()->get()->values();
            $index = $siblings->search(fn ($item) => $item->id === $condition->id);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! isset($siblings[$target])) {
                return;
            }

            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            foreach ($ordered as $position => $item) {
                $item->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * Quadrants with their conditions. `shared` marks a text that appears in all four quadrants:
     * possibly a predetermined element rather than a condition of one future.
     */
    public function quadrants(Scenario $framework): array
    {
        $quadrants = $framework->quadrants()->with(['conditions.projectForce.drivingForce'])->get();
        $normalize = fn (string $text) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
        $shared = $quadrants->count() === count(Scenario::POSITIONS)
            ? $quadrants->map(fn ($quadrant) => $quadrant->conditions->pluck('text')->map($normalize)->unique())
                ->reduce(fn (?Collection $carry, Collection $texts) => $carry === null ? $texts : $carry->intersect($texts))
                ->flip()
            : collect();

        return $quadrants->map(fn (ScenarioQuadrant $quadrant) => [
            'id' => $quadrant->uuid,
            'position' => $quadrant->position,
            'poles' => $framework->poles($quadrant->position),
            'title' => $quadrant->title,
            'summary' => $quadrant->summary,
            'conditions' => $quadrant->conditions->map(fn (ScenarioCondition $condition) => [
                'id' => $condition->uuid,
                'kind' => $condition->kind,
                'text' => $condition->text,
                'axis' => $condition->axis,
                'force' => $condition->projectForce ? ['id' => $condition->projectForce->uuid, 'keyword' => $condition->projectForce->drivingForce?->keyword] : null,
                'origin' => $condition->origin,
                'review_status' => $condition->review_status,
                'shared' => $shared->has($normalize($condition->text)),
            ])->values()->all(),
        ])->values()->all();
    }

    /** Predetermined forces of the exercise: they hold in every scenario. */
    public function predetermined(ScenarioPlanningProject $project): array
    {
        return $project->projectForces()
            ->where('inclusion_status', 'included')
            ->where('is_predetermined', true)
            ->with(['drivingForce.dimension', 'consensus'])
            ->get()
            ->map(fn (ProjectDrivingForce $link) => $this->forceSummary($link))
            ->sortBy('keyword')
            ->values()
            ->all();
    }

    /** Included forces outside both axes, which a force-behaviour condition may describe. */
    public function forces(ScenarioPlanningProject $project): array
    {
        return $this->nonAxisForces($project)
            ->load(['drivingForce.dimension', 'consensus'])
            ->map(fn (ProjectDrivingForce $link) => $this->forceSummary($link))
            ->sortBy('keyword')
            ->values()
            ->all();
    }

    /** True when every quadrant of the approved framework has enough accepted conditions. */
    public function complete(Scenario $framework): bool
    {
        $counts = $framework->quadrants()->withCount(['conditions' => fn ($q) => $q->where('review_status', 'accepted')])->pluck('conditions_count');

        return $counts->count() === count(Scenario::POSITIONS) && $counts->min() >= self::MIN_PER_QUADRANT;
    }

    private function attributes(ScenarioPlanningProject $project, array $validated): array
    {
        $kind = $validated['kind'];
        $axis = $kind === 'pole_expression' ? ($validated['axis'] ?? null) : null;
        if ($kind === 'pole_expression' && $axis === null) {
            throw ValidationException::withMessages(['axis' => 'Pilih sumbu yang diekspresikan kondisi ini.']);
        }

        $forceId = null;
        if ($kind === 'force_behaviour') {
            $link = empty($validated['force']) ? null : $this->nonAxisForces($project)->firstWhere('uuid', $validated['force']);
            if (! $link) {
                throw ValidationException::withMessages(['force' => 'Pilih driving force yang disertakan dan bukan anggota sumbu. Sumbu dijelaskan lewat kondisi kutub.']);
            }
            $forceId = $link->id;
        }

        return ['kind' => $kind, 'text' => $validated['text'], 'axis' => $axis, 'project_driving_force_id' => $forceId];
    }

    /** @return EloquentCollection<int, ProjectDrivingForce> */
    private function nonAxisForces(ScenarioPlanningProject $project): EloquentCollection
    {
        $framework = $project->framework()->first();
        $axisIds = $framework
            ? DB::table('critical_uncertainty_forces')->whereIn('critical_uncertainty_id', array_filter([$framework->axis_a_uncertainty_id, $framework->axis_b_uncertainty_id]))->pluck('project_driving_force_id')
            : collect();

        return $project->projectForces()->where('inclusion_status', 'included')->whereNotIn('id', $axisIds)->get();
    }

    private function requireApproved(Scenario $framework): ScenarioPlanningProject
    {
        if (! $framework->isFrameworkApproved() || ! $framework->project) {
            throw ValidationException::withMessages(['conditions' => 'Setujui kerangka skenario (G5) dulu sebelum menyusun kondisi.']);
        }

        return $framework->project;
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
