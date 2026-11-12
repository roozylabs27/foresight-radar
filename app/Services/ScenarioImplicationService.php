<?php

namespace App\Services;

use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\User;
use App\Support\Provenance;
use App\Support\TextSimilarity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Step 7 (roadmap R1.7): opportunities and challenges per quadrant of the approved framework,
 * linked to included exercise forces and to conditions of the same quadrant. Giving an imported
 * (untyped) proposal a type accepts it. Step 7 is done when every quadrant has at least one
 * accepted opportunity and one accepted challenge.
 */
class ScenarioImplicationService
{
    public function create(ScenarioQuadrant $quadrant, array $validated, User $user): ScenarioImplication
    {
        $project = $this->requireApproved($quadrant->scenario);
        [$forces, $conditions] = $this->resolveLinks($project, $quadrant, $validated);

        return DB::transaction(function () use ($quadrant, $validated, $forces, $conditions, $user) {
            $implication = $quadrant->implicationItems()->create($this->attributes($validated) + [
                'origin' => Provenance::HUMAN,
                'review_status' => 'accepted',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->syncLinks($implication, $forces, $conditions);

            return $implication;
        });
    }

    public function update(ScenarioImplication $implication, array $validated, User $user): void
    {
        $project = $this->requireApproved($implication->quadrant->scenario);
        [$forces, $conditions] = $this->resolveLinks($project, $implication->quadrant, $validated);

        DB::transaction(function () use ($implication, $validated, $forces, $conditions, $user) {
            $implication->update($this->attributes($validated) + ['review_status' => 'accepted', 'updated_by' => $user->id]);
            $this->syncLinks($implication, $forces, $conditions);
            // Audit U6: the team reviewed this item; responses relying on it need a look.
            app(StalenessService::class)->clear($implication);
            app(StalenessService::class)->implicationChanged($implication, 'Implikasi diubah: '.Str::limit($implication->title, 80));
        });
    }

    public function delete(ScenarioImplication $implication): void
    {
        $this->requireApproved($implication->quadrant->scenario);

        app(StalenessService::class)->implicationChanged($implication, 'Implikasi dihapus: '.Str::limit($implication->title, 80));
        $implication->delete();
    }

    /**
     * Quadrants with their items, the narrative's own implications text, and the conditions an item
     * may link to. `generic` marks a title found in all four quadrants (it does not tell the futures apart).
     */
    public function quadrants(Scenario $framework): array
    {
        $quadrants = $framework->quadrants()->with(['implicationItems.projectForces.drivingForce', 'implicationItems.conditions', 'conditions'])->get();
        $normalize = fn (string $text) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
        $generic = $quadrants->count() === count(Scenario::POSITIONS)
            ? $quadrants->map(fn ($quadrant) => $quadrant->implicationItems->pluck('title')->map($normalize)->unique())
                ->reduce(fn (?Collection $carry, Collection $titles) => $carry === null ? $titles : $carry->intersect($titles))
                ->flip()
            : collect();

        $similar = $this->similarAcrossQuadrants($quadrants);

        return $quadrants->map(fn (ScenarioQuadrant $quadrant) => [
            'id' => $quadrant->uuid,
            'position' => $quadrant->position,
            'poles' => $framework->poles($quadrant->position),
            'title' => $quadrant->title,
            'summary' => $quadrant->summary,
            'narrative_implications' => $quadrant->implications,
            'conditions' => $quadrant->conditions->map(fn ($condition) => ['id' => $condition->uuid, 'text' => $condition->text])->values()->all(),
            'items' => $quadrant->implicationItems->map(fn (ScenarioImplication $item) => [
                'id' => $item->uuid,
                'type' => $item->type,
                'title' => $item->title,
                'description' => $item->description,
                'impact_level' => $item->impact_level,
                'priority' => $item->priority,
                'origin' => $item->origin,
                'review_status' => $item->review_status,
                'stale_reason' => $item->stale_since ? $item->stale_reason : null,
                'forces' => $item->projectForces->map(fn ($link) => ['id' => $link->uuid, 'keyword' => $link->drivingForce?->keyword])->values()->all(),
                'conditions' => $item->conditions->map(fn ($condition) => ['id' => $condition->uuid, 'text' => $condition->text])->values()->all(),
                'generic' => $generic->has($normalize($item->title)) || $similar->has($item->uuid),
                'similar_to' => $similar->get($item->uuid, []),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Roadmap R2.6: items whose title overlaps an item of another quadrant by at least
     * TextSimilarity::THRESHOLD, keyed by item uuid, with the other quadrants' positions.
     *
     * @param  Collection<int, ScenarioQuadrant>  $quadrants
     * @return Collection<string, non-empty-array<int, string>>
     */
    private function similarAcrossQuadrants(Collection $quadrants): Collection
    {
        $items = $quadrants->flatMap(fn (ScenarioQuadrant $quadrant) => $quadrant->implicationItems->map(fn (ScenarioImplication $item) => [
            'id' => $item->uuid,
            'position' => $quadrant->position,
            'words' => TextSimilarity::words($item->title),
        ]))->values();

        $similar = [];
        foreach ($items as $i => $a) {
            foreach ($items->slice($i + 1) as $b) {
                if ($a['position'] !== $b['position'] && TextSimilarity::jaccard($a['words'], $b['words']) >= TextSimilarity::THRESHOLD) {
                    $similar[$a['id']][] = $b['position'];
                    $similar[$b['id']][] = $a['position'];
                }
            }
        }

        return collect($similar)->map(fn ($positions) => array_values(array_unique($positions)));
    }

    /** Included forces of the exercise an item may link to. */
    public function forces(ScenarioPlanningProject $project): array
    {
        return $project->projectForces()->where('inclusion_status', 'included')->with('drivingForce')->get()
            ->map(fn (ProjectDrivingForce $link) => ['id' => $link->uuid, 'keyword' => $link->drivingForce?->keyword])
            ->sortBy('keyword')->values()->all();
    }

    public function complete(Scenario $framework): bool
    {
        $quadrants = $framework->quadrants()->with(['implicationItems' => fn ($q) => $q->where('review_status', 'accepted')])->get();

        return $quadrants->count() === count(Scenario::POSITIONS)
            && $quadrants->every(fn ($quadrant) => $quadrant->implicationItems->contains('type', 'opportunity') && $quadrant->implicationItems->contains('type', 'challenge'));
    }

    /**
     * One untyped proposal per framework quadrant whose narrative has implications text and that has
     * no imported item yet. Runs from the R1.7 migration and is safe to repeat. Returns the number made.
     */
    public static function importNarrativeImplications(): int
    {
        $rows = DB::table('scenario_quadrants')
            ->join('scenarios', 'scenarios.id', '=', 'scenario_quadrants.scenario_id')
            ->whereNotNull('scenarios.project_id')
            ->whereNotNull('scenario_quadrants.implications')
            ->where('scenario_quadrants.implications', '!=', '')
            ->whereNotExists(fn ($q) => $q->from('scenario_implications')
                ->whereColumn('scenario_implications.scenario_quadrant_id', 'scenario_quadrants.id')
                ->where('scenario_implications.origin', 'migrated'))
            ->get(['scenario_quadrants.id', 'scenario_quadrants.tenant_id', 'scenario_quadrants.title', 'scenario_quadrants.implications']);

        foreach ($rows as $row) {
            DB::table('scenario_implications')->insert([
                'uuid' => (string) Uuid::uuid4(),
                'tenant_id' => $row->tenant_id,
                'scenario_quadrant_id' => $row->id,
                'type' => null,
                'title' => Str::limit('Implikasi narasi: '.$row->title, 160, ''),
                'description' => $row->implications,
                'origin' => Provenance::MIGRATED,
                'review_status' => 'proposed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $rows->count();
    }

    private function attributes(array $validated): array
    {
        return [
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'impact_level' => $validated['impact_level'] ?? null,
            'priority' => $validated['priority'] ?? null,
        ];
    }

    /**
     * @return array{0: Collection<int, int>, 1: Collection<int, int>} ids of the linked forces and conditions
     */
    private function resolveLinks(ScenarioPlanningProject $project, ScenarioQuadrant $quadrant, array $validated): array
    {
        $forceUuids = array_unique($validated['forces'] ?? []);
        $forces = $project->projectForces()->where('inclusion_status', 'included')->whereIn('uuid', $forceUuids)->pluck('id');
        if ($forces->count() !== count($forceUuids)) {
            throw ValidationException::withMessages(['forces' => 'Pilih driving force yang disertakan dalam latihan ini.']);
        }

        $conditionUuids = array_unique($validated['conditions'] ?? []);
        $conditions = $quadrant->conditions()->whereIn('uuid', $conditionUuids)->pluck('id');
        if ($conditions->count() !== count($conditionUuids)) {
            throw ValidationException::withMessages(['conditions' => 'Pilih kondisi dari kuadran yang sama.']);
        }

        return [$forces, $conditions];
    }

    /**
     * Links are rewritten as a whole: both targets share `implication_links`, which BelongsToMany::sync
     * cannot tell apart.
     *
     * @param  Collection<int, int>  $forces
     * @param  Collection<int, int>  $conditions
     */
    private function syncLinks(ScenarioImplication $implication, Collection $forces, Collection $conditions): void
    {
        DB::table('implication_links')->where('scenario_implication_id', $implication->id)->delete();
        DB::table('implication_links')->insert([
            ...$forces->map(fn ($id) => ['scenario_implication_id' => $implication->id, 'project_driving_force_id' => $id, 'scenario_condition_id' => null])->all(),
            ...$conditions->map(fn ($id) => ['scenario_implication_id' => $implication->id, 'project_driving_force_id' => null, 'scenario_condition_id' => $id])->all(),
        ]);
    }

    private function requireApproved(Scenario $framework): ScenarioPlanningProject
    {
        if (! $framework->isFrameworkApproved() || ! $framework->project) {
            throw ValidationException::withMessages(['implications' => 'Setujui kerangka skenario (G5) dulu sebelum menyusun peluang dan tantangan.']);
        }

        return $framework->project;
    }
}
