import { useState } from "react";
import { router } from "@inertiajs/react";
import { BotIcon, PencilIcon, XIcon } from "lucide-react";

import ProvenanceBadge from "@/Components/ProvenanceBadge";
import SuggestionControl from "@/Components/ScenarioPlanning/SuggestionControl";
import { Button } from "@/Components/ui/button";
import { FOCAL_ISSUES } from "@/domain/scenarioPlanning";

/**
 * AI critique of the focal question with alternative questions (roadmap R3.1). Nothing is saved
 * from here: "Gunakan" opens the edit dialog with the chosen question, and the coordinator saves it.
 */
export default function FocalSuggestion({ projectId, suggestion, status, error, validationError, canManage, onUse }) {
    const [busy, setBusy] = useState(false);
    const visit = (method, url) => {
        setBusy(true);
        router.visit(url, { method, preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return (
        <div className="mb-4 flex flex-col gap-2">
            <SuggestionControl
                status={status}
                error={error}
                validationError={validationError}
                canManage={canManage}
                busy={busy}
                onSuggest={() => visit("post", route("scenario-planning.focal.suggest", projectId))}
                label="Minta tinjauan pertanyaan fokus AI"
                pendingLabel="AI sedang meninjau pertanyaan fokus…"
                reloadOnly={["focalSuggestion", "focalStatus", "focalError"]}
            />
            {suggestion && (
                <div className="flex flex-col gap-3 rounded-md border border-dashed border-primary/40 bg-primary/5 p-3 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <BotIcon aria-hidden="true" className="size-4 text-primary" />
                        <span className="font-semibold text-foreground">Tinjauan AI atas pertanyaan fokus</span>
                        <ProvenanceBadge origin="ai" review="proposed" />
                    </div>
                    {suggestion.critique.length === 0 ? (
                        <p className="m-0 text-muted-foreground">Tidak ada kelemahan berarti pada pertanyaan saat ini.</p>
                    ) : (
                        <ul className="m-0 flex list-disc flex-col gap-1 pl-5 text-foreground">
                            {suggestion.critique.map((row, index) => (
                                <li key={index}>
                                    <span className="font-medium">{FOCAL_ISSUES[row.issue] ?? FOCAL_ISSUES.other}:</span> {row.comment}
                                </li>
                            ))}
                        </ul>
                    )}
                    <ol className="m-0 flex list-none flex-col gap-2 p-0">
                        {suggestion.alternatives.map((row, index) => (
                            <li key={index} className="flex flex-wrap items-start justify-between gap-2 rounded-md bg-card p-2">
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="font-medium text-foreground">{row.question}</span>
                                    {row.rationale && <span className="text-xs text-muted-foreground">{row.rationale}</span>}
                                </div>
                                {canManage && (
                                    <Button size="sm" variant="outline" onClick={() => onUse(row.question)}>
                                        <PencilIcon aria-hidden="true" />
                                        Gunakan
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ol>
                    {canManage && (
                        <Button size="sm" variant="ghost" className="self-start text-muted-foreground" disabled={busy} onClick={() => visit("delete", route("scenario-planning.focal.suggestion.reject", projectId))}>
                            <XIcon aria-hidden="true" />
                            Buang saran
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
