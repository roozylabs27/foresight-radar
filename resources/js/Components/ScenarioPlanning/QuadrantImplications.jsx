import { CheckIcon, CopyPlusIcon, PencilIcon, PlusIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import HintTooltip from "@/Components/HintTooltip";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import StaleTag from "@/Components/ScenarioPlanning/StaleTag";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { IMPACT_LEVELS, IMPLICATION_TYPES } from "@/domain/scenarioPlanning";

/** One opportunity, challenge or untyped proposal, with its links and actions. */
function Item({ item, canManage, onEdit, onRemove, onAccept }) {
    const type = IMPLICATION_TYPES[item.type];

    return (
        <li className="flex items-start gap-2 rounded-md bg-muted/40 p-2">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                    {type && <span className={statusBadge({ tone: type.tone })}>{type.label}</span>}
                    {item.review_status === "proposed" && <ProvenanceBadge origin={item.origin} review="proposed" />}
                    <StaleTag reason={item.stale_reason} />
                    {item.priority && <span className="font-medium text-foreground">P{item.priority}</span>}
                    {item.impact_level && <span>{IMPACT_LEVELS[item.impact_level]}</span>}
                    {item.generic && (
                        <HintTooltip content={item.similar_to?.length > 0 ? "Judulnya sangat mirip dengan item di kuadran lain, jadi item ini kurang membedakan skenario." : "Judul yang sama ada di keempat kuadran, jadi item ini tidak membedakan skenario."}>
                            <button type="button" className={statusBadge({ tone: "pending" })}>
                                {item.similar_to?.length > 0 ? `Generik · mirip ${item.similar_to.join(", ")}` : "Sama di 4 kuadran"}
                            </button>
                        </HintTooltip>
                    )}
                </div>
                <p className="m-0 text-sm font-medium text-foreground">{item.title}</p>
                {item.description && <p className="m-0 text-xs whitespace-pre-line text-muted-foreground">{item.description}</p>}
                {(item.forces.length > 0 || item.conditions.length > 0) && (
                    <p className="m-0 text-[11px] text-muted-foreground">
                        {[...item.forces.map((force) => force.keyword), ...item.conditions.map((condition) => `“${condition.text}”`)].join(" · ")}
                    </p>
                )}
            </div>
            {canManage && (
                <div className="flex shrink-0 items-center">
                    {item.review_status === "proposed" && item.type && (
                        <Button size="icon-xs" variant="ghost" aria-label="Terima draf" className="text-success" onClick={() => onAccept(item.id)}>
                            <CheckIcon aria-hidden="true" />
                        </Button>
                    )}
                    <Button size="icon-xs" variant="ghost" aria-label={item.type ? "Ubah item" : "Tinjau usulan"} onClick={() => onEdit(item)}>
                        <PencilIcon aria-hidden="true" />
                    </Button>
                    <ConfirmDialog title="Hapus item ini?" description={item.title} confirmText="Hapus" onConfirm={() => onRemove(item.id)}>
                        <Button size="icon-xs" variant="ghost" aria-label="Hapus item" className="text-destructive">
                            <Trash2Icon aria-hidden="true" />
                        </Button>
                    </ConfirmDialog>
                </div>
            )}
        </li>
    );
}

/**
 * Opportunities and challenges of one quadrant, plus the narrative's own implications text that the
 * team can copy into an item. Actions render only when `canManage`.
 */
export default function QuadrantImplications({ quadrant, canManage, onAdd, onEdit, onRemove, onAccept }) {
    const accepted = quadrant.items.filter((item) => item.type && item.review_status !== "proposed");
    const complete = accepted.some((item) => item.type === "opportunity") && accepted.some((item) => item.type === "challenge");

    return (
        <article className="flex min-w-0 flex-col gap-3 rounded-lg border border-border p-4">
            <header className="flex flex-col gap-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{quadrant.poles.join(" × ")}</span>
                    <span className={statusBadge({ tone: complete ? "approved" : "pending" })}>{complete ? "Lengkap" : "Perlu peluang & tantangan"}</span>
                </div>
                <h3 className="m-0 text-sm font-semibold text-foreground">{quadrant.title}</h3>
            </header>

            {quadrant.items.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground italic">Belum ada peluang atau tantangan.</p>
            ) : (
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                    {quadrant.items.map((item) => (
                        <Item key={item.id} item={item} canManage={canManage} onEdit={onEdit} onRemove={onRemove} onAccept={onAccept} />
                    ))}
                </ul>
            )}

            {quadrant.narrative_implications && (
                <details className="rounded-md border border-dashed border-border p-2 text-xs text-muted-foreground">
                    <summary className="cursor-pointer font-medium text-foreground">Implikasi dari narasi</summary>
                    <p className="m-0 mt-1 whitespace-pre-line">{quadrant.narrative_implications}</p>
                    {canManage && (
                        <Button size="sm" variant="ghost" className="mt-1" onClick={() => onAdd({ description: quadrant.narrative_implications })}>
                            <CopyPlusIcon aria-hidden="true" />
                            Salin ke item baru
                        </Button>
                    )}
                </details>
            )}

            {canManage && (
                <Button size="sm" variant="outline" className="self-start" onClick={() => onAdd(null)}>
                    <PlusIcon aria-hidden="true" />
                    Tambah item
                </Button>
            )}
        </article>
    );
}
