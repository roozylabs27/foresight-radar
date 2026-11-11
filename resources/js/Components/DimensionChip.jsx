import { DIMENSION_COLORS, dimensionColor as paletteColor } from "@/domain/dimensions";
import { cn } from "@/lib/utils";

/**
 * Colour keys of the default taxonomy (and its legacy names), so these keep one colour everywhere.
 * Custom dimensions without a chosen colour get a stable key from their name.
 */
const DEFAULT_KEYS = {
    political: "red",
    politic: "red",
    economic: "amber",
    economy: "amber",
    social: "pink",
    technology: "teal",
    technological: "teal",
    ecology: "green",
    environmental: "green",
    legislative: "purple",
    regulation: "purple",
    consumer: "cyan",
    customer: "cyan",
    "supply chain": "orange",
    supplier: "orange",
    competitor: "indigo",
    "new entrant": "blue",
    substitute: "lime",
};

const PALETTE = Object.keys(DIMENSION_COLORS);

/** Colour key for a dimension: the one the organisation chose, a default one, or one from its name. */
export function dimensionKey(name, color) {
    if (color && DIMENSION_COLORS[color]) return color;
    const normalized = String(name ?? "").trim().toLowerCase();
    if (!normalized) return null;
    if (DEFAULT_KEYS[normalized]) return DEFAULT_KEYS[normalized];
    let hash = 0;
    for (const character of normalized) hash = (hash * 31 + character.codePointAt(0)) >>> 0;
    return PALETTE[hash % PALETTE.length];
}

export function dimensionColor(name, color) {
    const key = dimensionKey(name, color);
    return key ? paletteColor(key) : "var(--muted-foreground)";
}

/** Dimension label with its color dot; the name is always shown, not just the color. */
export default function DimensionChip({ name, color, className }) {
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
                style={{ backgroundColor: dimensionColor(name, color) }}
            />
            <span className="truncate">{name || "-"}</span>
        </span>
    );
}
