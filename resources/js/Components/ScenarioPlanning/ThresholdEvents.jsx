import { TriangleAlertIcon } from "lucide-react";

const fmt = (value) => (value === null || value === undefined ? null : Number(value).toLocaleString("id-ID", { maximumFractionDigits: 6 }));

/**
 * Threshold crossings of one indicator, newest first (audit U4). A numeric crossing was decided by
 * the system; a manual one is a person's flag on a qualitative indicator. A crossing whose
 * observation was deleted stays listed as closed.
 */
export default function ThresholdEvents({ events }) {
    if (events.length === 0) return null;

    return (
        <div className="flex flex-col gap-1 rounded-md border border-danger/30 bg-danger-soft px-3 py-2 text-xs">
            <span className="flex items-center gap-1.5 font-semibold text-danger">
                <TriangleAlertIcon aria-hidden="true" className="size-3.5" />
                Ambang terlewati {events.length}×
            </span>
            <ul className="m-0 flex list-none flex-col gap-0.5 p-0 text-muted-foreground">
                {events.slice(0, 5).map((event) => (
                    <li key={event.id} className={event.closed ? "line-through" : undefined}>
                        {event.observed_at} · {event.kind === "numeric" ? `nilai ${fmt(event.observed_value)} (${event.rule})` : "ditandai manual"}
                        {event.closed && <span className="no-underline"> · observasi dihapus</span>}
                    </li>
                ))}
            </ul>
        </div>
    );
}
