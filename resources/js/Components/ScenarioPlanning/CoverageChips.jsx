import { groupCoverage } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

/**
 * Included forces per method category; empty categories are highlighted so the
 * team can check nothing was overlooked (slide 16: macro and mezzo environment).
 */
export default function CoverageChips({ coverage }) {
    const groups = groupCoverage(coverage);

    return (
        <div className="flex flex-col gap-3">
            {Object.entries(groups).map(([environment, rows]) => (
                <div key={environment} className="flex flex-col gap-1.5">
                    <span className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">{environment}</span>
                    <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
                        {rows.map((row) => (
                            <li
                                key={row.code ?? row.name}
                                className={cn(
                                    "inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs",
                                    row.included > 0 ? "border-border bg-card text-foreground" : "border-dashed border-warning/60 bg-warning-soft text-warning",
                                )}
                            >
                                {row.name}
                                <span className="font-semibold">{row.included}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}
