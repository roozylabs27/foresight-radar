<?php

namespace App\Http\Controllers;

use App\Models\ScenarioPlanningProject;
use App\Services\Llm\AiStatusStream;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events with the AI job statuses of one exercise (2026-11-16). Replaces the short
 * polling of the AI assists and the narrative generation; the page falls back to polling when the
 * stream is refused (503) or fails. Route binding scopes the exercise to the organisation.
 */
class AiStatusStreamController extends Controller
{
    public function stream(ScenarioPlanningProject $project, AiStatusStream $streams): StreamedResponse|JsonResponse
    {
        $slot = $streams->acquireSlot();
        if (! $slot) {
            return response()->json(['message' => 'Terlalu banyak koneksi status AI; halaman memakai pembaruan berkala.'], 503, ['Retry-After' => '30']);
        }

        return response()->stream(function () use ($project, $streams, $slot) {
            // No set_time_limit(): the stream ends itself after ops.sse.max_seconds, and the time a
            // worker spends in usleep() does not count towards max_execution_time. A limit set here
            // would also outlive the request in long-running processes (it broke the PHPUnit run).
            try {
                $streams->run($project, function (string $chunk) {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();
                });
            } finally {
                $slot->release();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            // nginx must pass events through at once instead of buffering the response.
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
