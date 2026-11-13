<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Product integrity roadmap #13: the reason a person gave for a gate or status decision in this
 * request. Gate controllers set it; GateDecisionObserver writes it with every decision the request
 * causes (including withdrawals that follow from it). Scoped, so it never leaks between requests.
 */
final class DecisionContext
{
    public const MAX = 1000;

    private ?string $rationale = null;

    /**
     * Read the optional `rationale` field of the request. With `$required` (a warning is being
     * overridden) a reason must be given.
     */
    public static function fromRequest(Request $request, bool $required = false): void
    {
        $rationale = $request->validate([
            'rationale' => [$required ? 'required' : 'nullable', 'string', 'max:'.self::MAX],
        ], [
            'rationale.required' => 'Tulis alasan karena peringatan ini diabaikan.',
        ])['rationale'] ?? null;

        app(self::class)->rationale = filled($rationale) ? trim($rationale) : null;
    }

    public function rationale(): ?string
    {
        return $this->rationale;
    }
}
