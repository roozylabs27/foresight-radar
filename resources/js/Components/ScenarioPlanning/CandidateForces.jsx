import { BotIcon, CheckIcon, XIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import Panel from "@/Components/Panel";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import SuggestionControl from "@/Components/ScenarioPlanning/SuggestionControl";
import { Button } from "@/Components/ui/button";

/**
 * AI candidate driving forces for step 2 (roadmap R2.4): grouped from accepted signals that no
 * exercise force rests on yet. Accepting adds the force to the library (pending approval) and to
 * this exercise; discarding drops it. Once the list is agreed, accepting asks first: it withdraws
 * G2 and every later agreement, as adding any force does.
 */
export default function CandidateForces({ candidates, status, error, validationError, canManage, locked, agreed, busy, onSuggest, onAccept, onReject }) {
    return (
        <Panel title="Usulan driving force AI" description="Dari sinyal diterima yang belum menjadi dasar driving force di latihan ini. Ditinjau satu per satu.">
            <SuggestionControl
                status={status}
                error={error}
                validationError={validationError}
                canManage={canManage}
                disabled={locked}
                busy={busy}
                onSuggest={onSuggest}
                label="Minta usulan driving force AI"
                pendingLabel="AI sedang menyusun usulan…"
                reloadOnly={["candidates", "candidateStatus", "candidateError"]}
            />
            {candidates.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground">Belum ada usulan yang menunggu tinjauan.</p>
            ) : (
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                    {candidates.map((candidate) => (
                        <li key={candidate.id} className="flex flex-col gap-1.5 rounded-md border border-dashed border-primary/40 bg-primary/5 p-2.5 text-xs">
                            <div className="flex flex-wrap items-center gap-2">
                                <BotIcon aria-hidden="true" className="size-4 text-primary" />
                                <span className="text-sm font-semibold text-foreground">{candidate.keyword}</span>
                                <ProvenanceBadge origin="ai" review="proposed" />
                            </div>
                            <span className="text-muted-foreground">{candidate.dimension}</span>
                            <p className="m-0 text-foreground">{candidate.description}</p>
                            {candidate.rationale && <p className="m-0 text-muted-foreground">{candidate.rationale}</p>}
                            <p className="m-0 text-muted-foreground">Sinyal: {candidate.signals.map((signal) => signal.title).join("; ")}</p>
                            {canManage && (
                                <div className="flex gap-2">
                                    {agreed ? (
                                        <ConfirmDialog
                                            title="Terima dan batalkan kesepakatan?"
                                            description={`Daftar driving force sudah disepakati. Menambah "${candidate.keyword}" membatalkan kesepakatan daftar (G2) beserta kesepakatan skor (G3), critical uncertainty (G4) dan kerangka skenario (G5) yang sudah ada. Semuanya perlu disepakati ulang.`}
                                            confirmText="Terima dan batalkan"
                                            onConfirm={() => onAccept(candidate.id)}
                                        >
                                            <Button size="sm" disabled={busy}>
                                                <CheckIcon aria-hidden="true" />
                                                Terima
                                            </Button>
                                        </ConfirmDialog>
                                    ) : (
                                        <Button size="sm" disabled={busy} onClick={() => onAccept(candidate.id)}>
                                            <CheckIcon aria-hidden="true" />
                                            Terima
                                        </Button>
                                    )}
                                    <Button size="sm" variant="ghost" disabled={busy} onClick={() => onReject(candidate.id)}>
                                        <XIcon aria-hidden="true" />
                                        Buang
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
