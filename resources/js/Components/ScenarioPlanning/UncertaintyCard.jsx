import { ArrowLeftRightIcon, CircleCheckIcon, PencilIcon, Trash2Icon, UndoIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import { Button } from "@/Components/ui/button";

/** One critical uncertainty: its forces, poles, reason and approval state, with the actions allowed. */
export default function UncertaintyCard({ uncertainty, canManage, locked, onEdit, onRemove, onApprove, onWithdraw }) {
    const approved = uncertainty.status === "approved";

    return (
        <article className={approved ? "flex flex-col gap-3 rounded-lg border-2 border-primary/50 bg-accent/30 p-4" : "flex flex-col gap-3 rounded-lg border border-border p-4"}>
            <header className="flex flex-wrap items-center gap-2">
                <h3 className="m-0 text-sm font-semibold text-foreground">{uncertainty.name}</h3>
                {approved ? (
                    <ProvenanceBadge review="approved" reviewLabel={`Sumbu ${uncertainty.axis} · disetujui`} />
                ) : (
                    <ProvenanceBadge origin={uncertainty.origin} review="proposed" />
                )}
            </header>

            <p className="m-0 inline-flex items-center gap-2 text-sm text-foreground">
                <span className="rounded bg-muted px-2 py-0.5">{uncertainty.low_pole_label}</span>
                <ArrowLeftRightIcon aria-label="sampai" className="size-4 text-muted-foreground" />
                <span className="rounded bg-muted px-2 py-0.5">{uncertainty.high_pole_label}</span>
            </p>

            <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0" aria-label="Driving force yang digabungkan">
                {uncertainty.forces.map((force) => (
                    <li key={force.id} className="rounded-md border border-border bg-card px-2 py-1 text-xs text-foreground">
                        {force.keyword} <span className="text-muted-foreground">· I {force.impact ?? "-"} · U {force.uncertainty ?? "-"}</span>
                    </li>
                ))}
            </ul>

            <p className="m-0 text-xs text-muted-foreground">
                <span className="font-medium text-foreground">Alasan: </span>
                {uncertainty.selection_rationale}
            </p>
            {approved && (
                <p className="m-0 text-xs text-muted-foreground">Disetujui oleh {uncertainty.approved_by}, {new Date(uncertainty.approved_at).toLocaleDateString("id-ID")}.</p>
            )}

            {canManage && (
                <footer className="flex flex-wrap gap-2">
                    {approved ? (
                        <ConfirmDialog
                            title="Batalkan persetujuan?"
                            description="Critical uncertainty kembali menjadi usulan dan langkah 4–9 terkunci lagi sampai dua sumbu disetujui."
                            confirmText="Batalkan persetujuan"
                            onConfirm={onWithdraw}
                        >
                            <Button size="sm" variant="outline">
                                <UndoIcon aria-hidden="true" />
                                Batalkan persetujuan
                            </Button>
                        </ConfirmDialog>
                    ) : (
                        <>
                            <Button size="sm" onClick={onApprove} disabled={locked}>
                                <CircleCheckIcon aria-hidden="true" />
                                Setujui sebagai sumbu
                            </Button>
                            <Button size="sm" variant="outline" onClick={onEdit}>
                                <PencilIcon aria-hidden="true" />
                                Ubah
                            </Button>
                            <ConfirmDialog title="Hapus usulan ini?" confirmText="Hapus" onConfirm={onRemove}>
                                <Button size="sm" variant="ghost" className="text-destructive">
                                    <Trash2Icon aria-hidden="true" />
                                    Hapus
                                </Button>
                            </ConfirmDialog>
                        </>
                    )}
                </footer>
            )}
        </article>
    );
}
