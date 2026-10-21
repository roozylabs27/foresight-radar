import { cn } from "@/lib/utils";

/** The eight STEEP-style dimensions that have a `--dim-*` token in app.css. */
export const DIMENSION_KEYS = [
    "economy",
    "competitor",
    "customer",
    "supplier",
    "substitute",
    "technology",
    "regulation",
    "ecology",
];

/** Maps a dimension name from the API ("Technology", "technology ") to its token key. */
export function dimensionKey(name) {
    const key = String(name ?? "").trim().toLowerCase().split(/\s+/)[0];
    return DIMENSION_KEYS.includes(key) ? key : null;
}

export function dimensionColor(name) {
    const key = dimensionKey(name);
    return key ? `var(--dim-${key})` : "var(--muted-foreground)";
}

/** Dimension label with its color dot; the name is always shown, not just the color. */
export default function DimensionChip({ name, className }) {
    return (
        <span
            data-slot="dimension-chip"
            className={cn(
                "inline-flex h-6 w-fit max-w-full shrink-0 items-center gap-1.5 rounded-full border bg-card px-2.5 text-xs font-medium text-foreground",
                className
            )}
        >
            <span
                aria-hidden="true"
                className="size-2 shrink-0 rounded-full"
                style={{ backgroundColor: dimensionColor(name) }}
            />
            <span className="truncate">{name || "-"}</span>
        </span>
    );
}
