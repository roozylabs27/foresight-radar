import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { CONSISTENCY_STATUSES } from "@/domain/scenarioPlanning";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Consistency sign-off of one quadrant (roadmap R2.8). `quadrant` is the comparison column or null
 * when closed. A note is needed for "issues" and when rule checks still warn.
 */
export default function ConsistencyDialog({ quadrant, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState({ status: "consistent", note: "" });
    const [errors, setErrors] = useState({});
    const warnings = quadrant?.consistency.checks ?? [];

    const resetForm = useEffectEvent(() => {
        if (!quadrant) return;
        setErrors({});
        setValues({ status: quadrant.consistency.status ?? "consistent", note: quadrant.consistency.note ?? "" });
    });
    useEffect(() => resetForm(), [quadrant?.id]);

    const save = async () => {
        const result = await onSave(quadrant.id, { status: values.status, note: values.note || null });
        if (result) return setErrors(result);
        onOpenChange(false);
    };
    const noteRequired = values.status === "issues" || warnings.length > 0;

    return (
        <FormDialog
            title="Tinjau konsistensi kuadran"
            description={quadrant ? `${quadrant.title ?? quadrant.position} · ${quadrant.poles.join(" × ")}` : undefined}
            open={quadrant !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan tinjauan"
            cancelText="Batal"
            loading={loading}
        >
            <div className="flex flex-col gap-5">
                {warnings.length > 0 && (
                    <ul className="m-0 flex flex-col gap-1 rounded-md bg-warning-soft p-3 pl-7 text-sm text-warning">
                        {warnings.map((row, index) => <li key={index}>{row.message}</li>)}
                    </ul>
                )}
                <FormField id="consistency-status" label="Hasil tinjauan" error={error(errors, "status")} group>
                    <ToggleGroup type="single" variant="outline" value={values.status} onValueChange={(value) => value && setValues((current) => ({ ...current, status: value }))} aria-labelledby="consistency-status-label">
                        {Object.entries(CONSISTENCY_STATUSES).map(([value, status]) => (
                            <ToggleGroupItem key={value} value={value} className="px-4">
                                {status.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </FormField>
                <FormField id="consistency-note" label="Catatan" required={noteRequired} error={error(errors, "note")}>
                    <Textarea
                        id="consistency-note"
                        value={values.note}
                        onChange={(event) => setValues((current) => ({ ...current, note: event.target.value }))}
                        maxLength={1000}
                        rows={3}
                        placeholder={values.status === "issues" ? "Kondisi mana yang saling bertentangan?" : "Mengapa kuadran ini tetap konsisten meski ada peringatan?"}
                    />
                </FormField>
            </div>
        </FormDialog>
    );
}
