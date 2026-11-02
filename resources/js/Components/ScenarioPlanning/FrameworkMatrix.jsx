import { PencilIcon } from "lucide-react";

import { Button } from "@/Components/ui/button";

/** Grid order: axis B high on top, axis A high on the right. */
const LAYOUT = ["LH", "HH", "LL", "HL"];

/**
 * The 2x2 framework: axis A runs left (low) to right (high), axis B bottom (low) to top (high).
 * Each quadrant shows its poles, name and summary; `onEdit` is passed only while editable.
 */
export default function FrameworkMatrix({ axes, quadrants, onEdit }) {
    const [axisA, axisB] = axes;
    const byPosition = Object.fromEntries(quadrants.map((quadrant) => [quadrant.position, quadrant]));

    return (
        <figure className="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-2" aria-label={`Kerangka skenario: ${axisA.name} × ${axisB.name}`}>
            <div className="flex flex-col items-center justify-between py-1 text-[11px] text-muted-foreground">
                <span>{axisB.high}</span>
                <span className="font-semibold text-foreground [writing-mode:vertical-rl] rotate-180">B · {axisB.name}</span>
                <span>{axisB.low}</span>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {LAYOUT.map((position) => {
                    const quadrant = byPosition[position];
                    if (!quadrant) return null;
                    return (
                        <article key={position} className="flex min-h-36 flex-col gap-2 rounded-lg border border-border bg-muted/30 p-3">
                            <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                <span className="rounded bg-card px-1.5 py-0.5">A: {quadrant.poles[0]}</span>
                                <span className="rounded bg-card px-1.5 py-0.5">B: {quadrant.poles[1]}</span>
                            </div>
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="m-0 text-sm font-semibold text-foreground">{quadrant.title}</h3>
                                {onEdit && (
                                    <Button size="icon-sm" variant="ghost" aria-label={`Ubah ${quadrant.title}`} onClick={() => onEdit(quadrant)}>
                                        <PencilIcon aria-hidden="true" />
                                    </Button>
                                )}
                            </div>
                            <p className="m-0 text-xs text-muted-foreground">{quadrant.summary || "Belum ada ringkasan."}</p>
                        </article>
                    );
                })}
            </div>
            <span aria-hidden="true" />
            <figcaption className="flex items-center justify-between text-[11px] text-muted-foreground">
                <span>{axisA.low}</span>
                <span className="font-semibold text-foreground">A · {axisA.name}</span>
                <span>{axisA.high}</span>
            </figcaption>
        </figure>
    );
}
