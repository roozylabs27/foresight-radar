<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\GenerateScenarioNarrative;
use App\Models\AiGenerationRun;
use App\Models\Scenario;
use App\Models\ScenarioCitation;
use App\Models\ScenarioNarrativeRevision;
use App\Models\ScenarioQuadrant;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\Prompts\ScenarioNarrativePrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Generates the four quadrants of a scenario. With an LLM it writes cited
 * narratives from the accepted signals; without one (or when the call fails)
 * it lays out a template skeleton for analysts to fill in, labelled as such.
 * A failed regeneration never replaces an AI narrative with the template.
 *
 * Citation rules: a narrative may only cite the S-references it was given;
 * other references are removed, and if any quadrant is left without a valid
 * citation the whole result is rejected and nothing is saved.
 */
class ScenarioNarrativeService
{
    public const PROVIDER_TEMPLATE = 'template';

    public function __construct(
        protected StructuredLlmClient $llm,
        protected ScenarioService $scenarios,
    ) {}

    /**
     * Check that the scenario can be generated, then queue the generation.
     * The LLM call runs in the GenerateScenarioNarrative job.
     *
     * @throws ConflictHttpException when a generation is running or edited quadrants would be overwritten
     * @throws ValidationException when there is no evidence for the LLM
     */
    public function queue(Scenario $scenario, bool $overwriteEdited, ?int $userId): Scenario
    {
        return DB::transaction(function () use ($scenario, $overwriteEdited, $userId) {
            $locked = Scenario::where('id', $scenario->id)->lockForUpdate()->firstOrFail();

            // A job older than the queue's retry window has died; the analyst may start again.
            $stale = now()->subSeconds((int) config('queue.connections.database.retry_after', 360));
            if ($locked->generation_status?->isPending() && $locked->updated_at?->greaterThan($stale)) {
                throw new ConflictHttpException('Narasi skenario ini sedang dibuat. Tunggu sampai selesai.');
            }

            $this->assertGeneratable($locked, $overwriteEdited, $this->clientFor($userId));

            $locked->update([
                'generation_status' => LlmJobStatus::QUEUED,
                'generation_error' => null,
                'generation_notice' => null,
            ]);

            GenerateScenarioNarrative::dispatch($locked, $overwriteEdited, $userId);

            return $locked->refresh();
        });
    }

