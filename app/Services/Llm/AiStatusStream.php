<?php

namespace App\Services\Llm;

use App\Models\ScenarioPlanningProject;
use BackedEnum;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Server-Sent Events for the AI work of one exercise (2026-11-16): the nine assists
 * (`{kind}_suggestion_status`) and the narrative generation of its framework.
 *
 * A stream holds a PHP-FPM worker while it is open. To keep the app responsive it is:
 * - short: it ends after `ops.sse.max_seconds` and the browser's EventSource reconnects by itself;
 * - capped: at most `ops.sse.max_streams` at once, through cache locks that expire on their own, so a
 *   killed worker never leaks a slot. A request that finds no free slot gets 503 and the page polls.
 */
class AiStatusStream
{
    /** A free stream slot, or null when every slot is taken. */
    public function acquireSlot(): ?Lock
    {
        $max = max(1, (int) config('ops.sse.max_streams', 8));
        $ttl = (int) config('ops.sse.max_seconds', 25) + 10;
        for ($slot = 0; $slot < $max; $slot++) {
            $lock = Cache::lock("ai-sse-slot:{$slot}", $ttl);
            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    /**
     * The current status of every AI job of the exercise, read fresh from the database.
     *
     * @return array<string, string|null>
     */
    public function snapshot(ScenarioPlanningProject $project): array
    {
        $columns = array_map(fn (string $kind) => "{$kind}_suggestion_status", array_keys(SuggestionRecovery::KINDS));
        $row = ScenarioPlanningProject::query()->whereKey($project->getKey())->first($columns);

        $statuses = [];
        foreach (array_keys(SuggestionRecovery::KINDS) as $kind) {
            $statuses[$kind] = $this->value($row?->getAttribute("{$kind}_suggestion_status"));
        }
        $statuses['narrative'] = $this->value($project->framework()->value('generation_status'));

        return $statuses;
    }

    /**
     * Writes the stream: a reconnect hint, a `status` event at the start and whenever a status changes,
     * a comment every ~10 s against idle-connection timeouts, and an `end` event when time is up.
     *
     * @param  Closure(string): void  $write  sends a chunk to the client (and flushes it)
     */
    public function run(ScenarioPlanningProject $project, Closure $write): void
    {
        $deadline = microtime(true) + (int) config('ops.sse.max_seconds', 25);
        $interval = max(10, (int) config('ops.sse.interval_ms', 1000)) * 1000;
        $lastPing = microtime(true);
        $last = null;

        $write("retry: 1500\n\n");
        while (true) {
            $now = $this->snapshot($project);
            if ($now !== $last) {
                $write('event: status'."\n".'data: '.json_encode($now)."\n\n");
                $last = $now;
            }
            if (connection_aborted() || microtime(true) >= $deadline) {
                break;
            }
            if (microtime(true) - $lastPing >= 10) {
                $write(": ping\n\n");
                $lastPing = microtime(true);
            }
            usleep($interval);
        }
        $write("event: end\ndata: {}\n\n");
    }

    private function value(mixed $status): ?string
    {
        return $status instanceof BackedEnum ? (string) $status->value : ($status === null ? null : (string) $status);
    }
}
