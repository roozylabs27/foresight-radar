<?php

namespace App\Services\Extraction;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\TimeHorizon;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\SignalExtractionPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\EvidenceVerifier;
use Illuminate\Support\Str;

/**
 * Asks the LLM for candidate signals and keeps only what can be trusted:
 * quotes found verbatim in the document, names mapped to real IDs, scores
 * clamped to the rubric. The model never sees or returns database IDs.
 */
class LlmSignalExtractor
{
    /** Approved driving forces offered to the model as F1..Fn. */
    public const MAX_FORCES = 80;

    public const MAX_CONFIDENCE = 0.95;

    public function __construct(protected StructuredLlmClient $llm) {}

    /**
     * @return array{candidates: array<int, array>, meta: array<string, mixed>}
     *
     * @throws LlmException
     */
    public function extract(string $title, string $content): array
    {
        $dimensions = Dimension::orderBy('id')->get(['id', 'name']);
        $horizons = TimeHorizon::orderBy('id')->get(['id', 'name']);
        $forces = DrivingForce::where('status', 'APPROVED')
            ->with('dimension:id,name')
            ->orderBy('id')
            ->limit(self::MAX_FORCES)
            ->get(['id', 'keyword', 'dimension_id']);

        $refs = [];
        $labels = [];
        foreach ($forces->values() as $position => $force) {
            $ref = 'F'.($position + 1);
            $refs[$ref] = $force->id;
            $labels[$ref] = $force->keyword.' ('.($force->dimension?->name ?? 'Tanpa dimensi').')';
        }

        $request = SignalExtractionPrompt::request(
            $title,
            $content,
            $dimensions,
            $horizons,
            $labels,
            (string) config('services.anthropic.effort', 'medium'),
        );
        $response = $this->llm->generateJson($request);

        $dimensionIds = $dimensions->mapWithKeys(fn ($dimension) => [Str::lower($dimension->name) => $dimension->id]);
        $horizonIds = $horizons->mapWithKeys(fn ($horizon) => [Str::lower($horizon->name) => $horizon->id]);
        $limit = max(1, (int) config('services.signal_extraction.max_candidates', 8));

        $returned = $response->data['signals'] ?? [];
        $returned = is_array($returned) ? $returned : [];
        $candidates = [];
        $seen = [];
        $discarded = 0;
        $duplicates = 0;

        foreach ($returned as $item) {
            $quote = is_array($item) ? EvidenceVerifier::normalize((string) ($item['evidence_quote'] ?? '')) : '';
            if (! EvidenceVerifier::verify($quote, $content)) {
                $discarded++;

                continue;
            }
            if (isset($seen[$quote])) {
                $duplicates++;

                continue;
            }
            $seen[$quote] = true;

            $ref = (string) ($item['related_force_ref'] ?? SignalExtractionPrompt::NO_FORCE);
            $candidates[] = [
                'title' => Str::limit(trim((string) ($item['title'] ?? '')) ?: 'Sinyal tanpa judul', 250, ''),
                'summary' => trim((string) ($item['summary'] ?? '')),
                'significance' => trim((string) ($item['significance'] ?? '')) ?: null,
                'evidence_quote' => $quote,
                'dimension_id' => $dimensionIds[Str::lower((string) ($item['dimension'] ?? ''))] ?? null,
                'suggested_time_horizon_id' => $horizonIds[Str::lower((string) ($item['time_horizon'] ?? ''))] ?? null,
                'preliminary_impact' => $this->score($item['impact'] ?? null),
                'preliminary_uncertainty' => $this->score($item['uncertainty'] ?? null),
                'confidence_score' => round(max(0.0, min(self::MAX_CONFIDENCE, (float) ($item['confidence'] ?? 0))), 2),
                'confidence_rationale' => trim((string) ($item['confidence_rationale'] ?? '')) ?: null,
                'suggested_driving_force_id' => $refs[$ref] ?? null,
                'quote_verified' => true,
            ];
        }

        $capped = max(0, count($candidates) - $limit);

        return [
            'candidates' => array_slice($candidates, 0, $limit),
            'meta' => [
                'provider' => $response->provider,
                'model' => $response->model,
                'prompt_version' => SignalExtractionPrompt::VERSION,
                'stop_reason' => $response->stopReason,
                'usage' => $response->usage,
                'duration_ms' => $response->durationMs,
                'returned' => count($returned),
                'discarded_unverified' => $discarded,
                'discarded_duplicate' => $duplicates,
                'discarded_over_limit' => $capped,
            ],
        ];
    }

    protected function score(mixed $value): ?int
    {
        return is_numeric($value) ? max(1, min(10, (int) round((float) $value))) : null;
    }
}
