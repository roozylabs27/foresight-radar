<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestForceCandidates;
use App\Models\AiGenerationRun;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\ForceCandidate;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ForceClusterPrompt;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Step 2 AI assist (roadmap R2.4): candidate driving forces from accepted signals that no force of
 * the exercise rests on yet. Candidates cite only the offered signals and wait for review;
 * accepting one creates a PENDING library force (normal approval flow), links its signals and adds
 * it to the exercise; rejecting keeps it out.
 */
class ForceCandidateService
{
    /** Signals offered per run, newest first. */
    public const MAX_SIGNALS = 40;

    public function __construct(
        protected StructuredLlmClient $llm,
        protected ProjectDrivingForceService $forces,
    ) {}

    /**
     * @throws ValidationException before the focal issue is confirmed, without signals to group, or without AI
     */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        if (! $project->isConfirmed()) {
            throw ValidationException::withMessages(['ai' => 'Konfirmasi focal issue dulu sebelum meminta usulan driving force.']);
        }
        if ($this->offeredSignals($project)->isEmpty()) {
            throw ValidationException::withMessages(['ai' => 'Tidak ada sinyal diterima yang belum menjadi dasar driving force di latihan ini.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['force_suggestion_status' => LlmJobStatus::QUEUED->value, 'force_suggestion_error' => null]);
        SuggestForceCandidates::dispatch($project, $user->id);
    }

    /** Run by SuggestForceCandidates. A new run replaces the candidates still awaiting review. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $refs = [];
        $offered = [];
        foreach ($this->offeredSignals($project)->values() as $index => $signal) {
            $ref = 'S'.($index + 1);
            $refs[$ref] = $signal->id;
            $offered[] = ['ref' => $ref, 'title' => $signal->title, 'summary' => $signal->summary, 'category' => $signal->dimension?->name];
        }
        $dimensions = Dimension::active()->ordered()->whereNotNull('code')->get(['id', 'code', 'name']);
        $categories = $dimensions->map(fn ($dimension) => ['code' => $dimension->code, 'name' => $dimension->name])->values()->all();
        $inputRefs = ['project' => $project->uuid, 'signals' => array_values($refs)];

        try {
            $response = $llm->generateStructured(ForceClusterPrompt::request($project, $categories, $offered));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'force_cluster', $llm, ForceClusterPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $candidates = collect($response->data['candidates'] ?? []);
        $codes = $dimensions->pluck('id', 'code');
        $problem = $this->problem($candidates, $refs, $codes);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'force_cluster', $llm, ForceClusterPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Usulan AI ditolak: '.$problem);

            return;
        }

        $run = app(AiGenerationRecorder::class)->completed('force_cluster', $response, ForceClusterPrompt::VERSION, $inputRefs, $userId, $project->id);
        DB::transaction(function () use ($project, $candidates, $refs, $codes, $run) {
            $project->candidates()->where('status', 'proposed')->delete();
            foreach ($candidates as $item) {
                $project->candidates()->create([
                    'keyword' => trim((string) $item['keyword']),
                    'description' => trim((string) $item['description']),
                    'dimension_id' => $codes[$item['dimension']],
                    'signal_ids' => array_values(array_unique(array_map(fn ($ref) => $refs[$ref], $item['signal_refs']))),
                    'rationale' => Str::limit((string) ($item['rationale'] ?? ''), 1000, ''),
                    'status' => 'proposed',
                    'generation_run_id' => $run->id,
                ]);
            }
            $project->update(['force_suggestion_status' => LlmJobStatus::COMPLETED->value, 'force_suggestion_error' => null]);
        });
    }

    /** Create the PENDING library force, link its signals and add it to the exercise. */
    public function accept(ForceCandidate $candidate, User $user): DrivingForce
    {
        if ($candidate->status !== 'proposed') {
            throw ValidationException::withMessages(['ai' => 'Usulan ini sudah ditinjau.']);
        }

        return DB::transaction(function () use ($candidate, $user) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(),
                'dimension_id' => $candidate->dimension_id,
                'created_by' => $user->id,
                'keyword' => $candidate->keyword,
                'description' => $candidate->description,
                'status' => 'PENDING',
            ]);
            $force->supporting_signals()->syncWithoutDetaching(Signal::whereIn('id', $candidate->signal_ids)->pluck('id')->all());
            $this->forces->add($candidate->project, [$force->uuid], $user);
            $candidate->update(['status' => 'accepted', 'driving_force_id' => $force->id, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);

            return $force;
        });
    }

    public function reject(ForceCandidate $candidate, User $user): void
    {
        if ($candidate->status === 'proposed') {
            $candidate->update(['status' => 'rejected', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        }
    }

    /** Candidates awaiting review, with their dimension and signals. */
    public function proposed(ScenarioPlanningProject $project): array
    {
        $candidates = $project->candidates()->where('status', 'proposed')->with('dimension:id,name')->orderBy('id')->get();
        $signals = Signal::whereIn('id', $candidates->pluck('signal_ids')->flatten()->unique()->all())->get(['id', 'uuid', 'title'])->keyBy('id');

        return $candidates->map(fn (ForceCandidate $candidate) => [
            'id' => $candidate->uuid,
            'keyword' => $candidate->keyword,
            'description' => $candidate->description,
            'dimension' => $candidate->dimension?->name,
            'rationale' => $candidate->rationale,
            'signals' => collect($candidate->signal_ids)->map(fn ($id) => $signals->get($id))->filter()->map(fn (Signal $signal) => ['id' => $signal->uuid, 'title' => $signal->title])->values()->all(),
        ])->all();
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['force_suggestion_status' => LlmJobStatus::FAILED->value, 'force_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the answer cannot be used, or null.
     *
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @param  array<string, int>  $refs
     * @param  Collection<string, int>  $codes
     */
    private function problem(Collection $candidates, array $refs, Collection $codes): ?string
    {
        if ($candidates->count() > ForceClusterPrompt::MAX_CANDIDATES) {
            return 'lebih dari '.ForceClusterPrompt::MAX_CANDIDATES.' usulan.';
        }
        foreach ($candidates as $item) {
            $keyword = trim((string) ($item['keyword'] ?? ''));
            if ($keyword === '' || str_word_count($keyword) > 4 || mb_strlen($keyword) > 100) {
                return "nama \"{$keyword}\" harus 1–4 kata.";
            }
            if (str_word_count((string) ($item['description'] ?? '')) > 120) {
                return "deskripsi \"{$keyword}\" lebih dari 120 kata.";
            }
            if (! $codes->has($item['dimension'] ?? '')) {
                return "kategori \"{$keyword}\" tidak dikenal.";
            }
            if (($item['signal_refs'] ?? []) === []) {
                return "\"{$keyword}\" tidak mengutip sinyal.";
            }
            foreach ($item['signal_refs'] as $ref) {
                if (! isset($refs[$ref])) {
                    return "\"{$keyword}\" mengutip {$ref}, yang tidak diberikan.";
                }
            }
        }

        return null;
    }

    /** @return EloquentCollection<int, Signal> accepted signals no force of the exercise rests on, newest first */
    private function offeredSignals(ScenarioPlanningProject $project): EloquentCollection
    {
        return Signal::where('review_status', 'ACCEPTED')
            ->whereDoesntHave('driving_forces', fn ($q) => $q->whereIn('driving_forces.id', $project->projectForces()->select('driving_force_id')))
            ->with('dimension:id,name')
            ->latest('id')
            ->limit(self::MAX_SIGNALS)
            ->get();
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
