import { BotIcon, CheckIcon, XIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import SuggestionControl from "@/Components/ScenarioPlanning/SuggestionControl";
import { Button } from "@/Components/ui/button";
import { POLE_ISSUES } from "@/domain/scenarioPlanning";

const POSITIONS = ["HH", "HL", "LH", "LL"];

/**
 * AI pole labels and quadrant names for a draft framework (roadmap R2.5), with a critique of the
 * current poles (matrix row 4.7). Applying replaces the current poles and names; quadrants that
 * already have a narrative are flagged for review. The critique is advice only.
 */
export default function FrameworkSuggestion({ framework, canManage, busy, validationError, onSuggest, onAccept, onReject }) {
    const suggestion = framework.suggestion;

    return (
        <div className="flex flex-col gap-2">
            <SuggestionControl
                status={framework.suggestionStatus}
                error={framework.suggestionError}
                validationError={validationError}
                canManage={canManage}
                busy={busy}
                onSuggest={onSuggest}
                label="Minta kritik dan saran kutub AI"
                pendingLabel="AI sedang menyiapkan saran kerangka…"
                reloadOnly={["framework"]}
            />
            {suggestion && (
                <div className="flex flex-col gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 p-3 text-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <BotIcon aria-hidden="true" className="size-4 text-primary" />
                        <span className="font-semibold text-foreground">Saran AI untuk kerangka</span>
                        <ProvenanceBadge origin="ai" review="proposed" />
                    </div>
                    {suggestion.critique?.length > 0 && (
                        <section aria-label="Catatan AI atas kutub saat ini" className="flex flex-col gap-1">
                            <p className="m-0 font-semibold text-foreground">Catatan atas kutub saat ini</p>
                            <ul className="m-0 flex list-none flex-col gap-1 p-0">
                                {suggestion.critique.map((row, index) => (
                                    <li key={index} className="flex flex-wrap items-baseline gap-1.5">
                                        <span className="rounded bg-warning-soft px-1.5 py-0.5 font-medium text-warning">
                                            {row.axis === "both" ? "Kedua sumbu" : `Sumbu ${row.axis}`} · {POLE_ISSUES[row.issue] ?? POLE_ISSUES.other}
                                        </span>
                                        <span className="text-muted-foreground">{row.comment}</span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                    <p className="m-0 font-semibold text-foreground">Usulan kutub dan nama</p>
                    <dl className="m-0 grid gap-1 sm:grid-cols-2">
                        {framework.axes.map((axis) => {
                            const key = axis.axis.toLowerCase();
                            return (
                                <div key={axis.axis}>
                                    <dt className="text-muted-foreground">Sumbu {axis.axis} · {axis.name}</dt>
                                    <dd className="m-0 text-foreground">
                                        {suggestion[`axis_${key}_low_label`]} ↔ {suggestion[`axis_${key}_high_label`]}
                                    </dd>
                                </div>
                            );
                        })}
                    </dl>
                    <ul className="m-0 flex list-none flex-col gap-1 p-0">
                        {POSITIONS.map((position) => suggestion.quadrants?.[position] && (
                            <li key={position}>
                                <span className="font-semibold text-foreground">{position}: {suggestion.quadrants[position].title}</span>
                                {suggestion.quadrants[position].summary && <span className="text-muted-foreground"> · {suggestion.quadrants[position].summary}</span>}
                            </li>
                        ))}
                    </ul>
                    {suggestion.rationale && <p className="m-0 text-muted-foreground">{suggestion.rationale}</p>}
                    {canManage && (
                        <div className="flex gap-2">
                            <ConfirmDialog
                                title="Pakai saran AI?"
                                description="Kutub kedua sumbu dan nama keempat kuadran diganti dengan saran ini. Kuadran yang sudah punya narasi ditandai untuk ditinjau."
                                confirmText="Pakai saran"
                                variant="default"
                                onConfirm={onAccept}
                            >
                                <Button size="sm" disabled={busy}>
                                    <CheckIcon aria-hidden="true" />
                                    Pakai saran
                                </Button>
                            </ConfirmDialog>
                            <Button size="sm" variant="ghost" disabled={busy} onClick={onReject}>
                                <XIcon aria-hidden="true" />
                                Buang
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
