import { ArrowDownIcon, ArrowUpIcon, PencilIcon, PlusIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import HintTooltip from "@/Components/HintTooltip";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { CONDITION_KINDS } from "@/domain/scenarioPlanning";

/**
 * One quadrant's conditions in order, with the predetermined elements that hold in it. Actions
 * render only when the handlers are passed (`canManage`).
 */
export default function QuadrantConditions({ quadrant, axes, predetermined, minimum, canManage, onAdd, onEdit, onRemove, onMove }) {
    const enough = quadrant.conditions.length >= minimum;
    const axisName = (letter) => axes.find((axis) => axis.axis === letter)?.name ?? letter;

    return (
        <article className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
            <header className="flex flex-col gap-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{quadrant.poles.join(" × ")}</span>
                    <span className={statusBadge({ tone: enough ? "approved" : "pending" })}>
                        {quadrant.conditions.length} kondisi{enough ? "" : ` · min. ${minimum}`}
                    </span>
                </div>
                <h3 className="m-0 text-sm font-semibold text-foreground">{quadrant.title}</h3>
                {quadrant.summary && <p className="m-0 text-xs text-muted-foreground">{quadrant.summary}</p>}
            </header>

            {quadrant.conditions.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground italic">Belum ada kondisi.</p>
            ) : (
                <ol className="m-0 flex list-none flex-col gap-2 p-0">
                    {quadrant.conditions.map((condition, index) => (
                        <li key={condition.id} className="flex items-start gap-2 rounded-md bg-muted/40 p-2">
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span className="font-medium text-foreground">{CONDITION_KINDS[condition.kind]?.label}</span>
                                    {condition.axis && <span>· Sumbu {condition.axis} ({axisName(condition.axis)})</span>}
                                    {condition.force && <span>· {condition.force.keyword}</span>}
                                    {condition.shared && (
                                        <HintTooltip content="Teks yang sama ada di keempat kuadran. Mungkin ini elemen predetermined, bukan pembeda skenario.">
                                            <span tabIndex={0} className={statusBadge({ tone: "pending" })}>
                                                Sama di 4 kuadran
                                            </span>
                                        </HintTooltip>
                                    )}
                                </div>
                                <p className="m-0 text-sm text-foreground">{condition.text}</p>
                            </div>
                            {canManage && (
                                <div className="flex shrink-0 items-center">
                                    <Button size="icon-xs" variant="ghost" aria-label="Naikkan" disabled={index === 0} onClick={() => onMove(condition.id, "up")}>
                                        <ArrowUpIcon aria-hidden="true" />
                                    </Button>
                                    <Button size="icon-xs" variant="ghost" aria-label="Turunkan" disabled={index === quadrant.conditions.length - 1} onClick={() => onMove(condition.id, "down")}>
                                        <ArrowDownIcon aria-hidden="true" />
                                    </Button>
                                    <Button size="icon-xs" variant="ghost" aria-label="Ubah kondisi" onClick={() => onEdit(condition)}>
                                        <PencilIcon aria-hidden="true" />
                                    </Button>
                                    <ConfirmDialog title="Hapus kondisi ini?" description={condition.text} confirmText="Hapus" onConfirm={() => onRemove(condition.id)}>
                                        <Button size="icon-xs" variant="ghost" aria-label="Hapus kondisi" className="text-destructive">
                                            <Trash2Icon aria-hidden="true" />
                                        </Button>
                                    </ConfirmDialog>
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {predetermined.length > 0 && (
                <p className="m-0 text-[11px] text-muted-foreground">
                    <span className="font-medium text-foreground">Predetermined:</span> {predetermined.map((force) => force.keyword).join(", ")}
                </p>
            )}

            {canManage && (
                <Button size="sm" variant="outline" className="self-start" onClick={onAdd}>
                    <PlusIcon aria-hidden="true" />
                    Tambah kondisi
                </Button>
            )}
        </article>
    );
}
