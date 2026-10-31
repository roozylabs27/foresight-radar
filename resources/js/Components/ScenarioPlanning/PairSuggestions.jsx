import { statusBadge } from "@/Components/StatusBadge";

/**
 * Pairs of the exercise's critical uncertainties, strongest independent pairs
 * first. Axes of a 2×2 should not drive each other; the cross-impact score
 * between their forces shows how much they do. Advice only.
 */
export default function PairSuggestions({ pairs }) {
    if (pairs.length === 0) {
        return <p className="m-0 text-sm text-muted-foreground">Buat minimal dua usulan untuk melihat pasangan sumbu.</p>;
    }

    return (
        <ol className="m-0 flex list-none flex-col gap-2 p-0">
            {pairs.map((pair) => (
                <li key={`${pair.a.id}-${pair.b.id}`} className="flex flex-col gap-1 rounded-md border border-border p-2.5">
                    <span className="text-sm font-medium text-foreground">
                        {pair.a.name} × {pair.b.name}
                    </span>
                    <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>Kekuatan {pair.strength}</span>
                        <span className={statusBadge({ tone: pair.dependent ? "pending" : "approved" })}>
                            {pair.dependent ? `Saling memengaruhi (${pair.influence})` : pair.influence > 0 ? `Pengaruh lemah (${pair.influence})` : "Independen"}
                        </span>
                    </span>
                </li>
            ))}
        </ol>
    );
}
