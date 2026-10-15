import { cn } from "@/lib/utils";

/** Read-only summary of a record, e.g. the driving force a dialog acts on. */
export default function DetailList({ className, children }) {
    return (
        <dl className={cn("m-0 grid gap-4 rounded-lg border border-solid border-border bg-muted/40 p-4", className)}>
            {children}
        </dl>
    );
}

export function DetailItem({ label, className, children }) {
    return (
        <div className={cn("flex min-w-0 flex-col gap-1", className)}>
            <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
            <dd className="m-0 text-sm text-foreground">{children ?? "-"}</dd>
        </div>
    );
}
