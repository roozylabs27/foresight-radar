import { PencilIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { MILESTONE_STATUSES } from "@/domain/scenarioPlanning";

/**
 * Roadmap R4.1: the backcasting path, read from the target year back to now. Each milestone shows
 * its owner and the step 8 response and step 9 indicator it points at.
 */
export default function MilestoneList({ milestones, targetYear, canManage, busy, onEdit, onRemove }) {
    if (milestones.length === 0) {
        return <p className="m-0 py-4 text-center text-sm text-muted-foreground">Belum ada milestone. Mulai dari kondisi di {targetYear}, lalu mundur ke tahun ini.</p>;
    }

    return (
        <ol className="m-0 flex list-none flex-col gap-0 p-0">
            {milestones.map((milestone) => {
                const status = MILESTONE_STATUSES[milestone.status];
                const links = [milestone.owner && `PJ: ${milestone.owner.name}`, milestone.response && `Respons: ${milestone.response.title}`, milestone.indicator && `Indikator: ${milestone.indicator.name}`].filter(Boolean);
                return (
                    <li key={milestone.id} className="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-3 border-l-2 border-border py-2 pl-3">
                        <span className="text-sm font-bold text-primary tabular-nums">{milestone.year}</span>
                        <div className="flex min-w-0 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-foreground">{milestone.title}</span>
                                <span className={statusBadge({ tone: status?.tone })}>{status?.label ?? milestone.status}</span>
                            </div>
                            {milestone.description && <p className="m-0 text-xs text-foreground">{milestone.description}</p>}
                            {links.length > 0 && <p className="m-0 flex flex-wrap gap-x-3 text-[11px] text-muted-foreground">{links.map((text) => <span key={text}>{text}</span>)}</p>}
                            {canManage && (
                                <div className="flex gap-1">
                                    <Button size="sm" variant="ghost" disabled={busy} onClick={() => onEdit(milestone)}>
                                        <PencilIcon aria-hidden="true" />
                                        Ubah
                                    </Button>
                                    <ConfirmDialog title="Hapus milestone ini?" description={`${milestone.year} · ${milestone.title}`} confirmText="Hapus" onConfirm={() => onRemove(milestone.id)}>
                                        <Button size="sm" variant="ghost" className="text-destructive">
                                            <Trash2Icon aria-hidden="true" />
                                            Hapus
                                        </Button>
                                    </ConfirmDialog>
                                </div>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
