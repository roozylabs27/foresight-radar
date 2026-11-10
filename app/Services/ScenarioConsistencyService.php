<?php

namespace App\Services;

use App\Models\Scenario;
use App\Models\ScenarioQuadrant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Roadmap R2.8 slice 2 (matrix 5.6): rule checks per quadrant and the team's consistency sign-off.
 * A quadrant marked consistent while checks still warn needs a note that says why; "issues" always
 * needs one.
 */
class ScenarioConsistencyService
{
    public const STATUSES = ['consistent', 'issues'];

    public function __construct(protected ScenarioConditionService $conditions) {}

    /**
     * Warnings per quadrant position, in a fixed order: too few accepted conditions, an axis
     * without a pole condition, drafts awaiting review, conditions alike another quadrant's.
     *
     * @return array<string, array<int, array{code: string, message: string}>>
     */
    public function checks(Scenario $framework): array
    {
        $checks = [];
        foreach ($this->conditions->quadrants($framework) as $quadrant) {
            $accepted = collect($quadrant['conditions'])->where('review_status', 'accepted');
            $rows = [];
            if ($accepted->count() < ScenarioConditionService::MIN_PER_QUADRANT) {
                $rows[] = ['code' => 'too_few', 'message' => "Baru {$accepted->count()} kondisi diterima; minimal ".ScenarioConditionService::MIN_PER_QUADRANT.'.'];
            }
            foreach (['A', 'B'] as $index => $axis) {
                if (! $accepted->contains(fn ($row) => $row['kind'] === 'pole_expression' && $row['axis'] === $axis)) {
                    $name = $framework->axisName($axis) ?? "sumbu {$axis}";
                    $rows[] = ['code' => 'missing_pole', 'message' => "Belum ada kondisi yang mengekspresikan kutub \"{$quadrant['poles'][$index]}\" ({$name})."];
                }
            }
            $pending = collect($quadrant['conditions'])->where('review_status', 'proposed')->count();
            if ($pending > 0) {
                $rows[] = ['code' => 'pending', 'message' => "{$pending} draf kondisi belum ditinjau."];
            }
            $similar = $accepted->flatMap(fn ($row) => $row['similar_to'])->unique()->sort()->values();
            if ($similar->isNotEmpty()) {
                $rows[] = ['code' => 'similar', 'message' => 'Ada kondisi yang mirip dengan kuadran '.$similar->join(', ').'.'];
            }
            $checks[$quadrant['position']] = $rows;
        }

        return $checks;
    }

    /** @param array{status: string, note?: string|null} $validated */
    public function signOff(ScenarioQuadrant $quadrant, array $validated, User $user): void
    {
        $note = trim((string) ($validated['note'] ?? ''));
        $warnings = $this->checks($quadrant->scenario)[$quadrant->position] ?? [];
        if ($note === '' && ($validated['status'] === 'issues' || $warnings !== [])) {
            throw ValidationException::withMessages(['note' => $validated['status'] === 'issues'
                ? 'Tuliskan apa yang belum konsisten.'
                : 'Masih ada peringatan; tuliskan alasan kuadran ini tetap dianggap konsisten.']);
        }

        $quadrant->forceFill([
            'consistency_status' => $validated['status'],
            'consistency_note' => $note === '' ? null : $note,
            'consistency_reviewed_by' => $user->id,
            'consistency_reviewed_at' => now(),
        ])->save();
    }

    /** An accepted condition changed: the sign-off no longer covers the quadrant. */
    public function conditionsChanged(int $quadrantId): void
    {
        ScenarioQuadrant::whereKey($quadrantId)->whereNotNull('consistency_status')->update(['consistency_status' => null]);
    }
}
