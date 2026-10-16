<?php

namespace App\Services;

use App\Models\Scenario;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ScenarioNarrativePrompt;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Generates the four quadrants of a scenario. With an LLM it writes cited
 * narratives from the accepted signals; without one (or when the call fails)
 * it lays out a template skeleton for analysts to fill in, labelled as such.
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
    ) {
    }

    /**
     * @return array{scenario: Scenario, provider: string, notice: ?string}
     *
     * @throws ConflictHttpException when edited quadrants would be overwritten
     * @throws ValidationException when there is no evidence for the LLM, or its citations are invalid
     */
    public function generate(Scenario $scenario, bool $overwriteEdited, ?int $userId): array
    {
        $scenario->loadMissing(['axis_a.dimension', 'axis_b.dimension', 'quadrants']);

        $edited = $scenario->quadrants->filter->isEdited()->count();
        if ($edited > 0 && !$overwriteEdited) {
            throw new ConflictHttpException("{$edited} kuadran sudah disunting analis. Konfirmasi untuk menimpanya.");
        }

        $evidence = $this->scenarios->evidenceFor($scenario);
        $notice = null;
        $quadrants = null;
        $model = null;

        if ($this->llm->isAvailable()) {
            foreach (['A' => $scenario->axis_a, 'B' => $scenario->axis_b] as $axis => $force) {
                if (!collect($evidence)->contains(fn ($item) => str_contains($item['axis'], $axis))) {
                    throw ValidationException::withMessages([
                        'evidence' => "Sumbu {$axis} ({$force->keyword}) belum punya sinyal ACCEPTED yang tertaut. Tautkan sinyal dulu sebelum membuat narasi AI.",
                    ]);
                }
            }

            try {
                $response = $this->llm->generateJson(ScenarioNarrativePrompt::request(
                    $scenario,
                    $evidence,
                    (string) config('services.anthropic.narrative_effort', 'medium'),
                ));
                $quadrants = $this->validated($response->data, array_column($evidence, 'ref'));
                $model = $response->model;
            } catch (LlmException $e) {
                Log::warning('Scenario narrative fell back to the template', ['reason' => $e->reason]);
                $notice = $e->userMessage() . ' Ditampilkan kerangka tanpa AI.';
            }
        }

        $provider = $quadrants === null ? self::PROVIDER_TEMPLATE : 'claude';
        $quadrants ??= $this->template($scenario, $evidence);

        $signalIds = collect($evidence)->mapWithKeys(fn ($item) => [$item['ref'] => $item['signal']->id]);

        DB::transaction(function () use ($scenario, $quadrants, $evidence, $signalIds, $provider, $model, $userId) {
            $scenario->quadrants()->delete();
            foreach ($quadrants as $quadrant) {
                $scenario->quadrants()->create(collect($quadrant)->except('cited_refs')->all() + [
                    'cited_signal_ids' => array_values(array_map(fn ($ref) => $signalIds[$ref], $quadrant['cited_refs'])),
                    'is_ai_generated' => $provider === 'claude',
                    'provider' => $provider,
                ]);
            }
            $scenario->update([
                'provider' => $provider,
                'model' => $model,
                'prompt_version' => $provider === 'claude' ? ScenarioNarrativePrompt::VERSION : null,
                'input_signal_ids' => array_map(fn ($item) => $item['signal']->id, $evidence),
                'generated_at' => now(),
                'updated_by' => $userId,
            ]);
        });

        return ['scenario' => $scenario->fresh('quadrants'), 'provider' => $provider, 'notice' => $notice];
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
            ];
        }

        if ($uncited !== []) {
            throw ValidationException::withMessages([
                'citations' => 'Narasi AI ditolak karena kuadran ' . implode(', ', $uncited) . ' tidak mengutip bukti yang valid. Tidak ada yang disimpan; coba buat ulang.',
            ]);
        }

        return $quadrants;
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
            $evidenceLine = $cited === []
                ? 'Belum ada sinyal ACCEPTED yang tertaut ke sumbu ini; tautkan sinyal agar narasi bisa didasarkan pada bukti.'
                : 'Bukti awal: ' . implode('; ', array_map(fn ($item) => "[{$item['ref']}] {$item['signal']->title}", $cited)) . '.';

            return [
                'position' => $position,
                'title' => "{$poleA} · {$poleB}",
                'narrative' => "Kerangka tanpa AI. Bayangkan masa depan ketika {$scenario->axis_a->keyword} berada di kutub \"{$poleA}\" "
                    . "dan {$scenario->axis_b->keyword} di kutub \"{$poleB}\". {$evidenceLine} "
                    . 'Lengkapi narasi ini bersama tim: apa yang berubah bagi pelanggan, pemasok, dan organisasi?',
                'implications' => 'Belum diisi. Tuliskan apa arti masa depan ini bagi strategi organisasi.',
                'early_indicators' => [
                    "Perkembangan {$scenario->axis_a->keyword} menuju \"{$poleA}\"",
                    "Perkembangan {$scenario->axis_b->keyword} menuju \"{$poleB}\"",
                ],
                'cited_refs' => $refs,
            ];
        }, Scenario::POSITIONS);
    }
}
