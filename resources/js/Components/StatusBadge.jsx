import { cva } from "class-variance-authority";

import { cn } from "@/lib/utils";

const statusBadge = cva(
    "inline-flex h-6 w-fit shrink-0 items-center gap-1.5 rounded-sm px-2 text-xs font-semibold whitespace-nowrap",
    {
        variants: {
            tone: {
                pending: "bg-warning-soft text-warning",
                approved: "bg-success-soft text-success",
                rejected: "bg-danger-soft text-danger",
                closed: "bg-muted text-muted-foreground",
                neutral: "bg-muted text-muted-foreground",
            },
        },
        defaultVariants: { tone: "neutral" },
    }
);

/** Matches App\Enums\DrivingForceStatus; values arrive upper-case from the API. */
const STATUSES = {
    pending: { tone: "pending", label: "Pending" },
    approved: { tone: "approved", label: "Approved" },
    rejected: { tone: "rejected", label: "Rejected" },
    closed: { tone: "closed", label: "Closed" },
};

export default function StatusBadge({ status, label, className }) {
    const config = STATUSES[String(status ?? "").trim().toLowerCase()];

    return (
        <span
            data-slot="status-badge"
            className={cn(statusBadge({ tone: config?.tone ?? "neutral" }), className)}
        >
            <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />
            {label ?? config?.label ?? status ?? "-"}
        </span>
    );
}

export { statusBadge };
