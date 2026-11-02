<?php

namespace App\Services;

use App\Models\EarlyWarningIndicator;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Step 9 (roadmap R1.9): the early warning indicator register of an exercise with an approved
 * framework. Each indicator carries its expected direction per quadrant; one with the same
 * direction everywhere does not tell the scenarios apart and is flagged. Saving an imported
 * proposal accepts it. Observations and signal matching are P2.
 */
class EarlyWarningIndicatorService
{
    public function create(ScenarioPlanningProject $project, array $validated, User $user): EarlyWarningIndicator
    {
        $framework = $this->requireApproved($project);
        $links = $this->resolveLinks($project, $validated);

        return DB::transaction(function () use ($project, $framework, $validated, $links, $user) {
            $indicator = EarlyWarningIndicator::create($this->attributes($validated, $links) + [
                'project_id' => $project->id,
                'status' => $validated['status'] ?? 'active',
                'origin' => 'human',
                'review_status' => 'accepted',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->syncDirections($indicator, $framework, $validated['directions'] ?? []);

            return $indicator;
        });
    }

    public function update(EarlyWarningIndicator $indicator, array $validated, User $user): void
    {
        $framework = $this->requireApproved($indicator->project);
        $links = $this->resolveLinks($indicator->project, $validated);

        DB::transaction(function () use ($indicator, $framework, $validated, $links, $user) {
            $indicator->update($this->attributes($validated, $links) + [
                'status' => $validated['status'] ?? $indicator->status,
                'review_status' => 'accepted',
                'updated_by' => $user->id,
            ]);
            $this->syncDirections($indicator, $framework, $validated['directions'] ?? []);
        });
    }

    public function delete(EarlyWarningIndicator $indicator): void
    {
        $this->requireApproved($indicator->project);

        $indicator->delete();
    }

    /** The register, alphabetical, with directions keyed by quadrant position. */
    public function list(ScenarioPlanningProject $project): array
    {
        return EarlyWarningIndicator::where('project_id', $project->id)
            ->with(['directions.quadrant', 'uncertainty:id,uuid,name', 'projectForce.drivingForce', 'owner:id,uuid,name'])
            ->orderBy('name')
            ->get()
            ->map(function (EarlyWarningIndicator $indicator) {
                $directions = $indicator->directions->mapWithKeys(fn ($row) => [$row->quadrant->position => ['direction' => $row->expected_direction, 'note' => $row->note]]);

                return [
                    'id' => $indicator->uuid,
                    'name' => $indicator->name,
                    'description' => $indicator->description,
                    'uncertainty' => $indicator->uncertainty ? ['id' => $indicator->uncertainty->uuid, 'name' => $indicator->uncertainty->name] : null,
                    'force' => $indicator->projectForce ? ['id' => $indicator->projectForce->uuid, 'keyword' => $indicator->projectForce->drivingForce?->keyword] : null,
                    'metric' => $indicator->metric,
                    'unit' => $indicator->unit,
                    'baseline_value' => $indicator->baseline_value,
                    'baseline_date' => $indicator->baseline_date?->toDateString(),
                    'frequency' => $indicator->frequency,
                    'owner' => $indicator->owner ? ['id' => $indicator->owner->uuid, 'name' => $indicator->owner->name] : null,
                    'source_type' => $indicator->source_type,
                    'threshold_rule' => $indicator->threshold_rule,
                    'status' => $indicator->status,
                    'origin' => $indicator->origin,
                    'review_status' => $indicator->review_status,
                    'directions' => (object) $directions->all(),
                    'discriminates' => self::discriminates($directions->pluck('direction')),
                ];
            })
            ->values()
            ->all();
    }

    /** Quadrants with the indicator strings their narrative wrote (step 6). */
    public function quadrants(Scenario $framework): array
    {
        return $framework->quadrants()->get()->map(fn ($quadrant) => [
            'id' => $quadrant->uuid,
            'position' => $quadrant->position,
            'title' => $quadrant->title,
            'poles' => $framework->poles($quadrant->position),
            'narrative_indicators' => array_values($quadrant->early_indicators ?? []),
        ])->values()->all();
    }

    /** At least two different expected directions across the quadrants. */
    public static function discriminates(Collection $directions): bool
    {
        return $directions->unique()->count() >= 2;
    }

    /** True when an accepted, active indicator has a direction in every quadrant. */
    public function complete(ScenarioPlanningProject $project): bool
    {
        return EarlyWarningIndicator::where('project_id', $project->id)
            ->where('review_status', 'accepted')
            ->where('status', 'active')
            ->withCount('directions')
            ->get()
            ->contains(fn ($indicator) => $indicator->directions_count >= count(Scenario::POSITIONS));
    }

    /**
     * Indicator strings of framework quadrant narratives become proposals, one per distinct text per
     * exercise, unless that exercise already has an indicator with the same name. Runs from the R1.9
     * migration and is safe to repeat. Returns the number made.
     */
    public static function importNarrativeIndicators(): int
    {
        $rows = DB::table('scenario_quadrants')
            ->join('scenarios', 'scenarios.id', '=', 'scenario_quadrants.scenario_id')
            ->whereNotNull('scenarios.project_id')
            ->whereNotNull('scenario_quadrants.early_indicators')
            ->get(['scenarios.project_id', 'scenario_quadrants.tenant_id', 'scenario_quadrants.early_indicators']);

        $normalize = fn (string $text) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
        $made = 0;
        foreach ($rows->groupBy('project_id') as $projectId => $quadrants) {
            $existing = DB::table('early_warning_indicators')->where('project_id', $projectId)->pluck('name')->map($normalize)->flip();
            foreach ($quadrants as $quadrant) {
                foreach ((array) json_decode((string) $quadrant->early_indicators, true) as $text) {
                    $name = Str::limit(trim((string) $text), 160, '');
                    if ($name === '' || $existing->has($normalize($name))) {
                        continue;
                    }
                    DB::table('early_warning_indicators')->insert([
                        'uuid' => (string) Uuid::uuid4(),
                        'tenant_id' => $quadrant->tenant_id,
                        'project_id' => $projectId,
                        'name' => $name,
                        'description' => 'Diusulkan dari narasi skenario; lengkapi arah per kuadran dan cara pemantauannya.',
                        'frequency' => 'ad_hoc',
                        'source_type' => 'manual',
                        'status' => 'active',
                        'origin' => 'migrated',
                        'review_status' => 'proposed',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $existing->put($normalize($name), 0);
                    $made++;
                }
            }
        }

        return $made;
    }

    /** @param  array{0: ?int, 1: ?int}  $links */
    private function attributes(array $validated, array $links): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'critical_uncertainty_id' => $links[0],
            'project_driving_force_id' => $links[1],
            'metric' => $validated['metric'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'baseline_value' => $validated['baseline_value'] ?? null,
            'baseline_date' => $validated['baseline_date'] ?? null,
            'frequency' => $validated['frequency'] ?? 'ad_hoc',
            'owner_id' => empty($validated['owner']) ? null : User::where('uuid', $validated['owner'])->value('id'),
            'source_type' => $validated['source_type'] ?? 'manual',
            'threshold_rule' => $validated['threshold_rule'] ?? null,
        ];
    }

    /** @return array{0: ?int, 1: ?int} ids of the linked critical uncertainty and exercise force */
    private function resolveLinks(ScenarioPlanningProject $project, array $validated): array
    {
        $uncertaintyId = null;
        if (! empty($validated['uncertainty'])) {
            $uncertaintyId = $project->criticalUncertainties()->where('uuid', $validated['uncertainty'])->value('id');
            if (! $uncertaintyId) {
                throw ValidationException::withMessages(['uncertainty' => 'Pilih critical uncertainty dari latihan ini.']);
            }
        }

        $forceId = null;
        if (! empty($validated['force'])) {
            $forceId = $project->projectForces()->where('inclusion_status', 'included')->where('uuid', $validated['force'])->value('id');
            if (! $forceId) {
                throw ValidationException::withMessages(['force' => 'Pilih driving force yang disertakan dalam latihan ini.']);
            }
        }

        return [$uncertaintyId, $forceId];
    }

    /** @param  array<string, array{direction: string, note?: ?string}>  $directions */
    private function syncDirections(EarlyWarningIndicator $indicator, Scenario $framework, array $directions): void
    {
        $quadrants = $framework->quadrants()->pluck('scenario_quadrants.id', 'position');
        $indicator->directions()->delete();
        foreach ($directions as $position => $row) {
            $indicator->directions()->create([
                'scenario_quadrant_id' => $quadrants[$position],
                'expected_direction' => $row['direction'],
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    private function requireApproved(ScenarioPlanningProject $project): Scenario
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['indicators' => 'Setujui kerangka skenario (G5) dulu sebelum menyusun indikator peringatan dini.']);
        }

        return $framework;
    }
}
