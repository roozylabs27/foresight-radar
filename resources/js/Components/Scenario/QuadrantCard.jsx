import { Fragment, useEffect, useState } from "react";
import { LoaderCircleIcon, PencilIcon } from "lucide-react";

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
import { providerLabel } from "@/lib/llm";
import { cn } from "@/lib/utils";

/** Provenance of a quadrant: written by an LLM, a template skeleton, or edited by an analyst. */
export function ProvenanceBadges({ quadrant }) {
    return (
        <span className="flex flex-wrap gap-1">
            {quadrant.is_ai_generated ? (
                <span className={cn(statusBadge(), "bg-primary text-primary-foreground")}>{providerLabel(quadrant.provider)}</span>
            ) : (
                <span className={cn(statusBadge(), "bg-muted text-muted-foreground")}>Kerangka tanpa AI</span>
            )}
            {quadrant.edited && <span className={cn(statusBadge(), "bg-warning-soft text-warning")}>Diedit analis</span>}
        </span>
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

function EditSheet({ quadrant, open, onOpenChange, onSave }) {
    const [values, setValues] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setValues({
                title: quadrant.title ?? "",
                narrative: quadrant.narrative ?? "",
                implications: quadrant.implications ?? "",
                early_indicators: (quadrant.early_indicators ?? []).join("\n"),
            });
        }
    }, [open, quadrant]);

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
        <Sheet open={open} onOpenChange={(next) => !saving && onOpenChange(next)}>
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
                            <Input id={`${quadrant.id}-title`} value={values.title} onChange={set("title")} required maxLength={255} />
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
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
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
 * `onSave(values)` resolves true when the edit was stored.
 */
export default function QuadrantCard({ quadrant, evidence, canManage = false, onSave, className }) {
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
            </header>

            <CitedText text={quadrant.narrative} evidence={evidence} />

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
                    <EditSheet quadrant={quadrant} open={editing} onOpenChange={setEditing} onSave={onSave} />
                </>
            )}
        </article>
    );
}
