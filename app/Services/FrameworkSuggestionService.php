<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestFramework;
use App\Models\AiGenerationRun;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ScenarioFrameworkPrompt;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 4 AI assist (roadmap R2.5): pole labels and quadrant names for a draft framework. The
 * suggestion waits on the framework; applying it goes through the normal pole update (quadrants
 * with a narrative are flagged) and renames the quadrants; discarding drops it.
 */
class FrameworkSuggestionService
{
    public const POLE_LIMIT = 120;

    public const TITLE_LIMIT = 120;

    public const SUMMARY_LIMIT = 200;

    public function __construct(
        protected StructuredLlmClient $llm,
        protected ScenarioFrameworkService $frameworks,
    ) {}

    /** @throws ValidationException without a draft framework or without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        $this->draftFramework($project);
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['framework_suggestion_status' => LlmJobStatus::QUEUED->value, 'framework_suggestion_error' => null]);
        SuggestFramework::dispatch($project, $user->id);
    }

    /** Run by SuggestFramework. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $framework = $project->framework()->firstOrFail();
        $inputRefs = ['project' => $project->uuid, 'framework' => $framework->uuid];

        try {
            $response = $llm->generateStructured(ScenarioFrameworkPrompt::request($framework));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'scenario_framework', $llm, ScenarioFrameworkPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $data = $response->data;
        $problem = $this->problem($data);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'scenario_framework', $llm, ScenarioFrameworkPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Saran AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('scenario_framework', $response, ScenarioFrameworkPrompt::VERSION, $inputRefs, $userId, $project->id);
        DB::transaction(function () use ($project, $framework, $data) {
            $framework->update(['framework_suggestion' => [
                'axis_a_low_label' => trim($data['axis_a_low_label']),
                'axis_a_high_label' => trim($data['axis_a_high_label']),
                'axis_b_low_label' => trim($data['axis_b_low_label']),
                'axis_b_high_label' => trim($data['axis_b_high_label']),
                'quadrants' => collect($data['quadrants'])->mapWithKeys(fn ($row) => [$row['position'] => ['title' => trim($row['title']), 'summary' => trim((string) $row['summary'])]])->all(),
                'rationale' => Str::limit((string) ($data['rationale'] ?? ''), 1000, ''),
            ]]);
            $project->update(['framework_suggestion_status' => LlmJobStatus::COMPLETED->value, 'framework_suggestion_error' => null]);
        });
    }

    /** Apply the waiting suggestion: new poles (via the normal pole update), then the quadrant names. */
    public function accept(ScenarioPlanningProject $project, User $user): void
    {
        $framework = $this->draftFramework($project);
        $suggestion = $framework->framework_suggestion;
        if (! $suggestion) {
            throw ValidationException::withMessages(['ai' => 'Tidak ada saran AI untuk kerangka ini.']);
        }

        DB::transaction(function () use ($framework, $suggestion, $user) {
            $this->frameworks->updatePoles($framework, array_intersect_key($suggestion, array_flip(['axis_a_low_label', 'axis_a_high_label', 'axis_b_low_label', 'axis_b_high_label'])), $user);
            foreach ($framework->quadrants()->get() as $quadrant) {
                $row = $suggestion['quadrants'][$quadrant->position] ?? null;
                if ($row) {
                    $quadrant->update(['title' => $row['title'], 'summary' => $row['summary'] ?: null]);
                }
            }
            $framework->update(['framework_suggestion' => null]);
        });
    }

    public function reject(ScenarioPlanningProject $project): void
    {
        $project->framework()->first()?->update(['framework_suggestion' => null]);
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['framework_suggestion_status' => LlmJobStatus::FAILED->value, 'framework_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /** Why the suggestion cannot be used, or null: four distinct-pole labels and four distinct, named quadrants. */
    private function problem(array $data): ?string
    {
        foreach (['a', 'b'] as $axis) {
            $low = trim((string) ($data["axis_{$axis}_low_label"] ?? ''));
            $high = trim((string) ($data["axis_{$axis}_high_label"] ?? ''));
            if ($low === '' || $high === '' || mb_strlen($low) > self::POLE_LIMIT || mb_strlen($high) > self::POLE_LIMIT) {
                return 'label kutub sumbu '.strtoupper($axis).' kosong atau terlalu panjang.';
            }
            if (mb_strtolower($low) === mb_strtolower($high)) {
                return 'kedua kutub sumbu '.strtoupper($axis).' sama.';
            }
        }

        $quadrants = collect($data['quadrants'] ?? []);
        if ($quadrants->pluck('position')->sort()->values()->all() !== collect(Scenario::POSITIONS)->sort()->values()->all()) {
            return 'tidak tepat satu nama untuk tiap kuadran.';
        }
        $titles = $quadrants->map(fn ($row) => mb_strtolower(trim((string) ($row['title'] ?? ''))));
        if ($titles->contains('') || $titles->unique()->count() !== count(Scenario::POSITIONS)) {
            return 'nama kuadran kosong atau kembar.';
        }
        foreach ($quadrants as $row) {
            if (mb_strlen(trim((string) $row['title'])) > self::TITLE_LIMIT || mb_strlen(trim((string) ($row['summary'] ?? ''))) > self::SUMMARY_LIMIT) {
                return "nama atau ringkasan {$row['position']} terlalu panjang.";
            }
        }

        return null;
    }

    private function draftFramework(ScenarioPlanningProject $project): Scenario
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            throw ValidationException::withMessages(['ai' => 'Simpan kerangka skenario dulu sebelum meminta saran AI.']);
        }
        if ($framework->isFrameworkApproved()) {
            throw ValidationException::withMessages(['ai' => 'Kerangka sudah disetujui. Buka kembali dulu untuk mengubah kutub dan nama.']);
        }

        return $framework;
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
