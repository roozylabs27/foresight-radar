import { PencilIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import CommentButton from "@/Components/ScenarioPlanning/CommentButton";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { INCLUSION_STATUSES } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

/** The exercise's driving forces with their per-exercise status, note and flag. */
export default function ProjectForceList({ forces, canManage, onEdit, onRemove }) {
    if (forces.length === 0) {
        return <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada driving force. Tambahkan dari pustaka organisasi.</p>;
    }

    return (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {forces.map((link) => {
                const inclusion = INCLUSION_STATUSES[link.inclusion_status] ?? INCLUSION_STATUSES.included;
                return (
                    <li key={link.id} className={cn("flex items-start gap-3 rounded-lg border border-border p-3", link.inclusion_status === "excluded" && "bg-muted/30")}>
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-foreground">{link.force?.keyword}</span>
                                <span className={statusBadge({ tone: inclusion.tone })}>{inclusion.label}</span>
                                {link.is_predetermined && <span className={statusBadge({ tone: "neutral" })}>Predetermined</span>}
                                {link.force?.is_deleted && <span className={statusBadge({ tone: "rejected" })}>Dihapus dari pustaka</span>}
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {link.force?.environment} · {link.force?.dimension} · {link.force?.signals_count} sinyal pendukung
                            </span>
                            <p className="m-0 text-xs text-muted-foreground">{link.influence_note || "Belum ada catatan pengaruh terhadap focal issue."}</p>
                        </div>
                        <div className="flex shrink-0 gap-1">
                            <CommentButton type="force" id={link.id} title={link.force?.keyword} count={link.open_comments} />
                            {canManage && (
                                <>
                                    <Button size="icon" variant="ghost" aria-label={`Ubah ${link.force?.keyword}`} onClick={() => onEdit(link)}>
                                        <PencilIcon aria-hidden="true" />
                                    </Button>
                                    <ConfirmDialog
                                        title="Keluarkan dari latihan?"
                                        description="Driving force tetap ada di pustaka organisasi; hanya dihapus dari daftar latihan ini."
                                        confirmText="Keluarkan"
                                        onConfirm={() => onRemove(link)}
                                    >
                                        <Button size="icon" variant="ghost" className="text-destructive" aria-label={`Keluarkan ${link.force?.keyword}`}>
                                            <Trash2Icon aria-hidden="true" />
                                        </Button>
                                    </ConfirmDialog>
                                </>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
