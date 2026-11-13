import { LibraryIcon, PencilIcon, UserRoundIcon } from "lucide-react";

import ScoreSuggestion from "@/Components/ScenarioPlanning/ScoreSuggestion";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { SCORE_CLASSES, classifyScore, formatSpread } from "@/domain/scenarioPlanning";

/**
 * Included forces with their number, consensus scores, matrix class and score provenance, plus the
 * spread of the participants' own scores (roadmap R2.1). `onEdit(item, mode)` opens the score dialog
 * for the consensus ("consensus") or the signed-in participant's own score ("mine"); `onKeepLibrary`
 * keeps a score copied from the organisation rating (PI-02).
 */
export default function ScoreList({ forces, threshold, canManage, canScore, busy, onEdit, onWithdraw, onAcceptSuggestion, onRejectSuggestion, onKeepLibrary }) {
    if (forces.length === 0) {
        return <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada driving force yang disertakan di langkah 2.</p>;
    }

    return (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {forces.map((item) => {
                const kind = SCORE_CLASSES[classifyScore(item.impact, item.uncertainty, threshold)];
                const spread = item.individual;
                return (
                    <li key={item.id} className="flex flex-wrap items-start gap-3 rounded-lg border border-border p-3">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold text-foreground">{item.number}</span>
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-foreground">{item.force.keyword}</span>
                                <span className={statusBadge({ tone: kind.tone })}>{kind.label}</span>
                                {item.library_pending && <span className={statusBadge({ tone: "pending" })}>Disalin dari penilaian organisasi, tinjau</span>}
                                {item.origin === "library" && !item.library_pending && <span className={statusBadge({ tone: "approved" })}>Nilai library, sudah ditinjau</span>}
                                {spread.wide && <span className={statusBadge({ tone: "pending" })}>Skor peserta berbeda jauh, diskusikan</span>}
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {item.force.environment} · {item.force.dimension}
                                {item.impact != null && ` · Kesepakatan: impact ${item.impact} · uncertainty ${item.uncertainty}`}
                            </span>
                            {(item.impact_rationale || item.uncertainty_rationale) && (
                                <p className="m-0 text-xs text-muted-foreground">
                                    {item.impact_rationale && <>Impact: {item.impact_rationale} </>}
                                    {item.uncertainty_rationale && <>Uncertainty: {item.uncertainty_rationale}</>}
                                </p>
                            )}
                            {spread.count > 0 && (
                                <p className="m-0 text-xs text-muted-foreground">
                                    Skor peserta ({spread.count}): impact {formatSpread(spread.impact)} · uncertainty {formatSpread(spread.uncertainty)}
                                </p>
                            )}
                            {item.suggestion && (
                                <ScoreSuggestion suggestion={item.suggestion} canManage={canManage} busy={busy} onAccept={() => onAcceptSuggestion(item)} onReject={() => onRejectSuggestion(item)} />
                            )}
                            {item.mine && (
                                <p className="m-0 text-xs text-foreground">
                                    Skor Anda: impact {item.mine.impact} · uncertainty {item.mine.uncertainty}
                                    {onWithdraw && (
                                        <button type="button" className="ml-2 text-xs text-muted-foreground underline hover:text-foreground" onClick={() => onWithdraw(item)}>
                                            Tarik
                                        </button>
                                    )}
                                </p>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-wrap gap-2">
                            {canScore && (
                                <Button size="sm" variant="outline" onClick={() => onEdit(item, "mine")} aria-label={`Skor saya untuk ${item.force.keyword}`}>
                                    <UserRoundIcon aria-hidden="true" />
                                    Skor saya
                                </Button>
                            )}
                            {canManage && item.library_pending && onKeepLibrary && (
                                <Button size="sm" variant="outline" disabled={busy} onClick={() => onKeepLibrary(item)} aria-label={`Pakai nilai library untuk ${item.force.keyword}`}>
                                    <LibraryIcon aria-hidden="true" />
                                    Pakai nilai library
                                </Button>
                            )}
                            {canManage && (
                                <Button size="sm" variant="outline" onClick={() => onEdit(item, "consensus")} aria-label={`Skor kesepakatan ${item.force.keyword}`}>
                                    <PencilIcon aria-hidden="true" />
                                    Kesepakatan
                                </Button>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
