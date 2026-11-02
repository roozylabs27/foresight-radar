<?php

namespace App\Services;

use App\Models\Scenario;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\StrategicResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 8 (roadmap R1.8): strategic responses of an exercise with an approved framework. A response
 * is entered once and rated per quadrant; robustness follows from the fit. Every response starts
 * proposed, and any edit returns it to proposed. Approval (gate G7) needs a rationale and a fit for
 * all four quadrants.
 */
class StrategicResponseService
{
    /** Applies in this many quadrants or more: robust. */
    public const ROBUST_FROM = 3;

    public function create(ScenarioPlanningProject $project, array $validated, User $user): StrategicResponse
    {
        $framework = $this->requireApproved($project);
        [$quadrants, $implications] = $this->resolve($framework, $validated);

        return DB::transaction(function () use ($project, $validated, $quadrants, $implications, $user) {
            $response = StrategicResponse::create($this->attributes($validated) + [
                'project_id' => $project->id,
                'status' => 'proposed',
                'origin' => 'human',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->syncRelations($response, $validated, $quadrants, $implications);

            return $response;
        });
    }

    public function update(StrategicResponse $response, array $validated, User $user): void
    {
        $framework = $this->requireApproved($response->project);
        [$quadrants, $implications] = $this->resolve($framework, $validated);

        DB::transaction(function () use ($response, $validated, $quadrants, $implications, $user) {
            $response->update($this->attributes($validated) + [
                'status' => 'proposed',
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $user->id,
            ]);
            $this->syncRelations($response, $validated, $quadrants, $implications);
        });
    }

    /**
     * Gate G7.
     *
     * @throws ValidationException when the rationale or a quadrant's fit is missing
     */
    public function approve(StrategicResponse $response, User $user): void
    {
        $this->requireApproved($response->project);

        $errors = [];
        if (blank($response->rationale)) {
            $errors['rationale'] = 'Tulis alasan respons ini sebelum menyetujuinya.';
        }
        if ($response->fits()->count() < count(Scenario::POSITIONS)) {
            $errors['fit'] = 'Nilai kecocokan respons di keempat kuadran sebelum menyetujuinya.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $response->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'updated_by' => $user->id]);
    }

    public function reject(StrategicResponse $response, User $user): void
    {
        $this->requireApproved($response->project);

        $response->update(['status' => 'rejected', 'approved_by' => null, 'approved_at' => null, 'updated_by' => $user->id]);
    }

    /**
     * Responses ranked by the number of quadrants they apply to (the robustness view), then by
     * priority. `robustness` is null until at least one quadrant is rated.
     */
    public function list(ScenarioPlanningProject $project): array
    {
        return $project->strategicResponses()
            ->with(['fits.quadrant', 'implications.quadrant', 'owner:id,uuid,name', 'approver:id,name'])
            ->get()
            ->map(function (StrategicResponse $response) {
                $fit = $response->fits->mapWithKeys(fn ($row) => [$row->quadrant->position => $row->fit]);
                $applies = $fit->filter(fn ($value) => $value === 'applies')->count();

                return [
                    'id' => $response->uuid,
                    'title' => $response->title,
                    'description' => $response->description,
                    'rationale' => $response->rationale,
                    'initiatives' => $response->initiatives,
                    'priority' => $response->priority,
                    'owner' => $response->owner ? ['id' => $response->owner->uuid, 'name' => $response->owner->name] : null,
                    'depends_on' => $response->depends_on,
                    'status' => $response->status,
                    'origin' => $response->origin,
                    'approved_by' => $response->approver?->name,
                    'approved_at' => $response->approved_at?->toISOString(),
                    'fit' => (object) $fit->all(),
                    'applies' => $applies,
                    'robustness' => $fit->isEmpty() ? null : self::robustness($applies),
                    'implications' => $response->implications->map(fn ($item) => [
                        'id' => $item->uuid, 'type' => $item->type, 'title' => $item->title, 'position' => $item->quadrant?->position,
                    ])->values()->all(),
                ];
            })
            ->sortBy([['applies', 'desc'], fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99), ['title', 'asc']])
            ->values()
            ->all();
    }

    /** Opportunities and challenges of the framework a response may answer. */
    public function implicationOptions(Scenario $framework): array
    {
        return ScenarioImplication::whereIn('scenario_quadrant_id', $framework->quadrants()->select('scenario_quadrants.id'))
            ->whereNotNull('type')
            ->with('quadrant')
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => ['id' => $item->uuid, 'type' => $item->type, 'title' => $item->title, 'position' => $item->quadrant?->position])
            ->all();
    }

    public static function robustness(int $applies): string
    {
        return match (true) {
            $applies >= self::ROBUST_FROM => 'robust',
            $applies === 1 => 'scenario_specific',
            default => 'conditional',
        };
    }

    public function hasApproved(ScenarioPlanningProject $project): bool
    {
        return $project->strategicResponses()->where('status', 'approved')->exists();
    }

    private function attributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'rationale' => $validated['rationale'] ?? null,
            'initiatives' => $validated['initiatives'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'owner_id' => empty($validated['owner']) ? null : User::where('uuid', $validated['owner'])->value('id'),
            'depends_on' => $validated['depends_on'] ?? null,
        ];
    }

    /**
     * @return array{0: Collection<string, int>, 1: Collection<int, int>} quadrant ids by position, implication ids
     */
    private function resolve(Scenario $framework, array $validated): array
    {
        $quadrants = $framework->quadrants()->pluck('scenario_quadrants.id', 'position');

        $uuids = array_unique($validated['implications'] ?? []);
        $implications = ScenarioImplication::whereIn('scenario_quadrant_id', $quadrants->values())->whereIn('uuid', $uuids)->pluck('id');
        if ($implications->count() !== count($uuids)) {
            throw ValidationException::withMessages(['implications' => 'Pilih peluang atau tantangan dari latihan ini.']);
        }

        return [$quadrants, $implications];
    }

    /**
     * @param  Collection<string, int>  $quadrants
     * @param  Collection<int, int>  $implications
     */
    private function syncRelations(StrategicResponse $response, array $validated, Collection $quadrants, Collection $implications): void
    {
        $response->implications()->sync($implications->all());
        $response->fits()->delete();
        foreach ($validated['fit'] ?? [] as $position => $fit) {
            $response->fits()->create(['scenario_quadrant_id' => $quadrants[$position], 'fit' => $fit]);
        }
    }

    private function requireApproved(ScenarioPlanningProject $project): Scenario
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['responses' => 'Setujui kerangka skenario (G5) dulu sebelum menyusun respons strategis.']);
        }

        return $framework;
    }
}
