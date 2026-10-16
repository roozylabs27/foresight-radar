<?php

namespace App\Enums;

use App\Models\Priority;

/**
 * Priority of a rating. The priorities table has no code column, so rows are
 * matched on their name, case-insensitively.
 */
enum PriorityLevel: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    public static function fromModel(?Priority $priority): ?self
    {
        return $priority === null ? null : self::tryFrom(strtolower(trim((string) $priority->name)));
    }

    /**
     * Marker colour on the Foresight Radar.
     */
    public function radarColor(): string
    {
        return match ($this) {
            self::HIGH => '#ff4d4f',
            self::MEDIUM => '#faad14',
            self::LOW => '#52c41a',
        };
    }
}
