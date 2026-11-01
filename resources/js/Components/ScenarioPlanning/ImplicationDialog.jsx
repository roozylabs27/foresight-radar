import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { IMPACT_LEVELS, IMPLICATION_TYPES } from "@/domain/scenarioPlanning";

const EMPTY = { type: "opportunity", title: "", description: "", impact_level: "", priority: "", forces: [], conditions: [] };
const NONE = "none";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/** Checkbox list of `{ id, label }` options bound to an array of ids. */
function Choices({ id, options, value, onChange, empty }) {
    if (options.length === 0) return <p className="m-0 text-xs text-muted-foreground">{empty}</p>;
    const toggle = (optionId) => onChange(value.includes(optionId) ? value.filter((item) => item !== optionId) : [...value, optionId]);

    return (
        <ul aria-labelledby={`${id}-label`} className="m-0 flex max-h-36 list-none flex-col gap-1 overflow-y-auto p-0">
            {options.map((option) => (
                <li key={option.id}>
                    <label className="flex cursor-pointer items-start gap-2 rounded px-1 py-0.5 text-sm hover:bg-muted/50">
                        <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={value.includes(option.id)} onChange={() => toggle(option.id)} />
                        <span>{option.label}</span>
                    </label>
                </li>
            ))}
        </ul>
    );
}

/**
 * Add or edit an opportunity or challenge. `target` is `{ quadrant, item, prefill }` or null when
 * closed; `prefill` seeds a new item (e.g. from the narrative text). `onSave` resolves to the 422 errors or undefined.
 */
export default function ImplicationDialog({ target, forces, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const item = target?.item;

    useEffect(() => {
        if (!target) return;
        setErrors({});
        setValues(item
            ? { ...EMPTY, ...item, type: item.type ?? "opportunity", description: item.description ?? "", impact_level: item.impact_level ?? "", priority: item.priority ? String(item.priority) : "", forces: item.forces.map((force) => force.id), conditions: item.conditions.map((condition) => condition.id) }
            : { ...EMPTY, ...target.prefill });
    }, [target?.quadrant.id, item?.id, target?.prefill]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        const result = await onSave({ ...values, impact_level: values.impact_level || null, priority: values.priority ? Number(values.priority) : null });
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={item ? (item.type ? "Ubah item" : "Tinjau usulan") : "Tambah peluang atau tantangan"}
            description={target ? `${target.quadrant.title} · ${target.quadrant.poles.join(" × ")}` : undefined}
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan item"
            cancelText="Batal"
            loading={loading}
            className="sm:max-w-2xl"
        >
            <div className="flex flex-col gap-5">
                <FormField id="implication-type" label="Jenis" error={error(errors, "type")} group>
                    <ToggleGroup type="single" variant="outline" value={values.type} onValueChange={(value) => value && set("type", value)} aria-labelledby="implication-type-label">
                        {Object.entries(IMPLICATION_TYPES).map(([value, type]) => (
                            <ToggleGroupItem key={value} value={value} className="px-4">
                                {type.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </FormField>
                <FormField id="implication-title" label="Judul" error={error(errors, "title")}>
                    <Input id="implication-title" value={values.title} onChange={(event) => set("title", event.target.value)} maxLength={160} placeholder="Contoh: Pasar EV domestik tumbuh cepat" />
                </FormField>
                <FormField id="implication-description" label="Dampak bagi organisasi" required={false} error={error(errors, "description")}>
                    <Textarea id="implication-description" value={values.description} onChange={(event) => set("description", event.target.value)} maxLength={2000} rows={3} />
                </FormField>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="implication-impact" label="Tingkat dampak" required={false} error={error(errors, "impact_level")}>
                        <Select value={values.impact_level || NONE} onValueChange={(value) => set("impact_level", value === NONE ? "" : value)}>
                            <SelectTrigger id="implication-impact" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Belum dinilai</SelectItem>
                                {Object.entries(IMPACT_LEVELS).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="implication-priority" label="Prioritas" required={false} error={error(errors, "priority")}>
                        <Select value={values.priority || NONE} onValueChange={(value) => set("priority", value === NONE ? "" : value)}>
                            <SelectTrigger id="implication-priority" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Belum ditentukan</SelectItem>
                                {[1, 2, 3, 4, 5].map((rank) => (
                                    <SelectItem key={rank} value={String(rank)}>
                                        P{rank}
                                        {rank === 1 ? " (tertinggi)" : ""}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
                <FormField id="implication-forces" label="Driving force terkait" required={false} error={error(errors, "forces")} group>
                    <Choices id="implication-forces" options={forces.map((force) => ({ id: force.id, label: force.keyword }))} value={values.forces} onChange={(value) => set("forces", value)} empty="Belum ada driving force yang disertakan." />
                </FormField>
                <FormField id="implication-conditions" label="Kondisi penyebab" required={false} error={error(errors, "conditions")} group>
                    <Choices id="implication-conditions" options={(target?.quadrant.conditions ?? []).map((condition) => ({ id: condition.id, label: condition.text }))} value={values.conditions} onChange={(value) => set("conditions", value)} empty="Kuadran ini belum punya kondisi (langkah 5)." />
                </FormField>
                {errors.implications && <p role="alert" className="m-0 text-sm text-danger">{errors.implications}</p>}
            </div>
        </FormDialog>
    );
}
