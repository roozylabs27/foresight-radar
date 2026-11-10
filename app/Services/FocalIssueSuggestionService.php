<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestFocalIssue;
use App\Models\AiGenerationRun;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\FocalIssuePrompt;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 1 AI assist (roadmap R3.1): a critique of the focal question and alternative questions.
 * The suggestion never changes the exercise: the coordinator picks an alternative into the edit
 * dialog and saves it through the normal update, which drops the suggestion.
 */
class FocalIssueSuggestionService
{
    public const QUESTION_LIMIT = 300;

    public const MAX_CRITIQUE = 5;

    public const MIN_ALTERNATIVES = 2;

    public const MAX_ALTERNATIVES = 3;

    public function __construct(protected StructuredLlmClient $llm) {}

    /** @throws ValidationException without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['focal_suggestion_status' => LlmJobStatus::QUEUED->value, 'focal_suggestion_error' => null]);
        SuggestFocalIssue::dispatch($project, $user->id);
    }

    /** Run by SuggestFocalIssue. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $inputRefs = ['project' => $project->uuid];

        try {
            $response = $llm->generateStructured(FocalIssuePrompt::request($project));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'scenario_focal_issue', $llm, FocalIssuePrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $data = $response->data;
        $problem = $this->problem($data, (string) $project->focal_question);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'scenario_focal_issue', $llm, FocalIssuePrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Saran AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('scenario_focal_issue', $response, FocalIssuePrompt::VERSION, $inputRefs, $userId, $project->id);
        $project->update([
            'focal_suggestion' => [
                'critique' => collect($data['critique'] ?? [])->map(fn ($row) => [
                    'issue' => in_array($row['issue'] ?? null, FocalIssuePrompt::ISSUES, true) ? $row['issue'] : 'other',
                    'comment' => Str::limit(trim((string) ($row['comment'] ?? '')), 500, ''),
                ])->filter(fn ($row) => $row['comment'] !== '')->values()->all(),
                'alternatives' => collect($data['alternatives'])->map(fn ($row) => [
                    'question' => trim((string) $row['question']),
                    'rationale' => Str::limit(trim((string) ($row['rationale'] ?? '')), 500, ''),
                ])->values()->all(),
            ],
            'focal_suggestion_status' => LlmJobStatus::COMPLETED->value,
            'focal_suggestion_error' => null,
        ]);
    }

    public function reject(ScenarioPlanningProject $project): void
    {
        $project->update(['focal_suggestion' => null]);
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['focal_suggestion_status' => LlmJobStatus::FAILED->value, 'focal_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /** Why the suggestion cannot be used, or null: two or three distinct new questions of a usable length. */
    private function problem(array $data, string $current): ?string
    {
        $normalize = fn (string $text) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
        $questions = collect($data['alternatives'] ?? [])->map(fn ($row) => trim((string) ($row['question'] ?? '')));

        if ($questions->count() < self::MIN_ALTERNATIVES || $questions->count() > self::MAX_ALTERNATIVES) {
            return 'jumlah pertanyaan alternatif bukan '.self::MIN_ALTERNATIVES.'–'.self::MAX_ALTERNATIVES.'.';
        }
        if ($questions->contains(fn ($question) => mb_strlen($question) < 10 || mb_strlen($question) > self::QUESTION_LIMIT)) {
            return 'ada pertanyaan alternatif yang kosong atau terlalu panjang.';
        }
        $normalized = $questions->map($normalize);
        if ($normalized->unique()->count() !== $normalized->count() || $normalized->contains($normalize($current))) {
            return 'pertanyaan alternatif kembar atau sama dengan pertanyaan saat ini.';
        }
        if (count($data['critique'] ?? []) > self::MAX_CRITIQUE) {
            return 'terlalu banyak poin kritik.';
        }

        return null;
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
