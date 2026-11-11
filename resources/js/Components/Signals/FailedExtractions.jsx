import { AlertCircleIcon, RotateCwIcon } from "lucide-react";

import { Button } from "@/Components/ui/button";

/**
 * Sources whose AI extraction failed (no provider, timeout, provider error). They keep no signals;
 * `onRetry(source)` queues the extraction again through AI. `busyId` is the source being retried.
 */
export default function FailedExtractions({ sources, busyId, onRetry }) {
    if (sources.length === 0) return null;

    return (
        <section aria-label="Ekstraksi yang gagal" className="rounded-lg border border-solid border-danger/30 bg-card p-4">
            <ul className="m-0 flex list-none flex-col gap-3 p-0">
                {sources.map((source) => (
                    <li key={source.id} className="flex flex-wrap items-start gap-3">
                        <AlertCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-danger" />
                        <p className="m-0 min-w-0 flex-1 text-[13px] text-muted-foreground">
                            <b className="font-semibold text-foreground">{source.title}</b>: ekstraksi gagal. {source.error}
                        </p>
                        <Button size="sm" variant="outline" disabled={busyId === source.id} aria-busy={busyId === source.id || undefined} onClick={() => onRetry(source)}>
                            <RotateCwIcon aria-hidden="true" />
                            Coba lagi
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
