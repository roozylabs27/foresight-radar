<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Enums\TimeHorizonType;
use App\Jobs\ExtractSignals;
use App\Models\AiGenerationRun;
use App\Models\Dimension;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Extraction\LlmSignalExtractor;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\SignalExtractionPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\EvidenceVerifier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class SignalExtractionService
{
    public const PROVIDER_HEURISTIC = 'heuristic';

    public const AI_MISSING = 'Layanan AI belum diatur. Atur provider AI di Pengaturan.';

    /**
     * Both dependencies are optional so subclasses (and tests) can construct
     * the service without arguments; they are resolved from the container.
     */
    public function __construct(
        protected ?LlmSignalExtractor $llmExtractor = null,
        protected ?StructuredLlmClient $llm = null,
    ) {}

    /**
     * Resolve the structured LLM client for a specific user, falling back to system default.
     */
    public function clientForUser(int|User|null $user = null): StructuredLlmClient
    {
        $userId = $user instanceof User ? $user->id : $user;
        if ($userId) {
            $userLlm = app(LlmClientResolver::class)->resolveForUserId((int) $userId, false);
            if ($userLlm !== null) {
                return $userLlm;
            }
        }

        return $this->llm ?? $this->llm();
    }

    /**
     * The LLM provider id (e.g. "claude", "gemini", "openrouter") when signals will be
     * extracted by the LLM, otherwise "heuristic".
     */
    public function mode(int|User|null $user = null): string
    {
        $client = $this->clientForUser($user ?? Auth::user());

        return config('services.signal_extraction.driver') !== self::PROVIDER_HEURISTIC && $client->isAvailable()
            ? $client->provider()
            : self::PROVIDER_HEURISTIC;
    }

    public function usesLlm(int|User|null $user = null): bool
    {
        return $this->mode($user) !== self::PROVIDER_HEURISTIC;
    }

    public function modelName(int|User|null $user = null): ?string
    {
        $client = $this->clientForUser($user ?? Auth::user());

        return $this->usesLlm($user) ? $client->model() : null;
    }

    /** Display name of the extractor: the provider's label, or "Aturan". */
    public function modeLabel(int|User|null $user = null): string
    {
        $client = $this->clientForUser($user ?? Auth::user());

        return $this->usesLlm($user) ? $client->label() : 'Aturan';
    }

    /**
     * True when external sources must go through AI but no provider is active for the user (or their
     * organisation). Keyword rules run only when the driver is explicitly "heuristic" (tests, offline).
     */
    public function aiMissing(int|User|null $user = null): bool
    {
        return config('services.signal_extraction.driver') !== self::PROVIDER_HEURISTIC
            && ! $this->clientForUser($user ?? Auth::user())->isAvailable();
    }

    /**
     * Save the source and queue its extraction. The LLM call runs in the
     * ExtractSignals job, so the request returns before the model answers.
     *
     * @throws ValidationException when no AI provider is active
     */
    public function ingest(array $data, int $userId): Source
    {
        $this->requireAi($userId);
        $content = trim($data['content'] ?? '');

        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => $data['title'] ?? 'Ingested Source Document',
            'url' => $data['url'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'published_at' => ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : Carbon::today(),
            'raw_content' => $content,
            'content_hash' => hash('sha256', $content),
            'created_by' => $userId,
            'extraction_status' => LlmJobStatus::QUEUED,
        ]);

        ExtractSignals::dispatch($source);

        return $source->refresh();
    }

    /**
     * Queue the extraction of a failed source again, through AI.
     *
     * @throws ValidationException when the source did not fail or no AI provider is active
     */
    public function retry(Source $source): Source
    {
        if ($source->extraction_status !== LlmJobStatus::FAILED) {
            throw ValidationException::withMessages(['source' => 'Hanya ekstraksi yang gagal yang bisa dicoba lagi.']);
        }
        $this->requireAi($source->created_by ? (int) $source->created_by : null);

        $source->update(['extraction_status' => LlmJobStatus::QUEUED, 'extraction_error' => null]);
        ExtractSignals::dispatch($source);

        return $source->refresh();
    }

    /** @throws ValidationException */
    private function requireAi(?int $userId): void
    {
        if ($this->aiMissing($userId)) {
            throw ValidationException::withMessages(['ai' => self::AI_MISSING]);
        }
    }

    /** Mark the source failed with a reason the analyst sees, keeping no signals. */
    private function failSource(Source $source, string $message): void
    {
        $source->update(['extraction_status' => LlmJobStatus::FAILED, 'extraction_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Extract candidate signals from a saved source and mark it completed.
     *
     * The LLM is called before the transaction opens, so a slow call holds no
     * database locks. External sources go through AI only: when no provider is
     * active or the call fails, the source is marked failed with the reason and
     * can be retried; keyword rules run only with the "heuristic" driver (tests,
     * offline). When the AI finds no verifiable signal, the source is completed
     * without signals.
     */
    public function extract(Source $source): void
    {
        if ($source->extraction_status === LlmJobStatus::COMPLETED) {
            return;
        }

        $source->update(['extraction_status' => LlmJobStatus::PROCESSING, 'extraction_error' => null]);

        $content = (string) $source->raw_content;
        $title = (string) $source->title;
        $userLlm = $source->created_by ? app(LlmClientResolver::class)->resolveForUserId((int) $source->created_by, false) : null;
        $llm = $userLlm ?? ($this->llm ?? $this->llm());
        $useAi = config('services.signal_extraction.driver') !== self::PROVIDER_HEURISTIC;
        $notice = null;
        $candidates = null;
        $meta = [];

        if ($useAi && ! $llm->isAvailable()) {
            $this->failSource($source, self::AI_MISSING.' Lalu coba lagi dari halaman Sinyal.');

            return;
        }

        if ($useAi) {
            // Every AI call is recorded (roadmap R1.12); signals written by keyword rules link no run.
            $refs = ['source' => $source->uuid];
            try {
                $extractor = $userLlm ? new LlmSignalExtractor($userLlm) : ($this->llmExtractor ?? $this->llmExtractor());
                $result = $extractor->extract($title, $content);
                $run = app(AiGenerationRecorder::class)->completed('signal_extraction', $result['response'], SignalExtractionPrompt::VERSION, $refs, $source->created_by);
                $candidates = array_map(fn ($item) => $item + [
                    'extraction_provider' => $result['meta']['provider'],
                    'extraction_model' => $result['meta']['model'],
                    'prompt_version' => $result['meta']['prompt_version'],
                    'generation_run_id' => $run->id,
                ], $result['candidates']);
                $meta = $result['meta'] + ['label' => $llm->label()];
                if ($meta['irrelevant'] ?? false) {
                    $notice = 'Dokumen dinilai tidak relevan dengan lingkungan bisnis, sehingga tidak ada sinyal yang disimpan.'
                        .($meta['relevance_reason'] ? ' Alasan: '.$meta['relevance_reason'] : '');
                }
            } catch (LlmException $e) {
                app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'signal_extraction', $llm, SignalExtractionPrompt::VERSION, $refs, $e->reason, $source->created_by);
                Log::warning('Signal extraction failed', ['reason' => $e->reason]);
                $this->failSource($source, $e->userMessage().' Sumber tersimpan tanpa sinyal. Coba lagi dari halaman Sinyal.');

                return;
            }
        }

        if ($candidates === null) {
            $candidates = array_map(fn ($item) => $item + [
                'extraction_provider' => self::PROVIDER_HEURISTIC,
                'extraction_model' => null,
                'prompt_version' => null,
                'quote_verified' => EvidenceVerifier::verify($item['evidence_quote'], $content),
                'suggested_driving_force_id' => null,
            ], $this->extractCandidatesFromText($content, $title));
            $meta += ['provider' => self::PROVIDER_HEURISTIC, 'returned' => count($candidates)];
        }

        DB::transaction(function () use ($source, $candidates, $meta, $notice) {
            $source->signals()->where('review_status', 'PENDING')->delete();

            foreach ($candidates as $item) {
                Signal::create([
                    'uuid' => (string) Uuid::uuid4(),
                    'source_id' => $source->id,
                    'review_status' => 'PENDING',
                    'is_ai_generated' => true,
                ] + collect($item)->only([
                    'dimension_id', 'title', 'summary', 'significance', 'evidence_quote',
                    'suggested_time_horizon_id', 'preliminary_impact', 'preliminary_uncertainty',
                    'confidence_score', 'confidence_rationale', 'extraction_provider', 'extraction_model',
                    'prompt_version', 'quote_verified', 'suggested_driving_force_id', 'generation_run_id',
                    'horizon', 'wild_card',
                ])->all());
            }

            $source->update([
                'extraction_meta' => $meta + ['notice' => $notice],
                'extraction_status' => LlmJobStatus::COMPLETED,
            ]);
        });
    }

    /**
     * Where a source's extraction stands; once it is done, a one-sentence
     * result: who extracted, how many signals, how many were dropped.
     *
     * @return array{id: string, title: string, status: string, error: ?string, message: ?string, extraction: ?array}
     */
    public function progress(Source $source): array
    {
        // Sources saved before extraction was queued have no status and are complete.
        $status = $source->extraction_status ?? LlmJobStatus::COMPLETED;
        $staleWindow = (int) config('queue.llm_job_timeout', 300);

        if ($status === LlmJobStatus::PROCESSING
            && $source->updated_at
            && $source->updated_at->diffInSeconds(now()) >= $staleWindow) {
            $status = LlmJobStatus::FAILED;
            $error = $source->extraction_error ?? 'Ekstraksi sinyal terhenti karena timeout atau kegagalan server. Sumber tersimpan tanpa sinyal; coba ingest ulang dokumen ini.';
            $source->update([
                'extraction_status' => LlmJobStatus::FAILED,
                'extraction_error' => $error,
            ]);
        } else {
            $error = $source->extraction_error;
        }

        $progress = [
            'id' => $source->uuid,
            'title' => $source->title,
            'status' => $status->value,
            'error' => $error,
            'message' => null,
            'extraction' => null,
        ];

        if ($status !== LlmJobStatus::COMPLETED) {
            return $progress;
        }

        $meta = $source->extraction_meta ?? [];
        $count = $source->signals()->count();
        $ai = ($meta['provider'] ?? self::PROVIDER_HEURISTIC) !== self::PROVIDER_HEURISTIC;
        $label = $ai ? ($meta['label'] ?? $meta['provider']) : 'Aturan';
        $discarded = $meta['discarded_unverified'] ?? 0;

        $irrelevant = (bool) ($meta['irrelevant'] ?? false);

        $by = $ai ? "oleh {$label} (".($meta['model'] ?? '-').')' : 'dengan aturan kata kunci (tanpa AI)';
        if ($irrelevant) {
            $message = "Sumber disimpan tanpa sinyal: dokumen dinilai tidak relevan dengan lingkungan bisnis {$by}.";
        } else {
            $message = "Sumber disimpan. {$count} sinyal kandidat diekstrak {$by}.";
            if ($discarded > 0) {
                $message .= " {$discarded} usulan dibuang karena kutipannya tidak ditemukan verbatim di dokumen.";
            }
            if ($ai && $count === 0) {
                $message .= ' Tidak ada sinyal dengan bukti terverifikasi di dokumen ini.';
            }
        }

        return array_merge($progress, [
            'message' => $message,
            'extraction' => [
                'mode' => $meta['provider'] ?? self::PROVIDER_HEURISTIC,
                'label' => $label,
                'model' => $ai ? ($meta['model'] ?? null) : null,
                'fallback' => isset($meta['fallback_reason']),
                'irrelevant' => $irrelevant,
                'notice' => $meta['notice'] ?? null,
                'signals' => $count,
                'discarded' => $discarded + ($meta['discarded_duplicate'] ?? 0),
            ],
        ]);
    }

    protected function llm(): StructuredLlmClient
    {
        return $this->llm ??= app(StructuredLlmClient::class);
    }

    protected function llmExtractor(): LlmSignalExtractor
    {
        return $this->llmExtractor ??= app(LlmSignalExtractor::class);
    }

    /**
     * Parse raw text into candidate signal structures.
     */
    protected function extractCandidatesFromText(string $text, string $sourceTitle): array
    {
        $dimensions = Dimension::active()->ordered()->get();
        $timeHorizons = TimeHorizon::all();

        // Split by double newlines or headers to identify distinct paragraphs/sections
        $paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', $text)), function ($p) {
            return strlen($p) >= 40;
        });

        if (empty($paragraphs)) {
            $paragraphs = [$text];
        }

        // Limit candidate generation to top 3 highest-density paragraphs for the POC
        $candidates = [];
        $selectedParagraphs = array_slice($paragraphs, 0, 3);

        foreach ($selectedParagraphs as $index => $para) {
            $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z0-9])/', $para);
            $evidenceQuote = $sentences[0] ?? $para;
            if (strlen($evidenceQuote) < 30 && isset($sentences[1])) {
                $evidenceQuote .= ' '.$sentences[1];
            }

            $matchedDimension = $this->detectDimension($para, $dimensions);
            $matchedHorizon = $this->detectTimeHorizon($para, $timeHorizons);
            $scores = $this->estimateScores($para);

            // Title generation: extract leading phrase or synthesize from source title
            $paraWords = explode(' ', trim(preg_replace('/[^\w\s]/', '', $para)));
            $firstWords = implode(' ', array_slice($paraWords, 0, 6));
            $candidateTitle = count($selectedParagraphs) === 1
                ? ($sourceTitle ?: $firstWords)
                : $firstWords;

            $summary = strlen($para) > 300 ? Str::limit($para, 297) : $para;
            $significance = 'Indicates emerging changes in '.($matchedDimension?->name ?? 'operating environment').' that may alter organizational planning assumptions.';

            $candidates[] = [
                'title' => $candidateTitle,
                'summary' => $summary,
                'significance' => $significance,
                'evidence_quote' => trim($evidenceQuote),
                'dimension_id' => $matchedDimension?->id,
                'suggested_time_horizon_id' => $matchedHorizon?->id,
                'preliminary_impact' => $scores['impact'],
                'preliminary_uncertainty' => $scores['uncertainty'],
                'confidence_score' => $scores['confidence'],
                'confidence_rationale' => $scores['rationale'],
            ];
        }

        return $candidates;
    }

    /**
     * Detect best matching taxonomy dimension from keyword matches.
     */
    protected function detectDimension(string $text, Collection $dimensions): ?Dimension
    {
        $textLower = strtolower($text);

        $dimensionKeywords = [
            'POLITICAL' => ['government', 'minister', 'election', 'geopolit', 'sanction', 'political', 'national agenda', 'energy security'],
            'ECONOMIC' => ['market', 'inflation', 'gdp', 'economic', 'capital', 'revenue', 'cost', 'trade', 'interest rate', 'finance', 'exchange rate'],
            'SOCIAL' => ['society', 'social', 'demographic', 'generation', 'gen z', 'millennial', 'lifestyle', 'culture', 'urban'],
            'TECHNOLOGY' => ['tech', 'ai', 'algorithm', 'software', 'digital', 'cyber', 'quantum', 'cloud', 'compute', 'data'],
            'ECOLOGY' => ['climate', 'carbon', 'emission', 'green', 'environment', 'sustainable', 'waste', 'energy', 'biodiversity'],
            'LEGISLATIVE' => ['law', 'regulat', 'directive', 'act', 'legal', 'compliance', 'standard', 'decree', 'tkdn'],
            'CONSUMER' => ['consumer', 'customer', 'demand', 'preference', 'behavior', 'user', 'habit', 'adoption', 'purchasing power'],
            'SUPPLY_CHAIN' => ['supply chain', 'logistics', 'vendor', 'supplier', 'raw material', 'shipping', 'manufacturing', 'shortage'],
            'COMPETITOR' => ['rival', 'compet', 'patent', 'acquisition', 'market share', 'price war'],
            'NEW_ENTRANT' => ['new entrant', 'newcomer', 'market entry', 'new player', 'entering the market', 'enters the market', 'startup'],
            'SUBSTITUTE' => ['alternative', 'replacement', 'substitute', 'transition from', 'leapfrog', 'car sharing', 'ride-hailing'],
        ];

        $highestCount = 0;
        $bestMatch = null;

        foreach ($dimensions as $dimension) {
            // Keyed by code, so a renamed default keeps its keywords; a custom dimension matches its own name.
            $keywords = $dimensionKeywords[$dimension->code ?? ''] ?? [strtolower($dimension->name)];
            $count = 0;
            foreach ($keywords as $kw) {
                if (str_contains($textLower, $kw)) {
                    $count++;
                }
            }

            if ($count > $highestCount) {
                $highestCount = $count;
                $bestMatch = $dimension;
            }
        }

        return $bestMatch ?? $dimensions->first();
    }

    /**
     * Detect time horizon from temporal cues in text.
     */
    protected function detectTimeHorizon(string $text, Collection $timeHorizons): ?TimeHorizon
    {
        $textLower = strtolower($text);

        if (preg_match('/\b(202[4-6]|months|this year|imminent|immediate)\b/', $textLower)) {
            return $this->findTimeHorizon($timeHorizons, TimeHorizonType::SHORT_TERM) ?? $timeHorizons->first();
        }

        if (preg_match('/\b(203[0-9]|decade|transformational|future)\b/', $textLower)) {
            return $this->findTimeHorizon($timeHorizons, TimeHorizonType::LONG_TERM) ?? $timeHorizons->last();
        }

        return $this->findTimeHorizon($timeHorizons, TimeHorizonType::MID_TERM) ?? $timeHorizons->first();
    }

    protected function findTimeHorizon(Collection $timeHorizons, TimeHorizonType $type): ?TimeHorizon
    {
        return $timeHorizons->first(fn ($timeHorizon) => TimeHorizonType::fromModel($timeHorizon) === $type);
    }

    /**
     * Estimate heuristic scores and confidence.
     */
    protected function estimateScores(string $text): array
    {
        $textLower = strtolower($text);

        // Impact estimation based on high-impact vocabulary
        $impact = 5;
        if (preg_match('/\b(critical|breakthrough|crisis|transform|existential|severe|massive)\b/', $textLower)) {
            $impact = 8;
        } elseif (preg_match('/\b(minor|gradual|modest|incremental|slight)\b/', $textLower)) {
            $impact = 3;
        }

        // Uncertainty estimation based on variance vocabulary
        $uncertainty = 5;
        if (preg_match('/\b(unclear|unpredictable|volatile|unproven|speculative|unknown)\b/', $textLower)) {
            $uncertainty = 8;
        } elseif (preg_match('/\b(enacted|mandated|confirmed|certain|proven|standardized)\b/', $textLower)) {
            $uncertainty = 2;
        }

        // Confidence estimation
        $hasQuoteEvidence = strlen($text) > 80;
        $confidence = $hasQuoteEvidence ? 0.88 : 0.72;
        $rationale = 'Skor berbasis kata kunci (tanpa AI): kosakata dampak dan ketidakpastian di paragraf; keyakinan dari panjang kutipan.';

        return [
            'impact' => $impact,
            'uncertainty' => $uncertainty,
            'confidence' => $confidence,
            'rationale' => $rationale,
        ];
    }
}
