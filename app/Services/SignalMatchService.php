<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\MatchSignals;
use App\Models\AiGenerationRun;
use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\SignalMatchPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 9 AI assist (roadmap R2.6): match accepted signals that no observation of the exercise cites
 * yet to its active indicators. Matches become observations with origin `ai` and review status
 * `proposed`; they count for relevance and thresholds only once a person confirms them.
 */
class SignalMatchService
{
    /** Signals offered per run, newest first. */
    public const MAX_SIGNALS = 30;

    public function __construct(
        protected StructuredLlmClient $llm,
    ) {}

    /** @throws ValidationException without indicators, without new signals, or without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        if ($this->indicators($project)->isEmpty()) {
            throw ValidationException::withMessages(['ai' => 'Belum ada indikator aktif yang diterima untuk dicocokkan.']);
        }
        if ($this->newSignals($project)->isEmpty()) {
            throw ValidationException::withMessages(['ai' => 'Tidak ada sinyal diterima yang belum dipakai sebagai observasi di latihan ini.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['match_suggestion_status' => LlmJobStatus::QUEUED->value, 'match_suggestion_error' => null]);
        MatchSignals::dispatch($project, $user->id);
    }

    /** Run by MatchSignals. A new run replaces the AI observations still waiting for confirmation. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $indicatorRefs = [];
        $indicators = [];
        foreach ($this->indicators($project)->values() as $index => $indicator) {
            $label = 'I'.($index + 1);
            $indicatorRefs[$label] = $indicator->id;
            $indicators[] = [
                'label' => $label,
                'name' => $indicator->name,
                'metric' => $indicator->metric,
                'directions' => $indicator->directions->map(fn ($row) => $row->quadrant?->position.' '.$row->expected_direction)->implode(', '),
            ];
        }
        $signalRefs = [];
        $signals = [];
        foreach ($this->newSignals($project)->values() as $index => $signal) {
            $ref = 'S'.($index + 1);
            $signalRefs[$ref] = $signal;
            $signals[] = ['ref' => $ref, 'title' => $signal->title, 'summary' => $signal->summary];
        }
        $inputRefs = ['project' => $project->uuid, 'signals' => collect($signalRefs)->map->id->values()->all()];

        try {
            $response = $llm->generateStructured(SignalMatchPrompt::request($project, $indicators, $signals));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'indicator_signal_match', $llm, SignalMatchPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $matches = collect($response->data['matches'] ?? []);
        $problem = $this->problem($matches, $indicatorRefs, $signalRefs);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'indicator_signal_match', $llm, SignalMatchPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Usulan AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('indicator_signal_match', $response, SignalMatchPrompt::VERSION, $inputRefs, $userId, $project->id);
        DB::transaction(function () use ($project, $matches, $indicatorRefs, $signalRefs, $userId) {
            IndicatorObservation::whereIn('early_warning_indicator_id', EarlyWarningIndicator::where('project_id', $project->id)->select('id'))
                ->where('origin', Provenance::AI)->where('review_status', 'proposed')->delete();
            foreach ($matches as $match) {
                $signal = $signalRefs[$match['signal']];
                IndicatorObservation::create([
                    'early_warning_indicator_id' => $indicatorRefs[$match['indicator']],
                    'observed_at' => now()->toDateString(),
                    'value_text' => Str::limit($signal->title, 255, ''),
                    'direction' => $match['direction'] === SignalMatchPrompt::NONE ? null : $match['direction'],
                    'crossed_threshold' => false,
                    'signal_id' => $signal->id,
                    'note' => Str::limit(trim((string) ($match['note'] ?? '')), 1000, '') ?: null,
                    'origin' => Provenance::AI,
                    'review_status' => 'proposed',
                    'recorded_by' => $userId,
                ]);
            }
            $project->update(['match_suggestion_status' => LlmJobStatus::COMPLETED->value, 'match_suggestion_error' => null]);
        });
    }

    /** A person confirms an AI match: it becomes their observation and counts from now on. */
    public function confirm(IndicatorObservation $observation, User $user): void
    {
        if ($observation->review_status !== 'proposed') {
            throw ValidationException::withMessages(['observations' => 'Observasi ini sudah dikonfirmasi.']);
        }
        $observation->update(['review_status' => 'accepted', 'recorded_by' => $user->id]);
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['match_suggestion_status' => LlmJobStatus::FAILED->value, 'match_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the matches cannot be used, or null.
     *
     * @param  Collection<int, array<string, mixed>>  $matches
     * @param  array<string, int>  $indicatorRefs
     * @param  array<string, Signal>  $signalRefs
     */
    private function problem(Collection $matches, array $indicatorRefs, array $signalRefs): ?string
    {
        if ($matches->count() > SignalMatchPrompt::MAX_MATCHES) {
            return 'lebih dari '.SignalMatchPrompt::MAX_MATCHES.' kecocokan.';
        }
        foreach ($matches as $match) {
            if (! isset($indicatorRefs[$match['indicator'] ?? ''])) {
                return "indikator {$match['indicator']} tidak diberikan.";
            }
            if (! isset($signalRefs[$match['signal'] ?? ''])) {
                return "sinyal {$match['signal']} tidak diberikan.";
            }
            if (! in_array($match['direction'] ?? null, [...IndicatorObservation::DIRECTIONS, SignalMatchPrompt::NONE], true)) {
                return 'arah kecocokan tidak dikenal.';
            }
        }

        return null;
    }

    /** @return EloquentCollection<int, EarlyWarningIndicator> */
    private function indicators(ScenarioPlanningProject $project): EloquentCollection
    {
        return EarlyWarningIndicator::where('project_id', $project->id)
            ->where('status', 'active')
            ->where('review_status', 'accepted')
            ->with('directions.quadrant')
            ->orderBy('name')
            ->get();
    }

    /** @return EloquentCollection<int, Signal> accepted signals no observation of the exercise cites, newest first */
    private function newSignals(ScenarioPlanningProject $project): EloquentCollection
    {
        $cited = IndicatorObservation::whereIn('early_warning_indicator_id', EarlyWarningIndicator::where('project_id', $project->id)->select('id'))
            ->whereNotNull('signal_id')
            ->select('signal_id');

        return Signal::where('review_status', 'ACCEPTED')->whereNotIn('id', $cited)->latest('id')->limit(self::MAX_SIGNALS)->get();
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
