import { Link } from "@inertiajs/react";
import { CircleCheckIcon, CircleDotIcon, CircleIcon, LayoutListIcon, LockIcon } from "lucide-react";

import HintTooltip from "@/Components/HintTooltip";
import { STEP_STATUSES } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

const ICONS = { done: CircleCheckIcon, in_progress: CircleDotIcon, available: CircleIcon, locked: LockIcon };

/**
 * The nine steps of an exercise as a compact stepper under the page header (roadmap R1.11).
 * `current` is the step shown on the page; locked steps are not links.
 */
export default function ExerciseStepper({ project, steps, current }) {
    return (
        <nav aria-label={`Langkah latihan ${project.name}`} className="border-b border-border/50 bg-card px-4 py-2 lg:px-6">
            <ol className="m-0 flex list-none items-center gap-1 overflow-x-auto p-0">
                <li className="shrink-0">
                    <Link href={route("scenario-planning.show", project.id)} className="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground">
                        <LayoutListIcon aria-hidden="true" className="size-3.5" />
                        Ringkasan
                    </Link>
                </li>
                {steps.map((step) => {
                    const Icon = ICONS[step.status] ?? CircleIcon;
                    const active = step.number === current;
                    const content = (
                        <>
                            <Icon aria-hidden="true" className={cn("size-3.5 shrink-0", step.status === "done" && "text-success")} />
                            <span className="tabular-nums">{step.number}</span>
                            <span className={cn("max-w-28 truncate", !active && "hidden xl:inline")}>{step.title}</span>
                        </>
                    );
                    const className = cn(
                        "flex items-center gap-1.5 rounded-md px-2 py-1 text-xs whitespace-nowrap",
                        active ? "bg-sidebar-accent font-semibold text-sidebar-accent-foreground" : "text-muted-foreground",
                        step.status === "locked" ? "opacity-60" : !active && "hover:bg-muted hover:text-foreground"
                    );
                    const label = `${step.number}. ${step.title}: ${(STEP_STATUSES[step.status] ?? STEP_STATUSES.locked).label}`;

                    return (
                        <li key={step.key} className="shrink-0">
                            <HintTooltip content={label}>
                                {step.href && step.status !== "locked" ? (
                                    <Link href={step.href} aria-label={label} aria-current={active ? "step" : undefined} className={className}>
                                        {content}
                                    </Link>
                                ) : (
                                    <button type="button" aria-disabled={step.status === "locked" || undefined} aria-label={label} aria-current={active ? "step" : undefined} className={cn(className, "cursor-default")}>
                                        {content}
                                    </button>
                                )}
                            </HintTooltip>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
