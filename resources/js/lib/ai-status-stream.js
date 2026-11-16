/**
 * Server-Sent Events with the AI job statuses of one exercise (AiStatusStreamController, 2026-11-16).
 *
 * `watchAiStatus(projectId, onStatus, onUnavailable)` opens an EventSource and calls
 * `onStatus(statuses)` for every `status` event. The server ends each stream after ~25 s and
 * EventSource reconnects by itself, so a long AI job is followed across several short streams.
 * When the stream cannot be used (no EventSource, 503 because every slot is taken, or the
 * connection is closed for good) `onUnavailable()` is called once and the caller falls back to
 * polling. Returns a function that closes the stream.
 */
export function watchAiStatus(projectId, onStatus, onUnavailable) {
    if (typeof window === "undefined" || typeof window.EventSource === "undefined" || !projectId) {
        onUnavailable();
        return () => {};
    }

    let gaveUp = false;
    const source = new window.EventSource(route("scenario-planning.ai-status.stream", projectId));

    source.addEventListener("status", (event) => {
        try {
            onStatus(JSON.parse(event.data));
        } catch {
            // A malformed event is ignored; the next one carries the full state again.
        }
    });
    source.addEventListener("error", () => {
        // CONNECTING means EventSource retries by itself (the normal end of a short stream).
        // CLOSED means it gave up (503, 4xx, wrong content type): fall back to polling.
        if (source.readyState === window.EventSource.CLOSED && !gaveUp) {
            gaveUp = true;
            onUnavailable();
        }
    });

    return () => source.close();
}
