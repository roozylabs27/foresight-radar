import { PencilIcon } from "lucide-react";

import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { SCORE_CLASSES, classifyScore } from "@/domain/scenarioPlanning";

/** Included forces with their number, scores, matrix class and score provenance. */
export default function ScoreList({ forces, threshold, canManage, onEdit }) {
    if (forces.length === 0) {
        return <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada driving force yang disertakan di langkah 2.</p>;
    }

    return (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {forces.map((item) => {
                const kind = SCORE_CLASSES[classifyScore(item.impact, item.uncertainty, threshold)];
                return (
                    <li key={item.id} className="flex items-start gap-3 rounded-lg border border-border p-3">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold text-foreground">{item.number}</span>
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-foreground">{item.force.keyword}</span>
                                <span className={statusBadge({ tone: kind.tone })}>{kind.label}</span>
                                {item.origin === "library" && <span className={statusBadge({ tone: "pending" })}>Disalin dari penilaian organisasi, tinjau</span>}
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {item.force.environment} · {item.force.dimension}
                                {item.impact != null && ` · Impact ${item.impact} · Uncertainty ${item.uncertainty}`}
                            </span>
                            {(item.impact_rationale || item.uncertainty_rationale) && (
                                <p className="m-0 text-xs text-muted-foreground">
                                    {item.impact_rationale && <>Impact: {item.impact_rationale} </>}
                                    {item.uncertainty_rationale && <>Uncertainty: {item.uncertainty_rationale}</>}
                                </p>
                            )}
                        </div>
                        {canManage && (
                            <Button size="sm" variant="outline" onClick={() => onEdit(item)} aria-label={`Beri skor ${item.force.keyword}`}>
                                <PencilIcon aria-hidden="true" />
                                Skor
                            </Button>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
