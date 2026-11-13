<?php

namespace App\Services;

use App\Models\ProjectDecision;
use App\Models\ProjectParticipant;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Support\RowLock;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Scenario planning exercises (roadmap R0.1): the key focal issue, gate G1
 * (focal issue confirmed) and the status of the nine method steps.
 */
class ScenarioPlanningProjectService
{
    /**
     * The nine steps of the method (slide 13). `module` is the existing page that covers the step
     * today, tenant-wide; it becomes exercise-scoped in later roadmap items.
     */
    public const STEPS = [
        1 => ['key' => 'focal_issue', 'title' => 'Key Focal Issue', 'module' => null],
        2 => ['key' => 'driving_forces', 'title' => 'Driving Forces', 'module' => 'driving-force.'],
        3 => ['key' => 'critical_uncertainties', 'title' => 'Critical Uncertainties', 'module' => 'visualization.prioritizing.'],
        4 => ['key' => 'scenario_framework', 'title' => 'Scenario Framework', 'module' => 'scenarios.index'],
        5 => ['key' => 'develop_scenarios', 'title' => 'Develop Scenarios', 'module' => null],
        6 => ['key' => 'narratives', 'title' => 'Narratives Imagination', 'module' => null],
        7 => ['key' => 'challenges_opportunities', 'title' => 'Challenges & Opportunities', 'module' => null],
        8 => ['key' => 'strategic_response', 'title' => 'Strategic Response', 'module' => null],
        9 => ['key' => 'early_warning', 'title' => 'Early Warning Signal', 'module' => null],
    ];

    /** What to do next, per step, in plain words for the overview (roadmap R1.11). */
    private const NEXT_ACTIONS = [
        1 => 'Lengkapi lalu konfirmasi pertanyaan fokus, tujuan dan tahun target.',
        2 => 'Pilih driving force yang relevan lalu sepakati daftarnya.',
        3 => 'Beri skor dampak dan ketidakpastian, lalu setujui dua critical uncertainty.',
        4 => 'Buat kerangka 2×2, beri nama tiap kuadran, lalu setujui kerangkanya.',
        5 => 'Tulis minimal dua kondisi untuk tiap kuadran.',
        6 => 'Buat narasi tiap skenario lalu tinjau dan setujui keempatnya.',
        7 => 'Catat minimal satu peluang dan satu tantangan di tiap kuadran.',
        8 => 'Susun respons strategis, nilai kecocokannya per kuadran, lalu setujui.',
        9 => 'Tentukan indikator peringatan dini dengan penanggung jawab; bersama-sama arahnya harus membedakan kedua sumbu.',
    ];

    /** Logged decisions that are not gates (roadmap R4.1). */
    private const OTHER_DECISIONS = ['PF' => 'Masa depan pilihan', ProjectDecision::STATUS => 'Status latihan'];

    /** Gates in the order they are passed (target workflow §4). */
    private const GATES = [
        'G1' => 'Focal issue dikonfirmasi',
        'G2' => 'Daftar driving force disepakati',
        'G3' => 'Skor disepakati',
        'G4' => 'Dua critical uncertainty disetujui',
        'G5' => 'Kerangka skenario disetujui',
        'G6' => 'Keempat narasi disetujui',
        'G7' => 'Respons strategis disetujui',
    ];

    /**
     * The first step that is open and not done, with its link and what to do, or null when every
     * open step is done.
     *
     * @param  array<int, array{number: int, title: string, status: string, href: ?string}>  $steps
     * @return array{number: int, title: string, text: string, href: string}|null
     */
    public function nextAction(ScenarioPlanningProject $project, array $steps): ?array
    {
        $step = collect($steps)->first(fn ($step) => in_array($step['status'], ['in_progress', 'available'], true));
        if (! $step) {
            return null;
        }

        return [
            'number' => $step['number'],
            'title' => $step['title'],
            'text' => self::NEXT_ACTIONS[$step['number']],
            'href' => $step['href'] ?? route('scenario-planning.show', $project),
        ];
    }

