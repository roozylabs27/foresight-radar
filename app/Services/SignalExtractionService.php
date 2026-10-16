<?php

namespace App\Services;

use App\Enums\TimeHorizonType;
use App\Services\Extraction\LlmSignalExtractor;
use App\Services\Llm\LlmException;
use App\Services\Llm\StructuredLlmClient;
use App\Support\EvidenceVerifier;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    ) {
    }

    /**
     * "claude" when signals will be extracted by the LLM, otherwise "heuristic".
     */
    public function mode(): string
    {
        return config('services.signal_extraction.driver') === 'claude' && $this->llm()->isAvailable()
            ? 'claude'
            : self::PROVIDER_HEURISTIC;
    }

    public function modelName(): ?string
    {
        return $this->mode() === 'claude' ? $this->llm()->model() : null;
    }

    /**
     * Ingest source content and extract candidate signals.
     *
     * The LLM is called before the transaction opens, so a slow call holds no
     * database locks. When it fails, extraction falls back to keyword rules and
     * `notice` says why; when it succeeds with no verifiable signal, the source
     * is saved without signals.
     *
     * @return array{source: Source, signals: array, mode: string, model: ?string, fallback: bool, notice: ?string, meta: array}
     */
    public function ingestAndExtract(array $data, int $userId): array
    {
        $content = trim($data['content'] ?? '');
        $title = $data['title'] ?? 'Ingested Source Document';
        $mode = $this->mode();
        $notice = null;
        $fallback = false;
        $candidates = null;
        $meta = [];

        if ($mode === 'claude') {
            try {
                $result = $this->llmExtractor()->extract($title, $content);
                $candidates = array_map(fn ($item) => $item + [
                    'extraction_provider' => $result['meta']['provider'],
                    'extraction_model' => $result['meta']['model'],
                    'prompt_version' => $result['meta']['prompt_version'],
                ], $result['candidates']);
                $meta = $result['meta'];
            } catch (LlmException $e) {
                Log::warning('Signal extraction fell back to keyword rules', ['reason' => $e->reason]);
                $fallback = true;
                $notice = $e->userMessage() . ' Sinyal diekstrak dengan aturan kata kunci (tanpa AI).';
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
            ], $this->extractCandidatesFromText($content, $data['title'] ?? ''));
            $meta += ['provider' => self::PROVIDER_HEURISTIC, 'returned' => count($candidates)];
        }

        $saved = DB::transaction(function () use ($data, $userId, $content, $title, $candidates, $meta) {
            $source = Source::create([
                'uuid' => (string) Uuid::uuid4(),
                'title' => $title,
                'url' => $data['url'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'published_at' => !empty($data['published_at']) ? Carbon::parse($data['published_at']) : Carbon::today(),
                'raw_content' => $content,
                'content_hash' => hash('sha256', $content),
                'created_by' => $userId,
                'extraction_meta' => $meta,
            ]);

            $signals = [];
            foreach ($candidates as $item) {
                $signal = Signal::create([
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

                $signals[] = $signal->load(['dimension', 'suggested_time_horizon', 'suggested_driving_force']);
            }

            return ['source' => $source, 'signals' => $signals];
        });

        return $saved + [
            'mode' => $fallback ? self::PROVIDER_HEURISTIC : $mode,
            'model' => $fallback ? null : ($meta['model'] ?? null),
            'fallback' => $fallback,
            'notice' => $notice,
            'meta' => $meta,
        ];
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
                $evidenceQuote .= ' ' . $sentences[1];
            }

            $matchedDimension = $this->detectDimension($para, $dimensions);
            $matchedHorizon = $this->detectTimeHorizon($para, $timeHorizons);
            $scores = $this->estimateScores($para);

            // Title generation: extract leading phrase or synthesize from source title
            $paraWords = explode(' ', trim(preg_replace('/[^\w\s]/', '', $para)));
            $firstWords = implode(' ', array_slice($paraWords, 0, 6));
            $candidateTitle = count($selectedParagraphs) === 1
                ? ($sourceTitle ?: $firstWords)
                : ($firstWords . '...');

            $summary = strlen($para) > 300 ? substr($para, 0, 297) . '...' : $para;
            $significance = 'Indicates emerging changes in ' . ($matchedDimension?->name ?? 'operating environment') . ' that may alter organizational planning assumptions.';

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
