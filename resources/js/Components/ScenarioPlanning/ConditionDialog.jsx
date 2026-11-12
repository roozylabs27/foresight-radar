import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { CONDITION_KINDS } from "@/domain/scenarioPlanning";
import { useDraft } from "@/hooks/use-draft";

const EMPTY = { kind: "assumption", text: "", axis: null, force: null };

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Add or edit one condition of a quadrant. `target` is `{ quadrant, condition }` (condition null to
 * add) or null when closed. `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function ConditionDialog({ target, axes, forces, loading, onOpenChange, onSave }) {
    const { values, setValues, reset, dirty } = useDraft(EMPTY);
    const [errors, setErrors] = useState({});
    const condition = target?.condition;

    useEffect(() => {
        if (!target) return;
        setErrors({});
        reset(condition ? { kind: condition.kind, text: condition.text, axis: condition.axis, force: condition.force?.id ?? null } : EMPTY);
    }, [target?.quadrant.id, condition?.id]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        const result = await onSave(values);
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={condition ? "Ubah kondisi" : "Tambah kondisi"}
            description={target ? `${target.quadrant.title} · ${target.quadrant.poles.join(" × ")}` : undefined}
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan kondisi"
            cancelText="Batal"
            loading={loading}
            dirty={dirty}
        >
            <div className="flex flex-col gap-5">
                <FormField id="condition-kind" label="Jenis" error={error(errors, "kind")} group>
                    <ToggleGroup type="single" variant="outline" value={values.kind} onValueChange={(value) => value && set("kind", value)} aria-labelledby="condition-kind-label" className="flex-wrap">
                        {Object.entries(CONDITION_KINDS).map(([value, kind]) => (
                            <ToggleGroupItem key={value} value={value} className="px-3">
                                {kind.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <p className="m-0 text-xs text-muted-foreground">{CONDITION_KINDS[values.kind].hint}</p>
                </FormField>
                {values.kind === "pole_expression" && (
                    <FormField id="condition-axis" label="Sumbu" error={error(errors, "axis")}>
                        <Select value={values.axis ?? ""} onValueChange={(value) => set("axis", value)}>
                            <SelectTrigger id="condition-axis" className="w-full">
                                <SelectValue placeholder="Pilih sumbu" />
                            </SelectTrigger>
                            <SelectContent>
                                {axes.map((axis) => (
                                    <SelectItem key={axis.axis} value={axis.axis}>
                                        {axis.axis} · {axis.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                )}
                {values.kind === "force_behaviour" && (
                    <FormField id="condition-force" label="Driving force" error={error(errors, "force")}>
                        <Select value={values.force ?? ""} onValueChange={(value) => set("force", value)}>
                            <SelectTrigger id="condition-force" className="w-full">
                                <SelectValue placeholder={forces.length ? "Pilih driving force" : "Tidak ada driving force di luar sumbu"} />
                            </SelectTrigger>
                            <SelectContent>
                                {forces.map((force) => (
                                    <SelectItem key={force.id} value={force.id}>
                                        {force.keyword}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                )}
                <FormField id="condition-text" label="Kondisi" error={error(errors, "text")}>
                    <Textarea id="condition-text" value={values.text} onChange={(event) => set("text", event.target.value)} maxLength={500} rows={3} placeholder="Contoh: Insentif TKDN diperluas ke komponen baterai." />
                </FormField>
                {errors.conditions && <p role="alert" className="m-0 text-sm text-danger">{errors.conditions}</p>}
            </div>
        </FormDialog>
    );
}