    /**
     * The highest gate the exercise has passed, with who passed it and when, or null before G1.
     *
     * @return array{gate: string, label: string, by: ?string, at: ?string}|null
     */
    public function lastGate(ScenarioPlanningProject $project): ?array
    {
        $passed = array_reverse($this->passedGates($project));
        if ($passed === []) {
            return null;
        }
        $gate = array_key_first($passed);
        [$by, $at] = $passed[$gate];

        return ['gate' => $gate, 'label' => self::GATES[$gate], 'by' => $by, 'at' => $at?->toISOString()];
    }

    /**
     * Every gate G1–G7 in order, passed or not, with who passed it and when (roadmap R3.5).
     *
     * @return array<int, array{gate: string, label: string, passed: bool, by: ?string, at: ?string}>
     */
    public function gates(ScenarioPlanningProject $project): array
    {
        $passed = $this->passedGates($project);

        return collect(self::GATES)->map(function (string $label, string $gate) use ($passed) {
            [$by, $at] = array_key_exists($gate, $passed) ? $passed[$gate] : [null, null];

            return [
                'gate' => $gate,
                'label' => $label,
                'passed' => array_key_exists($gate, $passed),
                'by' => $by,
                'at' => $at?->toISOString(),
            ];
        })->values()->all();
    }

    /**
     * Passed gates in order G1..G7, each as [who, when].
     *
     * @return array<string, array{0: ?string, 1: ?CarbonInterface}>
     */
    private function passedGates(ScenarioPlanningProject $project): array
    {
        $framework = $project->framework()->with('approver:id,name')->first();
        $response = $project->strategicResponses()->where('status', 'approved')->with('approver:id,name')->latest('approved_at')->first();
        $quadrants = $framework ? $framework->quadrants()->with('reviewer:id,name')->get() : collect();
        $uncertainty = $project->uncertaintiesApproved()
            ? $project->criticalUncertainties()->where('status', 'approved')->with('approver:id,name')->latest('approved_at')->first()
            : null;
        $lastReview = $quadrants->sortByDesc('reviewed_at')->first();

        return array_filter([
            'G1' => $project->confirmed_at ? [$project->confirmer?->name, $project->confirmed_at] : null,
            'G2' => $project->forces_confirmed_at ? [$project->forcesConfirmer?->name, $project->forces_confirmed_at] : null,
            'G3' => $project->scores_confirmed_at ? [$project->scoresConfirmer?->name, $project->scores_confirmed_at] : null,
            'G4' => $uncertainty ? [$uncertainty->approver?->name, $uncertainty->approved_at] : null,
            'G5' => $framework?->isFrameworkApproved() ? [$framework->approver?->name, $framework->approved_at] : null,
            'G6' => $quadrants->count() === 4 && $quadrants->every(fn ($quadrant) => $quadrant->review_status === 'approved') ? [$lastReview?->reviewer?->name, $lastReview?->reviewed_at] : null,
            'G7' => $response ? [$response->approver?->name, $response->approved_at] : null,
        ]);
    }

