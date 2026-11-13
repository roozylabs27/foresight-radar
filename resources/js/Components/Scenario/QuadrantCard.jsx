import { Fragment, useEffect, useState } from "react";
import { CircleCheckIcon, EyeIcon, LoaderCircleIcon, PencilIcon, UndoIcon } from "lucide-react";

import DiscardChangesDialog from "@/Components/DiscardChangesDialog";
import HintTooltip from "@/Components/HintTooltip";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from "@/Components/ui/sheet";
import { Textarea } from "@/Components/ui/textarea";
import { useDraft } from "@/hooks/use-draft";
import { providerLabel } from "@/lib/llm";
import { cn } from "@/lib/utils";

/** Provenance of a quadrant: written by an LLM, a template skeleton, or edited by an analyst. */
export function ProvenanceBadges({ quadrant }) {
    return (
        <span className="flex flex-wrap gap-1">
            <ProvenanceBadge origin={quadrant.origin} originLabel={quadrant.origin === "ai" ? providerLabel(quadrant.provider) : undefined} />
            {quadrant.edited && <span className={cn(statusBadge(), "bg-warning-soft text-warning")}>Diedit analis</span>}
            {quadrant.stale_reason && (
                <HintTooltip content={quadrant.stale_reason}>
                    <button type="button" className={cn(statusBadge(), "bg-warning-soft text-warning")}>
                        Perlu ditinjau
                    </button>
                </HintTooltip>
            )}
        </span>
    );
}

