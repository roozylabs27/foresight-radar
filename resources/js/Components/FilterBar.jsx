import { cn } from "@/lib/utils";

/** Card-style row of filter fields; wraps on narrow screens. `meta` sits on the right. */
export default function FilterBar({ label = "Filter", meta, actions, className, children }) {
    return (
        <section
            aria-label={label}
            className={cn(
                "flex w-full flex-wrap items-end gap-x-4 gap-y-3 rounded-lg border border-solid border-border bg-card p-4 text-sm text-card-foreground shadow-sm",
                className
            )}
        >
            {children}
            {(meta || actions) && (
                <div className="ml-auto flex flex-wrap items-center gap-3">
                    {meta && (
                        <span className="text-xs leading-[18px] font-medium text-subtle-foreground">{meta}</span>
                    )}
                    {actions}
                </div>
            )}
        </section>
    );
}

/** Label above a control. Pass `htmlFor` when the control has an id. */
export function FilterField({ label, htmlFor, className, children }) {
    const Label = htmlFor ? "label" : "span";

    return (
        <div className={cn("flex min-w-0 flex-col gap-1", className)}>
            <Label htmlFor={htmlFor} className="text-xs leading-[18px] font-medium text-muted-foreground">
                {label}
            </Label>
            {children}
        </div>
    );
}
