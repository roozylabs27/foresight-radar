import { useEffect, useState } from "react";
import { CheckIcon, XIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import FormField from "@/Components/FormField";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import { cn } from "@/lib/utils";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Roadmap R4.1 (deck slides 2 and 26): pick one of the four scenarios as the preferred future, with
 * a rationale. Viewers see the choice; coordinators change or clear it.
 */
export default function PreferredFuture({ quadrants, preferred, canManage, busy, onSave, onClear }) {
    const [selected, setSelected] = useState(preferred?.id ?? null);
    const [rationale, setRationale] = useState(preferred?.rationale ?? "");
    const [errors, setErrors] = useState({});

    useEffect(() => {
        setSelected(preferred?.id ?? null);
        setRationale(preferred?.rationale ?? "");
    }, [preferred?.id, preferred?.rationale]);

    const save = async () => setErrors((await onSave({ quadrant: selected, rationale })) ?? {});
    const dirty = selected !== (preferred?.id ?? null) || rationale !== (preferred?.rationale ?? "");

    return (
        <div className="flex flex-col gap-4">
            <div role="radiogroup" aria-label="Masa depan pilihan" className="grid gap-2 sm:grid-cols-2">
                {quadrants.map((quadrant) => {
                    const active = selected === quadrant.id;
                    return (
                        <button
                            key={quadrant.id}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            disabled={!canManage || busy}
                            onClick={() => setSelected(quadrant.id)}
                            className={cn("flex min-w-0 flex-col gap-1 rounded-lg border p-3 text-left transition-colors disabled:cursor-default", active ? "border-primary bg-primary/10" : "border-border enabled:hover:bg-muted/50")}
                        >
                            <span className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                {active && <CheckIcon aria-hidden="true" className="size-4 text-primary" />}
                                {quadrant.title}
                            </span>
                            {quadrant.summary && <span className="text-xs text-muted-foreground">{quadrant.summary}</span>}
                        </button>
                    );
                })}
            </div>
            {errors.quadrant && <p role="alert" className="m-0 text-sm text-danger">{[].concat(errors.quadrant)[0]}</p>}
            {canManage ? (
                <>
                    <FormField id="preferred-rationale" label="Mengapa masa depan ini yang diupayakan" error={error(errors, "rationale")}>
                        <Textarea id="preferred-rationale" value={rationale} onChange={(event) => setRationale(event.target.value)} maxLength={2000} rows={3} />
                    </FormField>
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" disabled={busy || !selected || !dirty} onClick={save}>
                            <CheckIcon aria-hidden="true" />
                            Simpan masa depan pilihan
                        </Button>
                        {preferred && (
                            <ConfirmDialog title="Batalkan masa depan pilihan?" description="Milestone yang sudah disusun tetap disimpan." confirmText="Batalkan" onConfirm={onClear}>
                                <Button size="sm" variant="ghost">
                                    <XIcon aria-hidden="true" />
                                    Batalkan pilihan
                                </Button>
                            </ConfirmDialog>
                        )}
                    </div>
                </>
            ) : (
                preferred?.rationale && <p className="m-0 text-sm text-foreground">{preferred.rationale}</p>
            )}
            {preferred?.chosen_by && <p className="m-0 text-xs text-muted-foreground">Dipilih oleh {preferred.chosen_by}{preferred.chosen_at && `, ${new Date(preferred.chosen_at).toLocaleDateString("id-ID")}`}</p>}
        </div>
    );
}