/** Review badge and, for managers, the next review actions of a quadrant with a narrative. */
function ReviewControls({ quadrant, canManage, onReview }) {
    const [busy, setBusy] = useState(false);
    const status = quadrant.review_status ?? "draft";
    const act = (next) => async () => {
        setBusy(true);
        await onReview(next);
        setBusy(false);
    };

    return (
        <div className="flex flex-wrap items-center gap-2 border-t border-border pt-3">
            <ProvenanceBadge review={status} />
            {quadrant.reviewed_by && status !== "draft" && <span className="text-[11px] text-muted-foreground">oleh {quadrant.reviewed_by}</span>}
            {canManage && onReview && (
                <div className="ml-auto flex flex-wrap gap-1.5">
                    {status === "draft" && (
                        <Button size="sm" variant="outline" disabled={busy} onClick={act("reviewed")}>
                            <EyeIcon aria-hidden="true" />
                            Tandai ditinjau
                        </Button>
                    )}
                    {status !== "approved" && (
                        <Button size="sm" disabled={busy} onClick={act("approved")}>
                            <CircleCheckIcon aria-hidden="true" />
                            Setujui
                        </Button>
                    )}
                    {status !== "draft" && (
                        <Button size="sm" variant="ghost" disabled={busy} onClick={act("draft")}>
                            <UndoIcon aria-hidden="true" />
                            Kembalikan ke draf
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

/** A [Sx] citation that opens the quoted evidence and its source. */
function CitationChip({ refName, evidence }) {
    const item = evidence[refName];

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label={`Bukti ${refName}${item?.title ? `: ${item.title}` : ""}`}
                    className="mx-0.5 inline-flex cursor-pointer items-center rounded border-0 bg-accent px-1 align-baseline text-[11px] font-semibold text-primary hover:bg-primary hover:text-primary-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {refName}
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-80 text-xs" align="start">
                {item ? (
                    <div className="flex flex-col gap-2">
                        <span className="font-semibold text-foreground">
                            {refName} · {item.title}
                        </span>
                        {item.evidence_quote && (
                            <blockquote className="m-0 border-0 border-l-[3px] border-solid border-primary bg-muted/60 px-2 py-1 italic">
                                “{item.evidence_quote}”
                            </blockquote>
                        )}
                        <span className="text-muted-foreground">
                            Sumber: {item.source_title ?? "-"}
                            {item.source_publisher ? ` (${item.source_publisher})` : ""}
                        </span>
                        {item.source_url && (
                            <a
                                href={item.source_url}
                                target="_blank"
                                rel="noreferrer"
                                className="break-all text-primary underline-offset-4 hover:underline"
                            >
                                {item.source_url}
                            </a>
                        )}
                    </div>
                ) : (
                    <span className="text-muted-foreground">Bukti {refName} tidak ditemukan.</span>
                )}
            </PopoverContent>
        </Popover>
    );
}

/** Narrative text with each [Sx] turned into a citation chip. */
function CitedText({ text, evidence }) {
    const parts = String(text ?? "").split(/(\[S\d+\])/g);

    return (
        <p className="m-0 text-[13px] leading-relaxed whitespace-pre-line text-foreground">
            {parts.map((part, index) => {
                const match = part.match(/^\[(S\d+)\]$/);
                return match ? (
                    <CitationChip key={index} refName={match[1]} evidence={evidence} />
                ) : (
                    <Fragment key={index}>{part}</Fragment>
                );
            })}
        </p>
    );
}

/** Statement kinds of a typed narrative (roadmap R2.7), from evidence to the model's own reading. */
const STATEMENT_KINDS = {
    fact: { short: "F", label: "Fakta dari bukti", className: "bg-success-soft text-success" },
    analysis: { short: "An", label: "Analisis atas bukti", className: "bg-primary/10 text-primary" },
    assumption: { short: "As", label: "Asumsi skenario", className: "bg-warning-soft text-warning" },
    interpretation: { short: "I", label: "Interpretasi model", className: "bg-muted text-muted-foreground" },
};

function KindMarker({ kind }) {
    const meta = STATEMENT_KINDS[kind] ?? STATEMENT_KINDS.interpretation;
    return (
        <abbr
            title={meta.label}
            className={cn("mr-1 inline-flex h-4 min-w-4 items-center justify-center rounded px-1 align-[1px] text-[10px] font-semibold no-underline", meta.className)}
        >
            {meta.short}
        </abbr>
    );
}

/** A typed narrative: each sentence carries its kind marker and its own citation chips. */
function StatementText({ statements, evidence }) {
    const used = Object.keys(STATEMENT_KINDS).filter((kind) => statements.some((row) => row.kind === kind));

    return (
        <div className="flex flex-col gap-1.5">
            <p className="m-0 text-[13px] leading-relaxed text-foreground">
                {statements.map((row, index) => (
                    <Fragment key={index}>
                        <KindMarker kind={row.kind} />
                        {row.text}
                        {row.refs.map((ref) => (
                            <CitationChip key={ref} refName={ref} evidence={evidence} />
                        ))}{" "}
                    </Fragment>
                ))}
            </p>
            <p className="m-0 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground" aria-label="Keterangan jenis pernyataan">
                {used.map((kind) => (
                    <span key={kind} className="inline-flex items-center">
                        <KindMarker kind={kind} />
                        {STATEMENT_KINDS[kind].label}
                    </span>
                ))}
            </p>
        </div>
    );
}

function EditSheet({ quadrant, open, titleLocked, onOpenChange, onSave }) {
    const { values, setValues, reset, dirty } = useDraft(null);
    const [saving, setSaving] = useState(false);
    const [asking, setAsking] = useState(false);
    const requestClose = () => (dirty ? setAsking(true) : onOpenChange(false));

    useEffect(() => {
        if (open) {
            reset({
                title: quadrant.title ?? "",
                narrative: quadrant.narrative ?? "",
                implications: quadrant.implications ?? "",
                early_indicators: (quadrant.early_indicators ?? []).join("\n"),
            });
        }
    }, [open, quadrant, reset]);

    const set = (key) => (event) => setValues((current) => ({ ...current, [key]: event.target.value }));
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        const ok = await onSave({
            title: values.title,
            narrative: values.narrative,
            implications: values.implications || null,
            early_indicators: values.early_indicators
                .split("\n")
                .map((line) => line.trim())
                .filter(Boolean),
        });
        setSaving(false);
        if (ok) onOpenChange(false);
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !saving && (next ? onOpenChange(true) : requestClose())}>
            <DiscardChangesDialog open={asking} onOpenChange={setAsking} onDiscard={() => onOpenChange(false)} />
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Sunting kuadran</SheetTitle>
                    <SheetDescription>
                        {quadrant.poles.join(" × ")}. Kutip bukti dengan menulis [S1], [S2], dan seterusnya.
                    </SheetDescription>
                </SheetHeader>
                {values && (
                    <form id={`quadrant-${quadrant.id}`} onSubmit={submit} className="flex flex-col gap-4 px-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`${quadrant.id}-title`}>Judul</Label>
                            <Input id={`${quadrant.id}-title`} value={values.title} onChange={set("title")} disabled={titleLocked} required maxLength={255} />
                            {titleLocked && <p className="m-0 text-xs text-muted-foreground">Nama dikunci oleh persetujuan kerangka (G5).</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`${quadrant.id}-narrative`}>Narasi</Label>
                            <Textarea
                                id={`${quadrant.id}-narrative`}
                                value={values.narrative}
                                onChange={set("narrative")}
                                required
                                maxLength={5000}
                                rows={10}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`${quadrant.id}-implications`}>Implikasi</Label>
                            <Textarea
                                id={`${quadrant.id}-implications`}
                                value={values.implications}
                                onChange={set("implications")}
                                maxLength={3000}
                                rows={4}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`${quadrant.id}-indicators`}>Indikator awal (satu per baris)</Label>
                            <Textarea
                                id={`${quadrant.id}-indicators`}
                                value={values.early_indicators}
                                onChange={set("early_indicators")}
                                rows={4}
                            />
                        </div>
                    </form>
                )}
                <SheetFooter>
                    <Button type="submit" form={`quadrant-${quadrant.id}`} disabled={saving}>
                        {saving && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        Simpan
                    </Button>
                    <Button type="button" variant="outline" onClick={requestClose} disabled={saving}>
                        Batal
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}

/**
 * One future of the 2x2 matrix: its poles, narrative with citation chips,
 * implications and early indicators. `evidence` maps "S1".. to signals.
 * `onSave(values)` resolves true when the edit was stored; `onReview(status)` changes the review
 * state. `titleLocked` keeps the name read-only (approved framework, G5).
 */
export default function QuadrantCard({ quadrant, evidence, canManage = false, titleLocked = false, onSave, onReview, className }) {
    const [editing, setEditing] = useState(false);

    return (
        <article
            aria-label={`Kuadran ${quadrant.poles.join(" dan ")}`}
            className={cn("flex min-w-0 flex-col gap-3 rounded-lg border border-solid border-border bg-card p-4", className)}
        >
            <header className="flex flex-col gap-1.5">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <span className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        {quadrant.poles.join(" × ")}
                    </span>
                    <ProvenanceBadges quadrant={quadrant} />
                </div>
                <h3 className="m-0 text-[15px] font-bold text-foreground">{quadrant.title}</h3>
                {quadrant.summary && <p className="m-0 text-xs text-muted-foreground">{quadrant.summary}</p>}
            </header>

            {quadrant.statements?.length > 0 ? (
                <StatementText statements={quadrant.statements} evidence={evidence} />
            ) : quadrant.narrative ? (
                <CitedText text={quadrant.narrative} evidence={evidence} />
            ) : (
                <p className="m-0 text-[13px] text-muted-foreground italic">Narasi belum dibuat.</p>
            )}

            {quadrant.implications && (
                <div className="flex flex-col gap-1">
                    <span className="text-xs font-semibold text-foreground">Implikasi</span>
                    <p className="m-0 text-xs text-muted-foreground">{quadrant.implications}</p>
                </div>
            )}

            {quadrant.early_indicators?.length > 0 && (
                <div className="flex flex-col gap-1">
                    <span className="text-xs font-semibold text-foreground">Indikator awal</span>
                    <ul className="m-0 flex list-disc flex-col gap-0.5 pl-4 text-xs text-muted-foreground">
                        {quadrant.early_indicators.map((indicator, index) => (
                            <li key={index}>{indicator}</li>
                        ))}
                    </ul>
                </div>
            )}

            {canManage && (
                <>
                    <Button variant="outline" size="sm" className="self-start" onClick={() => setEditing(true)}>
                        <PencilIcon aria-hidden="true" />
                        Sunting
                    </Button>
                    <EditSheet quadrant={quadrant} open={editing} titleLocked={titleLocked} onOpenChange={setEditing} onSave={onSave} />
                </>
            )}

            {quadrant.narrative && <ReviewControls quadrant={quadrant} canManage={canManage} onReview={onReview} />}
        </article>
    );
}