    /**
     * @return array{scenario: Scenario, provider: string, notice: ?string}
     *
     * @throws ConflictHttpException when edited quadrants would be overwritten
     * @throws ValidationException when there is no evidence for the LLM, its citations are invalid,
     *                             or it fails while an AI narrative already exists
     */
    public function generate(Scenario $scenario, bool $overwriteEdited, ?int $userId): array
    {
        $llm = $this->clientFor($userId);
        $evidence = $this->assertGeneratable($scenario, $overwriteEdited, $llm);

        $notice = null;
        $quadrants = null;
        $model = null;
        $run = null;
        // Recorded on every AI call (roadmap R1.12); the template output links no run.
        $refs = ['scenario' => $scenario->uuid, 'signals' => array_map(fn ($item) => $item['signal']->id, $evidence)];
        $record = fn (string $status, string $error, ?LlmResponse $response = null) => app(AiGenerationRecorder::class)
            ->unsuccessful($status, 'scenario_narrative', $llm, ScenarioNarrativePrompt::VERSION, $refs, $error, $userId, $scenario->project_id, $response);

        if ($llm->isAvailable()) {
            $response = null;
            try {
                $response = $llm->generateJson(ScenarioNarrativePrompt::request(
                    $scenario,
                    $evidence,
                    (string) config('services.anthropic.narrative_effort', 'medium'),
                ));
                try {
                    $quadrants = $this->validated($response->data, array_column($evidence, 'ref'));
                } catch (ValidationException $e) {
                    $record(AiGenerationRun::REJECTED, (string) collect($e->errors())->flatten()->first(), $response);
                    throw $e;
                }
                $model = $response->model;
                $run = app(AiGenerationRecorder::class)->completed('scenario_narrative', $response, ScenarioNarrativePrompt::VERSION, $refs, $userId, $scenario->project_id);
            } catch (LlmException $e) {
                $record($response ? AiGenerationRun::REJECTED : AiGenerationRun::FAILED, $e->reason, $response);
                // A template would replace a narrative the AI already wrote, so a regeneration keeps it instead.
                if ($scenario->quadrants->contains('is_ai_generated', true)) {
                    Log::warning('Scenario regeneration failed; previous narrative kept', ['reason' => $e->reason]);
                    throw ValidationException::withMessages([
                        'llm' => $e->userMessage().' Narasi sebelumnya tidak diubah; coba lagi nanti.',
                    ]);
                }
                Log::warning('Scenario narrative fell back to the template', ['reason' => $e->reason]);
                $notice = $e->userMessage().' Ditampilkan kerangka tanpa AI.';
            }
        }

        $provider = $quadrants === null ? self::PROVIDER_TEMPLATE : $llm->provider();
        $quadrants ??= $this->template($scenario, $evidence);

        $signalIds = collect($evidence)->mapWithKeys(fn ($item) => [$item['ref'] => $item['signal']->id]);

        DB::transaction(function () use ($scenario, $quadrants, $evidence, $signalIds, $provider, $model, $notice, $userId, $run) {
            // Rows are filled in place: a framework's quadrants exist before any narrative (R0.5).
            $existing = $scenario->quadrants()->get()->keyBy('position');
            foreach ($quadrants as $quadrant) {
                $statements = $quadrant['statements'] ?? null;
                $attributes = collect($quadrant)->except(['cited_refs', 'statements'])->all() + [
                    'cited_signal_ids' => array_values(array_map(fn ($ref) => $signalIds[$ref], $quadrant['cited_refs'])),
                    'narrative_statements' => $statements === null ? null : array_map(fn ($row) => [
                        'text' => $row['text'],
                        'kind' => $row['kind'],
                        'signal_ids' => array_map(fn ($ref) => $signalIds[$ref], $row['refs']),
                    ], $statements),
                    'is_ai_generated' => $provider !== self::PROVIDER_TEMPLATE,
                    'origin' => $provider !== self::PROVIDER_TEMPLATE ? Provenance::AI : Provenance::TEMPLATE,
                    'provider' => $provider,
                    'edited_by' => null,
                    'edited_at' => null,
                    'review_status' => 'draft',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'stale_since' => null,
                    'stale_reason' => null,
                    'generation_run_id' => $run?->id,
                ];
                // An approved framework's quadrant names are a team decision (G5); the narrative leaves them.
                if ($scenario->isFrameworkApproved()) {
                    unset($attributes['title']);
                }
                $row = $existing[$quadrant['position']] ?? null;
                if ($row) {
                    ScenarioNarrativeRevision::capture($row, 'regenerate', $userId);
                    $row->update($attributes);
                } else {
                    $row = $scenario->quadrants()->create($attributes);
                }
                $this->writeCitations($row, $quadrant['cited_refs'], $statements, $signalIds->all());
            }
            $scenario->update([
                'provider' => $provider,
                'model' => $model,
                'prompt_version' => $provider !== self::PROVIDER_TEMPLATE ? ScenarioNarrativePrompt::VERSION : null,
                'input_signal_ids' => array_map(fn ($item) => $item['signal']->id, $evidence),
                'generated_at' => now(),
                'generation_status' => LlmJobStatus::COMPLETED,
                'generation_error' => null,
                'generation_notice' => $notice,
                'generation_run_id' => $run?->id,
                'updated_by' => $userId,
            ]);
        });

        return ['scenario' => $scenario->fresh('quadrants'), 'provider' => $provider, 'notice' => $notice];
    }

