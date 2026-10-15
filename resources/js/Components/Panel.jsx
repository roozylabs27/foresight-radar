import { useId } from "react";

import { cn } from "@/lib/utils";

/** Dashboard card: icon, title, optional badge and description, then content. */
export default function Panel({ icon, title, badge, description, className, children }) {
    const headingId = useId();

    return (
        <section
            aria-labelledby={headingId}
            className={cn(
                "flex min-w-0 flex-col gap-3 rounded-lg border border-solid border-border bg-card p-4 text-card-foreground lg:p-5",
                className
            )}
        >
            <div className="flex flex-col gap-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 id={headingId} className="m-0 flex items-center gap-2 text-sm font-semibold text-foreground">
                        {icon && (
                            <span aria-hidden="true" className="text-base">
                                {icon}
                            </span>
                        )}
                        {title}
                    </h3>
                    {badge}
                </div>
                {description && <p className="m-0 text-xs text-muted-foreground">{description}</p>}
            </div>
            {children}
        </section>
    );
}
