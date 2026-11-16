import { useEffect, useEffectEvent } from "react";
import { usePage } from "@inertiajs/react";

import { watchAiStatus } from "@/lib/ai-status-stream";

/**
 * While `active` (an AI job of this exercise is queued or running), follow the job through
 * Server-Sent Events and call `onChange(statuses)` when the server reports a status change, so the
 * page reloads only when there is something new. The first event of a page also triggers one call,
 * which covers a job that finished between the page render and the stream opening.
 * Falls back to calling `onChange(null)` every `fallbackMs` when the stream is unavailable.
 */
export function useAiStatusStream({ active, onChange, fallbackMs = 5000 }) {
    const projectId = usePage().props.project?.id;
    const changed = useEffectEvent((statuses) => onChange(statuses));

    useEffect(() => {
        if (!active) return undefined;
        let timer = null;
        let last = null;

        const close = watchAiStatus(
            projectId,
            (statuses) => {
                const key = JSON.stringify(statuses);
                if (key === last) return;
                last = key;
                changed(statuses);
            },
            () => {
                timer ??= setInterval(() => changed(null), fallbackMs);
            },
        );

        return () => {
            close();
            if (timer) clearInterval(timer);
        };
    }, [active, projectId, fallbackMs]);
}
