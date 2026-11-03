import { AlertCircleIcon, CheckIcon, CircleCheckIcon, PencilIcon, Trash2Icon, XIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import HintTooltip from "@/Components/HintTooltip";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { RESPONSE_FITS, RESPONSE_STATUSES, ROBUSTNESS } from "@/domain/scenarioPlanning";

/** One strategic response: status, robustness, fit per quadrant, links and actions. */
export default function ResponseCard({ response, quadrants, canManage, busy, onEdit, onRemove, onApprove, onReject, onKeep }) {
    const status = RESPONSE_STATUSES[response.status];
    const robustness = ROBUSTNESS[response.robustness];
    const initiatives = (response.initiatives ?? "").split("\n").map((line) => line.trim()).filter(Boolean);

    return (
        <article className="flex flex-col gap-3 rounded-lg border border-border p-4">
            <header className="flex flex-wrap items-center gap-2">
                <h3 className="m-0 text-sm font-semibold text-foreground">{response.title}</h3>
                <ProvenanceBadge origin={response.origin} review={response.status} reviewLabel={status.label} />
                {robustness && (
                    <HintTooltip content={robustness.hint}>
                        <span tabIndex={0} className={statusBadge({ tone: response.robustness === "robust" ? "approved" : "neutral" })}>
                            {robustness.label} · {response.applies}/4
                        </span>
                    </HintTooltip>
                )}
                {response.priority && <span className="text-xs font-medium text-foreground">P{response.priority}</span>}
                {response.owner && <span className="text-xs text-muted-foreground">PJ: {response.owner.name}</span>}
            </header>

            {response.stale && (
                <div role="status" className="flex flex-col gap-1.5 rounded-md border border-warning/40 bg-warning-soft p-2.5 text-xs text-warning">
                    <p className="m-0 flex items-center gap-1.5 font-semibold">
                        <AlertCircleIcon aria-hidden="true" className="size-4" />
                        Kondisi yang diandalkan berubah. Tinjau respons ini.
                    </p>
                    <ul className="m-0 flex list-disc flex-col gap-0.5 pl-5">
                        {response.stale.reasons.map((reason) => (
                            <li key={reason}>{reason}</li>
                        ))}
                    </ul>
                    {canManage && (
                        <Button size="sm" variant="outline" className="self-start" disabled={busy} onClick={onKeep}>
                            <CheckIcon aria-hidden="true" />
                            Sudah ditinjau, pertahankan
                        </Button>
                    )}
                </div>
            )}

            <dl className="m-0 grid grid-cols-2 gap-2 sm:grid-cols-4">
                {quadrants.map((quadrant) => {
                    const fit = RESPONSE_FITS[response.fit[quadrant.position]];
                    return (
                        <div key={quadrant.position} className="flex min-w-0 flex-col gap-1 rounded-md bg-muted/40 p-2">
                            <dt className="truncate text-[11px] text-muted-foreground">{quadrant.title}</dt>
                            <dd className="m-0">
                                <span className={statusBadge({ tone: fit?.tone ?? "neutral" })}>{fit?.label ?? "Belum dinilai"}</span>
                            </dd>
                        </div>
                    );
                })}
            </dl>

            {response.rationale && <p className="m-0 text-sm text-foreground">{response.rationale}</p>}
            {initiatives.length > 0 && (
                <ul className="m-0 flex list-disc flex-col gap-0.5 pl-4 text-xs text-muted-foreground">
                    {initiatives.map((line, index) => (
                        <li key={index}>{line}</li>
                    ))}
                </ul>
            )}
            {response.implications.length > 0 && (
                <p className="m-0 text-[11px] text-muted-foreground">Menjawab: {response.implications.map((item) => `${item.position} · ${item.title}`).join("; ")}</p>
            )}
            {response.conditions.length > 0 && (
                <p className="m-0 text-[11px] text-muted-foreground">Bergantung pada: {response.conditions.map((item) => `${item.position} · ${item.text}`).join("; ")}</p>
            )}
            {response.depends_on && <p className="m-0 text-[11px] text-muted-foreground">Asumsi: {response.depends_on}</p>}
            {response.status === "approved" && response.approved_by && <p className="m-0 text-[11px] text-muted-foreground">Disetujui oleh {response.approved_by}</p>}

            {canManage && (
                <div className="flex flex-wrap gap-2">
                    {response.status !== "approved" && (
                        <Button size="sm" disabled={busy} onClick={onApprove}>
                            <CircleCheckIcon aria-hidden="true" />
                            Setujui
                        </Button>
                    )}
                    {response.status !== "rejected" && (
                        <Button size="sm" variant="outline" disabled={busy} onClick={onReject}>
                            <XIcon aria-hidden="true" />
                            Tolak
                        </Button>
                    )}
                    <Button size="sm" variant="outline" onClick={onEdit}>
                        <PencilIcon aria-hidden="true" />
                        Ubah
                    </Button>
                    <ConfirmDialog title="Hapus respons ini?" description={response.title} confirmText="Hapus" onConfirm={onRemove}>
                        <Button size="sm" variant="ghost" className="text-destructive">
                            <Trash2Icon aria-hidden="true" />
                            Hapus
                        </Button>
                    </ConfirmDialog>
                </div>
            )}
        </article>
    );
}
