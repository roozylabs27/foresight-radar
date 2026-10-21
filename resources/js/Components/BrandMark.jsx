import { cn } from "@/lib/utils";

/** Radar logo tile from the design system: scan-colored rings on a night tile. */
export default function BrandMark({ className, iconClassName }) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                "flex size-9 shrink-0 items-center justify-center rounded-md bg-night text-scan dark:ring-1 dark:ring-border",
                className
            )}
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                className={cn("size-5", iconClassName)}
            >
                <circle cx="12" cy="12" r="9" />
                <circle cx="12" cy="12" r="5" />
                <path d="M12 12 L18.4 5.6" />
                <circle cx="12" cy="12" r="1" fill="currentColor" />
            </svg>
        </span>
    );
}
