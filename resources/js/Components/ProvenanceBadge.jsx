import { statusBadge } from "@/Components/StatusBadge";
import { ORIGINS, REVIEW_STATES } from "@/domain/provenance";
import { cn } from "@/lib/utils";

const AI_CLASS = "bg-primary text-primary-foreground";

/**
 * Where an artefact came from and whether a person accepted it, as one or two badges (roadmap R0.6).
 * `origin` and `review` take the shared vocabulary; `originLabel` / `reviewLabel` override the text
 * (e.g. the AI provider's name, or "Sumbu A · disetujui"). Human origin and an unknown review show nothing.
 */
export default function ProvenanceBadge({ origin, review, originLabel, reviewLabel, className }) {
    const source = ORIGINS[origin];
    const state = REVIEW_STATES[review];
    if (!source && !state) return null;

    return (
        <span className={cn("inline-flex flex-wrap gap-1", className)}>
            {source && (
                <span className={cn(statusBadge({ tone: source.tone === "ai" ? "neutral" : source.tone }), source.tone === "ai" && AI_CLASS)}>
                    {originLabel ?? source.label}
                </span>
            )}
            {state && <span className={statusBadge({ tone: state.tone })}>{reviewLabel ?? state.label}</span>}
        </span>
    );
}
