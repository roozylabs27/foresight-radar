import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { MILESTONE_STATUSES } from "@/domain/scenarioPlanning";

const NONE = "none";
const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

function OptionalSelect({ id, value, onChange, placeholder, options }) {
    return (
        <Select value={value || NONE} onValueChange={(next) => onChange(next === NONE ? "" : next)}>
            <SelectTrigger id={id} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Add or edit one backcasting milestone (roadmap R4.1). `target` is `{ milestone }` (null milestone
 * for a new one) or null when closed. `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function MilestoneDialog({ target, targetYear, owners, responses, indicators, loading, onOpenChange, onSave }) {
    const empty = { year: String(targetYear), title: "", description: "", status: "planned", owner: "", response: "", indicator: "" };
    const [values, setValues] = useState(empty);
    const [errors, setErrors] = useState({});
    const milestone = target?.milestone;

    useEffect(() => {
        if (!target) return;
        setErrors({});
        setValues(milestone ? {
            year: String(milestone.year), title: milestone.title, description: milestone.description ?? "", status: milestone.status,
            owner: milestone.owner?.id ?? "", response: milestone.response?.id ?? "", indicator: milestone.indicator?.id ?? "",
        } : empty);
    }, [target, milestone?.id]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        const blank = (value) => (value === "" ? null : value);
        const result = await onSave({ ...values, description: blank(values.description), owner: blank(values.owner), response: blank(values.response), indicator: blank(values.indicator) });
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={milestone ? "Ubah milestone" : "Milestone baru"}
            description={`Langkah antara tahun ini dan ${targetYear} menuju masa depan pilihan.`}
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan milestone"
            cancelText="Batal"
            loading={loading}
            className="sm:max-w-xl"
        >
            <div className="flex flex-col gap-5">
                {errors.backcasting && <p role="alert" className="m-0 text-sm text-danger">{[].concat(errors.backcasting)[0]}</p>}
                <div className="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <FormField id="milestone-year" label="Tahun" error={error(errors, "year")}>
                        <Input id="milestone-year" type="number" inputMode="numeric" min={new Date().getFullYear()} max={targetYear} value={values.year} onChange={(event) => set("year", event.target.value)} />
                    </FormField>
                    <FormField id="milestone-title" label="Milestone" error={error(errors, "title")}>
                        <Input id="milestone-title" value={values.title} onChange={(event) => set("title", event.target.value)} maxLength={200} placeholder="Contoh: Insentif untuk semua xEV terbit" />
                    </FormField>
                </div>
                <FormField id="milestone-description" label="Keterangan" required={false} error={error(errors, "description")}>
                    <Textarea id="milestone-description" value={values.description} onChange={(event) => set("description", event.target.value)} maxLength={2000} rows={2} />
                </FormField>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="milestone-status" label="Status" error={error(errors, "status")}>
                        <Select value={values.status} onValueChange={(value) => set("status", value)}>
                            <SelectTrigger id="milestone-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(MILESTONE_STATUSES).map(([value, status]) => (
                                    <SelectItem key={value} value={value}>
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="milestone-owner" label="Penanggung jawab" required={false} error={error(errors, "owner")}>
                        <OptionalSelect id="milestone-owner" value={values.owner} onChange={(value) => set("owner", value)} placeholder="Belum ditentukan" options={owners} />
                    </FormField>
                    <FormField id="milestone-response" label="Respons strategis (langkah 8)" required={false} error={error(errors, "response")}>
                        <OptionalSelect id="milestone-response" value={values.response} onChange={(value) => set("response", value)} placeholder="Tidak dikaitkan" options={responses} />
                    </FormField>
                    <FormField id="milestone-indicator" label="Indikator (langkah 9)" required={false} error={error(errors, "indicator")}>
                        <OptionalSelect id="milestone-indicator" value={values.indicator} onChange={(value) => set("indicator", value)} placeholder="Tidak dikaitkan" options={indicators} />
                    </FormField>
                </div>
            </div>
        </FormDialog>
    );
}
