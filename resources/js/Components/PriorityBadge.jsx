import { cva } from "class-variance-authority";

import { cn } from "@/lib/utils";

const priorityBadge = cva(
    "inline-flex h-6 w-fit shrink-0 items-center rounded-sm px-2 text-xs font-semibold whitespace-nowrap",
    {
        variants: {
            priority: {
                high: "bg-danger-soft text-danger",
                medium: "bg-warning-soft text-warning",
                low: "bg-success-soft text-success",
                unknown: "bg-muted text-muted-foreground",
            },
        },
        defaultVariants: { priority: "unknown" },
    }
);

const LABELS = { high: "High", medium: "Medium", low: "Low" };

/** Priority is always spelled out, never shown as color alone. */
export default function PriorityBadge({ priority, className }) {
    const key = String(priority ?? "").trim().toLowerCase();
    const known = key in LABELS;

    return (
        <span
            data-slot="priority-badge"
            className={cn(priorityBadge({ priority: known ? key : "unknown" }), className)}
        >
            {known ? `Priority ${LABELS[key].toLowerCase()}` : priority || "Tanpa prioritas"}
        </span>
    );
}

export { priorityBadge };