    /**
     * Save an analyst's edit of a quadrant. The replaced text is kept as a revision; an edited
     * narrative no longer matches its typed statements (roadmap R2.7), so they are dropped and its
     * citations stay untyped; an approved narrative goes back to draft. Quadrant names of an approved
     * framework are a team decision (G5) and stay locked.
     *
     * @param  array{title: string, narrative: string, implications?: ?string, early_indicators?: ?array}  $data
     *
     * @throws ValidationException when the name of an approved framework's quadrant would change
     */
    public function updateQuadrant(ScenarioQuadrant $quadrant, array $data, int $userId): ScenarioQuadrant
    {
        $scenario = $quadrant->scenario;
        if ($scenario->isFrameworkApproved() && $data['title'] !== $quadrant->title) {
            throw ValidationException::withMessages(['title' => 'Nama kuadran terkunci oleh persetujuan kerangka (G5). Buka kembali kerangkanya di latihan untuk mengganti nama.']);
        }

        DB::transaction(function () use ($quadrant, $data, $userId) {
            $changed = $data['title'] !== $quadrant->title || $data['narrative'] !== $quadrant->narrative
                || (array_key_exists('implications', $data) && $data['implications'] !== $quadrant->implications);
            if ($changed) {
                ScenarioNarrativeRevision::capture($quadrant, 'edit', $userId);
            }
            if ($data['narrative'] !== $quadrant->narrative && $quadrant->narrative_statements !== null) {
                $quadrant->narrative_statements = null;
                $quadrant->citations()->update(['statement_kind' => null, 'statement_index' => null]);
            }
            $quadrant->update($data + [
                'early_indicators' => $data['early_indicators'] ?? [],
                'edited_by' => $userId,
                'edited_at' => now(),
            ] + ($quadrant->review_status === 'approved' ? ['review_status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null] : []));
            app(StalenessService::class)->clearQuadrant($quadrant);
        });

        return $quadrant;
    }

    /**
     * Review state of one quadrant narrative (roadmap R1.6). `approved` is gate G6 (logged by
     * GateDecisionObserver); an unedited template skeleton is no narrative yet and cannot be approved.
     *
     * @throws ValidationException
     */
    public function reviewQuadrant(ScenarioQuadrant $quadrant, string $status, int $userId): ScenarioQuadrant
    {
        if ($status !== 'draft' && blank($quadrant->narrative)) {
            throw ValidationException::withMessages(['status' => 'Kuadran ini belum punya narasi.']);
        }
        if ($status === 'approved' && $quadrant->provider === self::PROVIDER_TEMPLATE && ! $quadrant->isEdited()) {
            throw ValidationException::withMessages(['status' => 'Narasi ini masih kerangka tanpa AI. Lengkapi narasinya dulu sebelum disetujui.']);
        }

        $quadrant->update($status === 'draft'
            ? ['review_status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null]
            : ['review_status' => $status, 'reviewed_by' => $userId, 'reviewed_at' => now()]);

        return $quadrant;
    }

    /**
     * Roadmap R2.7: one row per cited signal and statement, so citations are queryable by signal.
     *
     * @param  array<int, string>  $cited
     * @param  array<int, array{text: string, kind: string, refs: array<int, string>}>|null  $statements
     * @param  array<string, int>  $signalIds
     */
    protected function writeCitations(ScenarioQuadrant $quadrant, array $cited, ?array $statements, array $signalIds): void
    {
        $quadrant->citations()->delete();
        $rows = [];
        if ($statements !== null) {
            foreach ($statements as $index => $statement) {
                foreach ($statement['refs'] as $ref) {
                    $rows[] = ['ref' => $ref, 'statement_kind' => $statement['kind'], 'statement_index' => $index];
                }
            }
        }
        $covered = array_column($rows, 'ref');
        foreach ($cited as $ref) {
            if (! in_array($ref, $covered, true)) {
                $rows[] = ['ref' => $ref, 'statement_kind' => null, 'statement_index' => null];
            }
        }
        foreach ($rows as $row) {
            $quadrant->citations()->create($row + ['tenant_id' => $quadrant->getAttribute('tenant_id'), 'signal_id' => $signalIds[$row['ref']]]);
        }
    }

    /**
     * The client this user's generation runs on: their own key, the organization's key, or
     * the platform default, in LlmClientResolver's order.
     */
    public function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }

    /**
     * The scenario's evidence, once it is clear the generation may go ahead. The evidence rules
     * apply when $llm (the client the generation will actually use) can answer; otherwise the
     * template runs and needs none.
     *
     * @throws ConflictHttpException when edited quadrants would be overwritten
     * @throws ValidationException when an axis has no evidence for the LLM
     */
    protected function assertGeneratable(Scenario $scenario, bool $overwriteEdited, StructuredLlmClient $llm): array
    {
        $scenario->loadMissing(['axis_a.dimension', 'axis_b.dimension', 'axis_a_uncertainty', 'axis_b_uncertainty', 'quadrants']);

        if ($scenario->isFramework() && ! $scenario->isFrameworkApproved()) {
            throw ValidationException::withMessages(['framework' => 'Setujui kerangka skenario (G5) di latihan scenario planning dulu sebelum membuat narasi.']);
        }

        // Edited or reviewed quadrants hold human work that a new narrative would replace.
        $edited = $scenario->quadrants->filter->isEdited()->count();
        $reviewed = $scenario->quadrants->filter->isReviewed()->count();
        if (($edited > 0 || $reviewed > 0) && ! $overwriteEdited) {
            throw new ConflictHttpException(implode(' ', array_filter([
                $edited > 0 ? "{$edited} kuadran sudah disunting analis." : null,
                $reviewed > 0 ? "{$reviewed} kuadran sudah ditinjau atau disetujui dan akan kembali menjadi draf." : null,
                'Konfirmasi untuk menimpanya.',
            ])));
        }

        $evidence = $this->scenarios->evidenceFor($scenario);
        if (! $llm->isAvailable()) {
            return $evidence;
        }

        foreach (['A', 'B'] as $axis) {
            $force = $scenario->axisForce($axis);
            if (! $scenario->axisUncertainty($axis) && (! $force || $force->trashed())) {
                throw ValidationException::withMessages([
                    'evidence' => "Sumbu {$axis} telah dihapus. Pulihkan faktor atau buat skenario baru dengan faktor aktif.",
                ]);
            }
            if (! collect($evidence)->contains(fn ($item) => str_contains($item['axis'], $axis))) {
                throw ValidationException::withMessages([
                    'evidence' => "Sumbu {$axis} ({$scenario->axisName($axis)}) belum punya sinyal ACCEPTED yang tertaut. Tautkan sinyal dulu sebelum membuat narasi AI.",
                ]);
            }
        }

        return $evidence;
    }

    /**
     * Check the model's quadrants and keep only citations that were offered.
     *
     * @param  array<int, string>  $refs
     *
     * @throws LlmException when the answer does not hold four distinct quadrants
     * @throws ValidationException when a quadrant has no valid citation
     */
    protected function validated(array $data, array $refs): array
    {
        $byPosition = collect($data['quadrants'] ?? [])->filter(fn ($item) => is_array($item))->keyBy('position');
        if (array_diff(Scenario::POSITIONS, $byPosition->keys()->all()) !== []) {
            throw new LlmException('invalid_json', 'Missing quadrant positions.');
        }

        $allowed = array_flip($refs);
        $quadrants = [];
        $uncited = [];

        foreach (Scenario::POSITIONS as $position) {
            $item = $byPosition[$position];
            // Roadmap R2.7: typed statements become the narrative, each followed by its own labels.
            $statements = $this->statements($item['statements'] ?? null, $allowed);
            if ($statements !== null) {
                $item['narrative'] = implode(' ', array_map(fn ($row) => trim($row['text'].' '.implode('', array_map(fn ($ref) => "[{$ref}]", $row['refs']))), $statements));
            }
            // Unknown references are removed from the text; known ones are collected from both places.
            $narrative = preg_replace_callback('/\[(S\d+)\]/', fn ($match) => isset($allowed[$match[1]]) ? $match[0] : '', (string) ($item['narrative'] ?? ''));
            preg_match_all('/\[(S\d+)\]/', $narrative, $inText);
            $cited = array_values(array_unique(array_filter(
                [...($item['cited_refs'] ?? []), ...$inText[1]],
                fn ($ref) => is_string($ref) && isset($allowed[$ref])
            )));
            if ($cited === []) {
                $uncited[] = $position;
            }

            $quadrants[] = [
                'position' => $position,
                'title' => Str::limit(trim((string) ($item['title'] ?? '')) ?: $position, 250, ''),
                'narrative' => trim(preg_replace('/[ \t]{2,}/', ' ', $narrative)),
                'implications' => trim((string) ($item['implications'] ?? '')) ?: null,
                'early_indicators' => array_values(array_filter(array_map('trim', array_map('strval', (array) ($item['early_indicators'] ?? []))))),
                'cited_refs' => $cited,
                'statements' => $statements,
            ];
        }

        if ($uncited !== []) {
            throw ValidationException::withMessages([
                'citations' => 'Narasi AI ditolak karena kuadran '.implode(', ', $uncited).' tidak mengutip bukti yang valid. Tidak ada yang disimpan; coba buat ulang.',
            ]);
        }

        return $quadrants;
    }

    /**
     * Typed statements with only offered labels, or null when the answer has none (an untyped
     * narrative). A fact without a valid label is not backed by evidence, so it counts as the
     * model's interpretation; an unknown kind does too.
     *
     * @param  array<string, int>  $allowed
     * @return array<int, array{text: string, kind: string, refs: array<int, string>}>|null
     */
    protected function statements(mixed $raw, array $allowed): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $statements = [];
        foreach ($raw as $row) {
            $text = trim(preg_replace('/\[S\d+\]/', '', (string) ($row['text'] ?? '')));
            if ($text === '') {
                continue;
            }
            $refs = array_values(array_unique(array_filter((array) ($row['refs'] ?? []), fn ($ref) => is_string($ref) && isset($allowed[$ref]))));
            $kind = in_array($row['kind'] ?? null, ScenarioCitation::KINDS, true) ? $row['kind'] : 'interpretation';
            if ($kind === 'fact' && $refs === []) {
                $kind = 'interpretation';
            }
            $statements[] = ['text' => $text, 'kind' => $kind, 'refs' => $refs];
        }

        return $statements === [] ? null : $statements;
    }

    /**
     * Skeleton quadrants without AI: the poles, the first evidence of each
     * axis when there is any, and prompts for the analyst.
     */
    protected function template(Scenario $scenario, array $evidence): array
    {
        $firstOf = fn (string $axis) => collect($evidence)->first(fn ($item) => str_contains($item['axis'], $axis));
        $cited = array_values(array_filter([$firstOf('A'), $firstOf('B')]));
        $refs = array_values(array_unique(array_column($cited, 'ref')));

        return array_map(function (string $position) use ($scenario, $cited, $refs) {
            [$poleA, $poleB] = $scenario->poles($position);
            $axisAKeyword = $scenario->axisName('A') ?? 'Sumbu A';
            $axisBKeyword = $scenario->axisName('B') ?? 'Sumbu B';
            $evidenceLine = $cited === []
                ? 'Belum ada sinyal ACCEPTED yang tertaut ke sumbu ini; tautkan sinyal agar narasi bisa didasarkan pada bukti.'
                : 'Bukti awal: '.implode('; ', array_map(fn ($item) => "[{$item['ref']}] {$item['signal']->title}", $cited)).'.';

            return [
                'position' => $position,
                'title' => "{$poleA} · {$poleB}",
                'narrative' => "Kerangka tanpa AI. Bayangkan masa depan ketika {$axisAKeyword} berada di kutub \"{$poleA}\" "
                    ."dan {$axisBKeyword} di kutub \"{$poleB}\". {$evidenceLine} "
                    .'Lengkapi narasi ini bersama tim: apa yang berubah bagi pelanggan, pemasok, dan organisasi?',
                'implications' => 'Belum diisi. Tuliskan apa arti masa depan ini bagi strategi organisasi.',
                'early_indicators' => [
                    "Perkembangan {$axisAKeyword} menuju \"{$poleA}\"",
                    "Perkembangan {$axisBKeyword} menuju \"{$poleB}\"",
                ],
                'cited_refs' => $refs,
            ];
        }, Scenario::POSITIONS);
    }
}