    public function create(array $validated, User $user): ScenarioPlanningProject
    {
        return ScenarioPlanningProject::create($this->attributes($validated) + [
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Save the changes. Returns true when the focal issue itself changed after it was confirmed:
     * the confirmation is then withdrawn and the exercise goes back to draft until confirmed again.
     */
    public function update(ScenarioPlanningProject $project, array $validated, User $user): bool
    {
        // The status is not editable here: changeStatus() and G1 own it (PI-01).
        $project->fill($this->attributes($validated) + ['updated_by' => $user->id]);

        $reconfirm = $project->isConfirmed() && $project->isDirty(ScenarioPlanningProject::FOCAL_FIELDS);
        // An AI critique (roadmap R3.1) is about the old question.
        if ($project->isDirty('focal_question')) {
            $project->focal_suggestion = null;
        }
        if ($reconfirm) {
            // The force list was agreed for the old focal issue, so gate G2 goes too.
            $project->confirmed_at = null;
            $project->confirmed_by = null;
            $project->forces_confirmed_at = null;
            $project->forces_confirmed_by = null;
            $project->scores_confirmed_at = null;
            $project->scores_confirmed_by = null;
            $project->status = 'draft';
        }

        $project->save();
        if ($reconfirm) {
            $project->withdrawUncertaintyApprovals();
            app(StalenessService::class)->markFramework($project, 'Focal issue, tujuan atau tahun target berubah');
        }

        return $reconfirm;
    }

    /** Gate G1. Confirming an already confirmed exercise changes nothing. */
    public function confirm(ScenarioPlanningProject $project, User $user): void
    {
        RowLock::run($project, function () use ($project, $user) {
            if ($project->isConfirmed()) {
                return;
            }

            $project->update([
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
                'status' => $project->status === 'draft' ? 'active' : $project->status,
                'updated_by' => $user->id,
            ]);
        });
    }

    /**
     * PRD-06: a confirmed exercise is active, in monitoring (only once step 9 is done) or archived.
     * Draft is set by withdrawing gate G1, never by hand.
     *
     * @throws ValidationException
     */
    public function changeStatus(ScenarioPlanningProject $project, string $status, User $user): void
    {
        if (! in_array($status, ['active', 'monitoring', 'archived'], true) || ! $project->isConfirmed()) {
            throw ValidationException::withMessages(['status' => 'Status ini tidak bisa dipilih untuk latihan ini.']);
        }
        if ($status === 'monitoring' && collect($this->steps($project))->firstWhere('number', 9)['status'] !== 'done') {
            throw ValidationException::withMessages(['status' => 'Selesaikan langkah 9 (indikator peringatan dini) sebelum masuk tahap pemantauan.']);
        }

        $project->update(['status' => $status, 'updated_by' => $user->id]);
    }

    /** Impact and uncertainty from this score up count as high on the matrix. */
    public const HIGH_SCORE = 6;

    /** Steps with their own exercise screen, by step number. */
    private const SCOPED_ROUTES = [
        2 => 'scenario-planning.forces.index',
        3 => 'scenario-planning.assessment.index',
        4 => 'scenario-planning.framework.index',
        5 => 'scenario-planning.conditions.index',
        6 => 'scenario-planning.narratives.index',
        7 => 'scenario-planning.implications.index',
        8 => 'scenario-planning.responses.index',
        9 => 'scenario-planning.indicators.index',
    ];

    /**
     * Status per step. Gate G1 (focal issue confirmed) opens step 2, G2 (force list agreed) opens
     * step 3, and step 3 is done at G4 (two critical uncertainties approved, after G3 scores agreed),
     * which opens step 4. Step 4 is done at G5 (framework approved), which opens steps 5–9; step 5 is
     * done when every quadrant has ScenarioConditionService::MIN_PER_QUADRANT conditions, step 6 when all
     * four narratives are approved (G6), step 7 when every quadrant has an opportunity and a challenge, step 8 with
     * an approved response (G7), step 9 with an accepted indicator rated in all four quadrants. `href` is the exercise's own screen when the step has one, otherwise the
     * existing tenant-wide page (`scoped` false).
     *
     * @return array<int, array{number: int, key: string, title: string, status: string, href: ?string, scoped: bool, ahead: bool}>
     */
    public function steps(ScenarioPlanningProject $project): array
    {
        $focal = $project->isConfirmed();
        $forces = $project->forcesConfirmed();
        $axes = $project->uncertaintiesApproved();
        $framework = $project->framework()->first();
        $frameworkApproved = $framework?->isFrameworkApproved() ?? false;
        $conditionsDone = $frameworkApproved && app(ScenarioConditionService::class)->complete($framework);
        $conditionsStarted = $frameworkApproved && ScenarioCondition::whereIn('scenario_quadrant_id', $framework->quadrants()->select('id'))->exists();
        $narratives = $frameworkApproved ? $framework->quadrants()->get(['narrative', 'review_status', 'provider', 'edited_at']) : collect();
        $narrativesDone = $narratives->count() === 4 && $narratives->every(fn ($quadrant) => $quadrant->review_status === 'approved');
        // PRD-07: a template skeleton nobody edited is not a started narrative.
        $narrativesStarted = $narratives->contains(fn ($quadrant) => filled($quadrant->narrative)
            && ($quadrant->provider !== ScenarioNarrativeService::PROVIDER_TEMPLATE || $quadrant->isEdited()));
        $implicationsDone = $frameworkApproved && app(ScenarioImplicationService::class)->complete($framework);
        $implicationsStarted = $frameworkApproved && ScenarioImplication::whereIn('scenario_quadrant_id', $framework->quadrants()->select('id'))->exists();
        $responsesDone = $frameworkApproved && app(StrategicResponseService::class)->hasApproved($project);
        $responsesStarted = $frameworkApproved && $project->strategicResponses()->exists();
        $indicatorsDone = $frameworkApproved && app(EarlyWarningIndicatorService::class)->complete($project);
        $indicatorsStarted = $frameworkApproved && $project->indicators()->exists();
        $included = $project->projectForces()->where('inclusion_status', 'included');
        $hasForces = (clone $included)->exists();
        $started = (clone $included)->whereHas('consensus')->exists() || $project->criticalUncertainties()->exists();

        $steps = collect(self::STEPS)->map(function (array $step, int $number) use ($project, $focal, $forces, $axes, $hasForces, $started, $framework, $frameworkApproved, $conditionsDone, $conditionsStarted, $narrativesDone, $narrativesStarted, $implicationsDone, $implicationsStarted, $responsesDone, $responsesStarted, $indicatorsDone, $indicatorsStarted) {
            $status = match (true) {
                $number === 1 => $focal ? 'done' : 'in_progress',
                $number === 2 => ! $focal ? 'locked' : ($forces ? 'done' : ($hasForces ? 'in_progress' : 'available')),
                $number === 3 => ! $forces ? 'locked' : ($axes ? 'done' : ($started ? 'in_progress' : 'available')),
                $number === 4 => ! $axes ? 'locked' : ($frameworkApproved ? 'done' : ($framework ? 'in_progress' : 'available')),
                $number === 5 => ! $frameworkApproved ? 'locked' : ($conditionsDone ? 'done' : ($conditionsStarted ? 'in_progress' : 'available')),
                $number === 6 => ! $frameworkApproved ? 'locked' : ($narrativesDone ? 'done' : ($narrativesStarted ? 'in_progress' : 'available')),
                $number === 7 => ! $frameworkApproved ? 'locked' : ($implicationsDone ? 'done' : ($implicationsStarted ? 'in_progress' : 'available')),
                $number === 8 => ! $frameworkApproved ? 'locked' : ($responsesDone ? 'done' : ($responsesStarted ? 'in_progress' : 'available')),
                $number === 9 => ! $frameworkApproved ? 'locked' : ($indicatorsDone ? 'done' : ($indicatorsStarted ? 'in_progress' : 'available')),
                default => 'locked',
            };
            $scoped = isset(self::SCOPED_ROUTES[$number]);

            return [
                'number' => $number,
                'key' => $step['key'],
                'title' => $step['title'],
                'status' => $status,
                'href' => $scoped ? route(self::SCOPED_ROUTES[$number], $project) : ($step['module'] ? route($step['module']) : null),
                'scoped' => $scoped,
            ];
        })->values();

        // PRD-07: steps 5–9 open together on G5, so a later step can be done before an earlier one.
        // It stays done; `ahead` lets the screen say it was finished ahead of the steps before it.
        return $steps->map(fn (array $step) => $step + [
            'ahead' => $step['status'] === 'done' && $steps->contains(fn (array $earlier) => $earlier['number'] < $step['number'] && $earlier['status'] !== 'done'),
        ])->all();
    }

    /**
     * Members of the tenant in context (an operator's picked workspace included) who can own an
     * exercise or a strategic response.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function owners(User $user): array
    {
        $tenantId = app(TenantContext::class)->getTenantId() ?? $user->tenant_id;

        return User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn ($member) => ['value' => $member->uuid, 'label' => $member->name])
            ->all();
    }

    /** The latest gate decisions of an exercise, newest first (roadmap R2.2). */
    public function decisions(ScenarioPlanningProject $project, int $limit = 50): array
    {
        return $project->decisions()
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ProjectDecision $row) => [
                'gate' => $row->gate,
                'label' => self::GATES[$row->gate] ?? self::OTHER_DECISIONS[$row->gate] ?? $row->gate,
                'action' => $row->action,
                'subject' => $row->subject,
                'rationale' => $row->rationale,
                'actor' => $row->actor?->name,
                'at' => $row->created_at->toISOString(),
            ])
            ->all();
    }

    /** Participants of an exercise, coordinators first, then by name (roadmap R2.2). */
    public function participants(ScenarioPlanningProject $project): array
    {
        $order = array_flip(ProjectParticipant::ROLES);

        return $project->participants()
            ->with('user:id,uuid,name')
            ->get()
            ->sortBy(fn (ProjectParticipant $row) => sprintf('%d-%s', $order[$row->role] ?? 9, strtolower((string) $row->user?->name)))
            ->map(fn (ProjectParticipant $row) => [
                'id' => $row->uuid,
                'user' => ['id' => $row->user?->uuid, 'name' => $row->user?->name],
                'role' => $row->role,
                'division' => $row->division,
            ])
            ->values()
            ->all();
    }

    /**
     * The exercises the firm reports can be narrowed to (Key Focal Issues): those with at least one
     * included force, newest first. Light on purpose: one query, no presenter.
     *
     * @return array<int, array{id: string, name: string, target_year: int|null, scores_confirmed: bool}>
     */
    public function reportScopes(): array
    {
        return ScenarioPlanningProject::whereHas('projectForces', fn ($q) => $q->where('inclusion_status', 'included'))
            ->orderByDesc('updated_at')
            ->get(['id', 'uuid', 'name', 'target_year', 'scores_confirmed_at'])
            ->map(fn (ScenarioPlanningProject $project) => [
                'id' => $project->uuid,
                'name' => $project->name,
                'target_year' => $project->target_year,
                'scores_confirmed' => $project->scores_confirmed_at !== null,
            ])
            ->values()
            ->all();
    }

    public function present(ScenarioPlanningProject $project): array
    {
        $project->loadMissing(['owner:id,uuid,name', 'confirmer:id,name', 'forcesConfirmer:id,name', 'scoresConfirmer:id,name', 'creator:id,name']);

        return [
            'id' => $project->uuid,
            'name' => $project->name,
            'focal_question' => $project->focal_question,
            'purpose' => $project->purpose,
            'target_year' => $project->target_year,
            'geography' => $project->geography,
            'industry' => $project->industry,
            'owner_unit' => $project->owner_unit,
            'owner' => $project->owner ? ['id' => $project->owner->uuid, 'name' => $project->owner->name] : null,
            'background' => $project->background,
            'objectives' => $project->objectives,
            'status' => $project->status,
            'confirmed_at' => $project->confirmed_at?->toISOString(),
            'confirmed_by' => $project->confirmer?->name,
            'forces_confirmed_at' => $project->forces_confirmed_at?->toISOString(),
            'forces_confirmed_by' => $project->forcesConfirmer?->name,
            'scores_confirmed_at' => $project->scores_confirmed_at?->toISOString(),
            'scores_confirmed_by' => $project->scoresConfirmer?->name,
            'stale' => app(StalenessService::class)->present($project),
            // AI narratives of the framework nobody has reviewed yet (roadmap R1.6).
            'unreviewed_narratives' => $project->framework()->first()?->quadrants()->where('is_ai_generated', true)->where('review_status', 'draft')->count() ?? 0,
            'created_by' => $project->creator?->name,
            'updated_at' => $project->updated_at?->toISOString(),
        ];
    }

    private function attributes(array $validated): array
    {
        $ownerId = empty($validated['owner']) ? null : User::where('uuid', $validated['owner'])->value('id');

        return [
            'name' => $validated['name'],
            'focal_question' => $validated['focal_question'],
            'purpose' => $validated['purpose'],
            'target_year' => (int) $validated['target_year'],
            'geography' => $validated['geography'] ?? null,
            'industry' => $validated['industry'] ?? null,
            'owner_unit' => $validated['owner_unit'] ?? null,
            'owner_user_id' => $ownerId,
            'background' => $validated['background'] ?? null,
            'objectives' => $validated['objectives'] ?? null,
        ];
    }
}
