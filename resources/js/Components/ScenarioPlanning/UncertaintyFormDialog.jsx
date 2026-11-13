import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { statusBadge } from "@/Components/StatusBadge";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { SCORE_CLASSES, classifyScore } from "@/domain/scenarioPlanning";

const EMPTY = { name: "", description: "", forces: [], low_pole_label: "", high_pole_label: "", selection_rationale: "" };

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Propose or edit a critical uncertainty: one or more exercise forces under a
 * name, two opposing poles and the reason. `uncertainty` null with `open` true
 * creates a new one. `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function UncertaintyFormDialog({ open, uncertainty, candidates, threshold, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});

    // Reset when the dialog opens or another uncertainty is chosen, not when props are refreshed
    // after a validation error (that would wipe the input and the errors).
    const resetForm = useEffectEvent(() => {
        if (!open) return;
        setErrors({});
        setValues(uncertainty ? { ...EMPTY, ...uncertainty, description: uncertainty.description ?? "", forces: uncertainty.forces.map((force) => force.id) } : EMPTY);
    });
    useEffect(() => resetForm(), [open, uncertainty?.id]);

    const set = (name) => (event) => setValues((current) => ({ ...current, [name]: event.target.value }));
    const toggle = (id) => setValues((current) => ({ ...current, forces: current.forces.includes(id) ? current.forces.filter((item) => item !== id) : [...current.forces, id] }));
    const save = async () => {
        const result = await onSave(values);
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={uncertainty ? "Ubah usulan critical uncertainty" : "Usulan critical uncertainty"}
            description="Satu critical uncertainty boleh menggabungkan beberapa driving force (mis. Government Regulation = Political + Legislative)."
            open={open}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan usulan"
            cancelText="Batal"
            loading={loading}
            className="sm:max-w-2xl"
        >
            <div className="flex flex-col gap-5">
                <FormField id="cu-name" label="Nama" error={error(errors, "name")}>
                    <Input id="cu-name" value={values.name} onChange={set("name")} maxLength={120} placeholder="Contoh: Government Regulation" />
                </FormField>
                <FormField id="cu-forces" label="Driving force yang digabungkan" error={error(errors, "forces")} group>
                    <ul aria-labelledby="cu-forces-label" className="m-0 flex max-h-56 list-none flex-col gap-1 overflow-y-auto p-0">
                        {candidates.map((force) => {
                            const kind = SCORE_CLASSES[classifyScore(force.impact, force.uncertainty, threshold)];
                            return (
                                <li key={force.id}>
                                    <label className="flex cursor-pointer items-center gap-3 rounded-md border border-border p-2 hover:bg-muted/40">
                                        <input type="checkbox" className="size-4 accent-primary" checked={values.forces.includes(force.id)} onChange={() => toggle(force.id)} />
                                        <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2 text-sm">
                                            <span className="font-medium text-foreground">{force.keyword}</span>
                                            <span className="text-xs text-muted-foreground">{force.dimension} · I {force.impact} · U {force.uncertainty}</span>
                                            <span className={statusBadge({ tone: kind.tone })}>{kind.label}</span>
                                        </span>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                    <p className="m-0 text-[11px] text-muted-foreground">Driving force yang ditandai predetermined tidak ditampilkan.</p>
                </FormField>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField id="cu-low" label="Kutub rendah" error={error(errors, "low_pole_label")}>
                        <Input id="cu-low" value={values.low_pole_label} onChange={set("low_pole_label")} maxLength={120} placeholder="Contoh: Unfavorable" />
                    </FormField>
                    <FormField id="cu-high" label="Kutub tinggi" error={error(errors, "high_pole_label")}>
                        <Input id="cu-high" value={values.high_pole_label} onChange={set("high_pole_label")} maxLength={120} placeholder="Contoh: Favorable" />
                    </FormField>
                </div>
                <FormField id="cu-rationale" label="Alasan pemilihan" error={error(errors, "selection_rationale")}>
                    <Textarea id="cu-rationale" value={values.selection_rationale} onChange={set("selection_rationale")} maxLength={2000} rows={3} placeholder="Mengapa ketidakpastian ini paling menentukan masa depan focal issue?" />
                </FormField>
                <FormField id="cu-description" label="Deskripsi" required={false} error={error(errors, "description")}>
                    <Textarea id="cu-description" value={values.description} onChange={set("description")} maxLength={1000} rows={2} />
                </FormField>
            </div>
        </FormDialog>
    );
}
