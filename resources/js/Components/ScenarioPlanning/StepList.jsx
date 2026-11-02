import { Link } from "@inertiajs/react";
import { ArrowUpRightIcon, CircleCheckIcon, CircleDotIcon, CircleIcon, LockIcon } from "lucide-react";

import { statusBadge } from "@/Components/StatusBadge";
import { STEP_PURPOSES, STEP_STATUSES } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

const ICONS = { done: CircleCheckIcon, in_progress: CircleDotIcon, available: CircleIcon, locked: LockIcon };

/**
 * The nine steps of the method with their status. A step links to the
 * exercise's own screen (`scoped`), or to the existing tenant-wide page that
 * covers it until it gets one.
 */
export default function StepList({ steps }) {
    return (
        <ol aria-label="Sembilan langkah scenario planning" className="m-0 flex list-none flex-col gap-2 p-0">
            {steps.map((step) => {
                const Icon = ICONS[step.status] ?? CircleIcon;
                const status = STEP_STATUSES[step.status] ?? STEP_STATUSES.locked;
                const linked = step.href && step.status !== "locked";

                return (
                    <li
                        key={step.key}
                        className={cn(
                            "flex items-start gap-3 rounded-lg border border-border p-3",
                            step.status === "locked" ? "bg-muted/30 text-muted-foreground" : "bg-card",
                        )}
                    >
                        <Icon aria-hidden="true" className={cn("mt-0.5 size-4 shrink-0", step.status === "done" ? "text-success" : step.status === "in_progress" ? "text-warning" : "text-muted-foreground")} />
                        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-foreground">
                                    {step.number}. {step.title}
                                </span>
                                <span className={statusBadge({ tone: status.tone })}>{status.label}</span>
                            </div>
                            <p className="m-0 text-xs text-muted-foreground">{STEP_PURPOSES[step.key]}</p>
                            {linked && (
                                <Link href={step.href} className="inline-flex w-fit items-center gap-1 text-xs font-medium text-primary hover:underline">
                                    {step.scoped ? "Buka langkah ini" : "Buka modul saat ini (belum per latihan)"}
                                    <ArrowUpRightIcon aria-hidden="true" className="size-3" />
                                </Link>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
