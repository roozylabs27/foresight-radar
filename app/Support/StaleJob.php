<?php

namespace App\Support;

use App\Enums\LlmJobStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When a row's queued job counts as lost. A QUEUED row whose job no worker picked up, or a
 * PROCESSING row whose worker died, would otherwise stay pending forever and block a retry.
 *
 * - QUEUED is stale after `queue.stale_queued_after` seconds (default 15 minutes).
 * - PROCESSING is stale only after the job's own timeout plus a grace period, so a job that is
 *   still allowed to run is never reported failed.
 */
final class StaleJob
{
    public const GRACE_SECONDS = 60;

    public static function window(LlmJobStatus $status, int $jobTimeout): ?int
    {
        return match ($status) {
            LlmJobStatus::QUEUED => (int) config('queue.stale_queued_after', 900),
            LlmJobStatus::PROCESSING => $jobTimeout + self::GRACE_SECONDS,
            default => null,
        };
    }

    public static function isStale(?LlmJobStatus $status, ?CarbonInterface $since, int $jobTimeout): bool
    {
        $window = $status ? self::window($status, $jobTimeout) : null;

        return $window !== null && $since !== null && $since->diffInSeconds(Carbon::now()) >= $window;
    }

    /** Rows of this status last touched before the cut-off are stale. */
    public static function cutoff(LlmJobStatus $status, int $jobTimeout): ?Carbon
    {
        $window = self::window($status, $jobTimeout);

        return $window === null ? null : Carbon::now()->subSeconds($window);
    }

    /** How long a job's unique lock may live: past the longest time it can legitimately wait and run. */
    public static function lockSeconds(int $jobTimeout): int
    {
        return (int) config('queue.stale_queued_after', 900) + $jobTimeout + self::GRACE_SECONDS;
    }
}
