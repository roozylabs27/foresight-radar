<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Enums\TimeHorizonType;
use App\Jobs\ExtractSignals;
use App\Models\Dimension;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Extraction\LlmSignalExtractor;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\StructuredLlmClient;
use App\Support\EvidenceVerifier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class SignalExtractionService
{
    public const PROVIDER_HEURISTIC = 'heuristic';

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
     * Save the source and queue its extraction. The LLM call runs in the
     * ExtractSignals job, so the request returns before the model answers.
     */
    public function ingest(array $data, int $userId): Source
    {
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
     * Extract candidate signals from a saved source and mark it completed.
     *
     * The LLM is called before the transaction opens, so a slow call holds no
     * database locks. When it fails, extraction falls back to keyword rules and
     * the stored `notice` says why; when it succeeds with no verifiable signal,
     * the source is completed without signals.
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
        $isLlmAvailable = config('services.signal_extraction.driver') !== self::PROVIDER_HEURISTIC && $llm->isAvailable();
        $mode = $isLlmAvailable ? $llm->provider() : self::PROVIDER_HEURISTIC;
        $notice = null;
        $candidates = null;
        $meta = [];

        if ($mode !== self::PROVIDER_HEURISTIC) {
            try {
                $extractor = $userLlm ? new LlmSignalExtractor($userLlm) : ($this->llmExtractor ?? $this->llmExtractor());
                $result = $extractor->extract($title, $content);
                $candidates = array_map(fn ($item) => $item + [
                    'extraction_provider' => $result['meta']['provider'],
                    'extraction_model' => $result['meta']['model'],
                    'prompt_version' => $result['meta']['prompt_version'],
                ], $result['candidates']);
                $meta = $result['meta'] + ['label' => $llm->label()];
            } catch (LlmException $e) {
                Log::warning('Signal extraction fell back to keyword rules', ['reason' => $e->reason]);
                $notice = $e->userMessage().' Sinyal diekstrak dengan aturan kata kunci (tanpa AI).';
                $meta = ['provider' => self::PROVIDER_HEURISTIC, 'fallback_reason' => $e->reason];
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
                    'prompt_version', 'quote_verified', 'suggested_driving_force_id',
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

        $by = $ai ? "oleh {$label} (".($meta['model'] ?? '-').')' : 'dengan aturan kata kunci (tanpa AI)';
        $message = "Sumber disimpan. {$count} sinyal kandidat diekstrak {$by}.";
        if ($discarded > 0) {
            $message .= " {$discarded} usulan dibuang karena kutipannya tidak ditemukan verbatim di dokumen.";
        }
        if ($ai && $count === 0) {
            $message .= ' Tidak ada sinyal dengan bukti terverifikasi di dokumen ini.';
        }

        return array_merge($progress, [
            'message' => $message,
            'extraction' => [
                'mode' => $meta['provider'] ?? self::PROVIDER_HEURISTIC,
                'label' => $label,
                'model' => $ai ? ($meta['model'] ?? null) : null,
                'fallback' => isset($meta['fallback_reason']),
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
        $dimensions = Dimension::all();
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
            'Technology' => ['tech', 'ai', 'algorithm', 'software', 'digital', 'cyber', 'quantum', 'cloud', 'compute', 'data'],
            'Economy' => ['market', 'inflation', 'gdp', 'economic', 'capital', 'revenue', 'cost', 'trade', 'interest rate', 'finance'],
            'Regulation' => ['law', 'policy', 'compliance', 'regulat', 'directive', 'act', 'legal', 'government', 'standard'],
            'Ecology' => ['climate', 'carbon', 'emission', 'green', 'environment', 'sustainable', 'waste', 'energy', 'biodiversity'],
            'Customer' => ['consumer', 'demand', 'preference', 'behavior', 'demographic', 'user', 'habit', 'adoption'],
            'Competitor' => ['rival', 'compet', 'patent', 'startup', 'disrupt', 'acquisition', 'market share'],
            'Supplier' => ['supply chain', 'logistics', 'vendor', 'raw material', 'shipping', 'manufacturing', 'shortage'],
            'Substitute' => ['alternative', 'replacement', 'substitute', 'transition from', 'leapfrog'],
        ];

        $highestCount = 0;
        $bestMatch = null;

        foreach ($dimensions as $dimension) {
            $keywords = $dimensionKeywords[$dimension->name] ?? [strtolower($dimension->name)];
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
