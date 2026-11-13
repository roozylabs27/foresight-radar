<?php

namespace App\Services;

use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\IndicatorThresholdEvent;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Models\User;
use App\Support\Provenance;
use App\Support\ThresholdEvaluator;
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
    /** Audit U5: observations older than this do not count for relevance. */
    public const RELEVANCE_WINDOW_MONTHS = 12;

    public function create(ScenarioPlanningProject $project, array $validated, User $user): EarlyWarningIndicator
    {
        $framework = $this->requireApproved($project);
        $links = $this->resolveLinks($project, $validated);

        return DB::transaction(function () use ($project, $framework, $validated, $links, $user) {
            $indicator = EarlyWarningIndicator::create($this->attributes($validated, $links) + [
                'project_id' => $project->id,
                'status' => $validated['status'] ?? 'active',
                'origin' => Provenance::HUMAN,
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
            // Audit U6: an edit by the team settles the indicator's review flag.
            app(StalenessService::class)->clear($indicator);
        });
    }

    public function delete(EarlyWarningIndicator $indicator): void
    {
        $this->requireApproved($indicator->project);

        $indicator->delete();
    }

    /** Observations shown per indicator in the register (roadmap R2.3). */
    public const RECENT_OBSERVATIONS = 5;

    /** Accepted signals an observation may cite, newest first. */
    public function signalOptions(): array
    {
        return Signal::where('review_status', 'ACCEPTED')
            ->latest('id')
            ->limit(100)
            ->get(['uuid', 'title'])
            ->map(fn (Signal $signal) => ['id' => $signal->uuid, 'title' => $signal->title])
            ->all();
    }

    /**
     * Roadmap R4.2 (deck slide 26, sensing): the latest accepted weak signals (horizon `might`) and
     * wild cards, which may point at a scenario before any indicator moves.
     */
    public function sensing(int $limit = 10): array
    {
        return Signal::where('review_status', 'ACCEPTED')
            ->where(fn ($q) => $q->where('horizon', 'might')->orWhere('wild_card', true))
            ->latest('id')
            ->limit($limit)
            ->get(['uuid', 'title', 'summary', 'horizon', 'wild_card', 'classified_by', 'created_at'])
            ->map(fn (Signal $signal) => [
                'id' => $signal->uuid,
                'title' => $signal->title,
                'summary' => $signal->summary,
                'horizon' => $signal->horizon,
                'wild_card' => $signal->wild_card,
                // Proposed by the AI at extraction and not yet saved by a reviewer.
                'proposed' => $signal->classified_by === null,
                'created_at' => $signal->created_at?->toISOString(),
            ])
            ->all();
    }

    /** The register, alphabetical, with directions keyed by quadrant position. */
    public function list(ScenarioPlanningProject $project): array
    {
        return EarlyWarningIndicator::where('project_id', $project->id)
            ->with(['directions.quadrant', 'uncertainty:id,uuid,name', 'projectForce.drivingForce', 'owner:id,uuid,name', 'observations.signal:id,uuid,title', 'observations.recorder:id,name', 'thresholdEvents'])
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
                    'external_source' => $indicator->external_source,
                    'external_series' => $indicator->external_series,
                    'external_area' => $indicator->external_area,
                    ...app(ExternalIndicatorService::class)->fetchState($indicator),
                    'last_fetched_at' => $indicator->last_fetched_at?->toISOString(),
                    'threshold_rule' => $indicator->threshold_rule,
                    'threshold_operator' => $indicator->threshold_operator,
                    'threshold_value' => $indicator->threshold_value,
                    'threshold_numeric' => ThresholdEvaluator::isNumeric($indicator->threshold_operator, $indicator->threshold_value),
                    'threshold_text' => ThresholdEvaluator::describe($indicator->threshold_operator, $indicator->threshold_value),
                    'threshold_events' => $indicator->thresholdEvents->map(fn (IndicatorThresholdEvent $event) => [
                        'id' => $event->uuid,
                        'kind' => $event->kind,
                        'rule' => ThresholdEvaluator::describe($event->operator, $event->threshold_value),
                        'observed_value' => $event->observed_value,
                        'observed_at' => $event->observed_at->toDateString(),
                        'closed' => $event->closed_at !== null,
                    ])->values()->all(),
                    'status' => $indicator->status,
                    'origin' => $indicator->origin,
                    'review_status' => $indicator->review_status,
                    'stale_reason' => $indicator->stale_since ? $indicator->stale_reason : null,
                    'directions' => (object) $directions->all(),
                    'discriminates' => self::discriminates($directions->pluck('direction')),
                    'observation_count' => $indicator->observations->count(),
                    'observations' => $indicator->observations->take(self::RECENT_OBSERVATIONS)->map(fn (IndicatorObservation $row) => [
                        'id' => $row->uuid,
                        'observed_at' => $row->observed_at->toDateString(),
                        'value_numeric' => $row->value_numeric,
                        'value_text' => $row->value_text,
                        'direction' => $row->direction,
                        'crossed_threshold' => $row->crossed_threshold,
                        'signal' => $row->signal ? ['id' => $row->signal->uuid, 'title' => $row->signal->title] : null,
                        'note' => $row->note,
                        'recorded_by' => $row->recorder?->name,
                        'recorded_by_id' => $row->recorded_by,
                        'origin' => $row->origin,
                        'review_status' => $row->review_status,
                        'source' => $row->source,
                        'source_ref' => $row->source_ref,
                        'source_period' => $row->source_period,
                        'fetched_at' => $row->fetched_at?->toISOString(),
                    ])->values()->all(),
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

    /**
     * Audit U5 (AUD-06): which scenario the recent evidence points to, with the reasons. Not a
     * probability: a transparent count, documented in docs/methodology/scenario-relevance.md.
     *
     * - Each active indicator counts once, by its latest accepted observation with a direction inside
     *   the last RELEVANCE_WINDOW_MONTHS months (older ones are reported as `outdated`).
     * - Per quadrant with a measurable expected direction: same direction supports, rising against
     *   falling contradicts, "stable" against a moving expectation (or the reverse) is unclear.
     *   Qualitative or unset expectations do not count.
     * - `reading`: strengthening when supporting > contradicting, weakening when fewer, else unclear.
     * - `leading`: the quadrant with the highest net (supporting − contradicting) when positive and unique.
     *
     * @return array{counted: int, outdated: int, window_months: int, since: string, leading: ?string, quadrants: array<int, array<string, mixed>>}
     */
    public function relevance(ScenarioPlanningProject $project, Scenario $framework): array
    {
        $since = now()->subMonths(self::RELEVANCE_WINDOW_MONTHS)->startOfDay();
        $quadrants = $framework->quadrants()->get()->keyBy('position');
        $rows = collect(Scenario::POSITIONS)->mapWithKeys(fn ($position) => [$position => ['supporting' => 0, 'contradicting' => 0, 'unclear' => 0, 'indicators' => []]])->all();
        $counted = 0;
        $outdated = 0;

        // PI-09: only accepted indicators that tell the futures apart count; one rising in every
        // quadrant (or still a proposal) would raise every reading at once.
        $indicators = EarlyWarningIndicator::where('project_id', $project->id)
            ->where('status', 'active')
            ->where('review_status', 'accepted')
            ->with(['directions.quadrant', 'observations' => fn ($q) => $q->whereNotNull('direction')->where('review_status', 'accepted')])
            ->orderBy('name')
            ->get()
            ->filter(fn ($indicator) => self::axesTold($this->expectedByPosition($indicator)) !== []);
        foreach ($indicators as $indicator) {
            // observations() is ordered newest first.
            $latest = $indicator->observations->first();
            if ($latest === null) {
                continue;
            }
            if ($latest->observed_at->lt($since)) {
                $outdated++;

                continue;
            }
            $counted++;

            foreach ($indicator->directions as $row) {
                $expected = $row->expected_direction;
                if (! in_array($expected, IndicatorObservation::DIRECTIONS, true) || ! $row->quadrant) {
                    continue;
                }
                $verdict = self::verdict($expected, $latest->direction);
                $position = $row->quadrant->position;
                $rows[$position][$verdict]++;
                $rows[$position]['indicators'][] = [
                    'name' => $indicator->name,
                    'observed' => $latest->direction,
                    'expected' => $expected,
                    'verdict' => $verdict,
                    'observed_at' => $latest->observed_at->toDateString(),
                    'crossed_threshold' => $latest->crossed_threshold,
                ];
            }
        }

        $net = collect($rows)->map(fn ($row) => $row['supporting'] - $row['contradicting']);
        $best = $net->max();
        $leading = $counted > 0 && $best > 0 && $net->filter(fn ($value) => $value === $best)->count() === 1 ? $net->search($best) : null;

        return [
            'counted' => $counted,
            'outdated' => $outdated,
            'window_months' => self::RELEVANCE_WINDOW_MONTHS,
            'since' => $since->toDateString(),
            'leading' => $leading,
            'quadrants' => collect(Scenario::POSITIONS)->map(fn ($position) => [
                'position' => $position,
                'title' => $quadrants[$position]->title ?? null,
                'reading' => match ($rows[$position]['supporting'] <=> $rows[$position]['contradicting']) {
                    1 => 'strengthening',
                    -1 => 'weakening',
                    default => 'unclear',
                },
            ] + $rows[$position])->all(),
        ];
    }

    /** One indicator's latest direction against one quadrant's expectation. */
    public static function verdict(string $expected, string $observed): string
    {
        if ($expected === $observed) {
            return 'supporting';
        }

        return $expected === 'stable' || $observed === 'stable' ? 'unclear' : 'contradicting';
    }

    /** At least two different expected directions across the quadrants. */
    public static function discriminates(Collection $directions): bool
    {
        return $directions->unique()->count() >= 2;
    }

    /**
     * PI-09: the quadrant pairs that differ only on one axis (position = axis A pole, then axis B pole).
     * An indicator tells an axis apart when it expects different directions in such a pair.
     */
    public const AXIS_PAIRS = ['A' => [['HH', 'LH'], ['HL', 'LL']], 'B' => [['HH', 'HL'], ['LH', 'LL']]];

    /**
     * The axes ('A', 'B') an indicator's expected directions tell apart.
     *
     * @param  array<string, string>  $expectedByPosition
     * @return array<int, string>
     */
    public static function axesTold(array $expectedByPosition): array
    {
        $directional = array_filter($expectedByPosition, fn ($direction) => in_array($direction, IndicatorObservation::DIRECTIONS, true));

        return collect(self::AXIS_PAIRS)
            ->filter(fn ($pairs) => collect($pairs)->contains(fn ($pair) => isset($directional[$pair[0]], $directional[$pair[1]])
                && $directional[$pair[0]] !== $directional[$pair[1]]))
            ->keys()->values()->all();
    }

    /**
     * PI-09: step 9 is done when accepted, active indicators with an owner and a direction in every
     * quadrant together tell both axes apart, so monitoring can show which future is unfolding.
     */
    public function complete(ScenarioPlanningProject $project): bool
    {
        return $this->stepNineGaps($project) === [];
    }

    /**
     * What still keeps step 9 open, in plain words for the indicators page (empty when done). It is the
     * same rule as complete(), so the page and the stepper never disagree.
     *
     * @return array<int, string>
     */
    public function stepNineGaps(ScenarioPlanningProject $project): array
    {
        $complete = EarlyWarningIndicator::where('project_id', $project->id)
            ->where('review_status', 'accepted')
            ->where('status', 'active')
            ->with('directions.quadrant')
            ->orderBy('name')
            ->get()
            ->filter(fn ($indicator) => $indicator->directions->count() >= count(Scenario::POSITIONS));
        if ($complete->isEmpty()) {
            return ['Belum ada indikator yang diterima dengan arah di keempat kuadran.'];
        }

        $told = $complete->whereNotNull('owner_id')
            ->flatMap(fn ($indicator) => self::axesTold($this->expectedByPosition($indicator)))
            ->unique();
        if ($told->contains('A') && $told->contains('B')) {
            return [];
        }

        // Not done: name the unowned indicators (an owner may be all that is missing), then the axes.
        $gaps = [];
        $unowned = $complete->whereNull('owner_id');
        if ($unowned->isNotEmpty()) {
            $gaps[] = 'Belum ada penanggung jawab untuk: '.$unowned->pluck('name')->implode(', ').'.';
        }
        $framework = $project->framework()->first();
        foreach (['A', 'B'] as $axis) {
            if (! $told->contains($axis)) {
                $poles = $framework ? ' ('.implode(' ↔ ', $axis === 'A'
                    ? [$framework->axis_a_low_label, $framework->axis_a_high_label]
                    : [$framework->axis_b_low_label, $framework->axis_b_high_label]).')' : '';
                $gaps[] = "Belum ada indikator dengan penanggung jawab yang membedakan sumbu {$axis}{$poles}: arahnya harus berbeda antara kedua kutub sumbu itu.";
            }
        }

        return $gaps;
    }

    /** @return array<string, string> */
    private function expectedByPosition(EarlyWarningIndicator $indicator): array
    {
        return $indicator->directions
            ->filter(fn ($row) => $row->quadrant !== null)
            ->mapWithKeys(fn ($row) => [$row->quadrant->position => (string) $row->expected_direction])
            ->all();
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
                        'origin' => Provenance::MIGRATED,
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
            'external_source' => $validated['external_source'] ?? null,
            'external_series' => $validated['external_series'] ?? null,
            'external_area' => isset($validated['external_area']) ? strtoupper($validated['external_area']) : null,
            'threshold_rule' => $validated['threshold_rule'] ?? null,
            'threshold_operator' => $validated['threshold_operator'] ?? null,
            'threshold_value' => isset($validated['threshold_operator']) ? ($validated['threshold_value'] ?? null) : null,
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
