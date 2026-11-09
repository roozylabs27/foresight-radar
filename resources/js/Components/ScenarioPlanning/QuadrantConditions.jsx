import { ArrowDownIcon, ArrowUpIcon, CheckIcon, PencilIcon, PlusIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import HintTooltip from "@/Components/HintTooltip";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import CommentButton from "@/Components/ScenarioPlanning/CommentButton";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { CONDITION_KINDS } from "@/domain/scenarioPlanning";

/**
 * One quadrant's conditions in order, with the predetermined elements that hold in it. AI drafts
 * (roadmap R2.5) are marked and count only once accepted. Actions render only with `canManage`.
 */
export default function QuadrantConditions({ quadrant, axes, predetermined, minimum, canManage, onAdd, onEdit, onRemove, onMove, onAccept }) {
    const accepted = quadrant.conditions.filter((condition) => condition.review_status !== "proposed").length;
    const enough = accepted >= minimum;
    const axisName = (letter) => axes.find((axis) => axis.axis === letter)?.name ?? letter;

    return (
        <article className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
            <header className="flex flex-col gap-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{quadrant.poles.join(" × ")}</span>
                    <span className={statusBadge({ tone: enough ? "approved" : "pending" })}>
                        {accepted} kondisi{enough ? "" : ` · min. ${minimum}`}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2">
                    <h3 className="m-0 text-sm font-semibold text-foreground">{quadrant.title}</h3>
                    <CommentButton type="quadrant" id={quadrant.id} title={quadrant.title} count={quadrant.open_comments} />
                </div>
                {quadrant.summary && <p className="m-0 text-xs text-muted-foreground">{quadrant.summary}</p>}
            </header>

            {quadrant.conditions.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground italic">Belum ada kondisi.</p>
            ) : (
                <ol className="m-0 flex list-none flex-col gap-2 p-0">
                    {quadrant.conditions.map((condition, index) => (
                        <li key={condition.id} className={condition.review_status === "proposed" ? "flex items-start gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 p-2" : "flex items-start gap-2 rounded-md bg-muted/40 p-2"}>
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span className="font-medium text-foreground">{CONDITION_KINDS[condition.kind]?.label}</span>
                                    {condition.axis && <span>· Sumbu {condition.axis} ({axisName(condition.axis)})</span>}
                                    {condition.force && <span>· {condition.force.keyword}</span>}
                                    {condition.review_status === "proposed" && <ProvenanceBadge origin={condition.origin} review="proposed" />}
                                    {condition.similar_to?.length > 0 && (
                                        <HintTooltip content="Sebagian besar katanya sama dengan kondisi di kuadran lain. Skenario yang mirip sulit dibedakan.">
                                            <span tabIndex={0} className={statusBadge({ tone: "pending" })}>
                                                Mirip dengan {condition.similar_to.join(", ")}
                                            </span>
                                        </HintTooltip>
                                    )}
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
                                    {condition.review_status === "proposed" && (
                                        <Button size="icon-xs" variant="ghost" aria-label="Terima draf kondisi" className="text-success" onClick={() => onAccept(condition.id)}>
                                            <CheckIcon aria-hidden="true" />
                                        </Button>
                                    )}
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
