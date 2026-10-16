<?php

namespace App\Enums;

use App\Models\StatusAction;

/**
 * Status action of a rating, keyed by the `code` column of status_actions
 * (seeded in database/data/status_action.json).
 */
enum StatusActionType: string
{
    case MONITORING = '∆';
    case DECIDED_PLAN = 'O';

    /**
     * Resolve from a StatusAction row by its code, falling back to the name
     * for rows created before codes were seeded.
     */
    public static function fromModel(?StatusAction $statusAction): ?self
    {
        if ($statusAction === null) {
            return null;
        }

        return self::tryFrom((string) $statusAction->code)
            ?? match (strtolower(trim((string) $statusAction->name))) {
                'monitoring' => self::MONITORING,
                'decided plan' => self::DECIDED_PLAN,
                default => null,
            };
    }

    public function label(): string
    {
        return match ($this) {
            self::MONITORING => 'Monitoring',
            self::DECIDED_PLAN => 'Decided Plan',
        };
    }
}
