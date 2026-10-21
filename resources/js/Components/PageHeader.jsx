import { cn } from "@/lib/utils";

/**
 * Page title block for the top of a page's content.
 * `as="h2"` when the layout header already holds the page's h1.
 */
export default function PageHeader({
    title,
    description,
    icon,
    badge,
    actions,
    as: Heading = "h1",
    className,
}) {
    return (
        <div className={cn("flex flex-wrap items-start justify-between gap-x-6 gap-y-3", className)}>
            <div className="flex min-w-0 items-start gap-3">
                {icon && (
                    <span
                        aria-hidden="true"
                        className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md bg-accent text-lg text-primary"
                    >
                        {icon}
                    </span>
                )}
                <div className="flex min-w-0 flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Heading className="m-0 text-xl leading-7 font-bold tracking-[-0.01em] text-foreground">
                            {title}
                        </Heading>
                        {badge}
                    </div>
                    {description && <p className="m-0 text-sm text-muted-foreground">{description}</p>}
                </div>
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
