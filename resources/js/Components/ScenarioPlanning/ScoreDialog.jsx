import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";

const SCORES = Array.from({ length: 10 }, (_, index) => String(index + 1));

const HINTS = {
    impact: "1–3 kecil atau lokal · 4–6 berarti bagi sebagian bisnis · 7–8 besar bagi seluruh bisnis · 9–10 bisa mengubah industri",
    uncertainty: "1–3 sudah pasti · 4–6 kemungkinan besar, waktu atau skala terbuka · 7–8 diperdebatkan · 9–10 spekulatif",
};

function ScoreField({ name, label, values, set, errors }) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-border p-3">
            <FormField id={`score-${name}`} label={label} error={errors[name] ? { message: errors[name] } : undefined}>
                <Select value={values[name]} onValueChange={set(name)}>
                    <SelectTrigger id={`score-${name}`} className="w-32">
                        <SelectValue placeholder="Pilih 1–10" />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        {SCORES.map((score) => <SelectItem key={score} value={score}>{score}</SelectItem>)}
                    </SelectContent>
                </Select>
                <p className="m-0 text-[11px] text-muted-foreground">{HINTS[name]}</p>
            </FormField>
            <FormField id={`score-${name}-rationale`} label={`Alasan ${label.toLowerCase()}`} required={false}>
                <Textarea id={`score-${name}-rationale`} value={values[`${name}_rationale`]} onChange={(event) => set(`${name}_rationale`)(event.target.value)} maxLength={1000} rows={2} />
            </FormField>
        </div>
    );
}

/**
 * Consensus impact and uncertainty of one driving force, with rationale.
 * `onSave(values)` resolves to the 422 errors or undefined on success.
 */
export default function ScoreDialog({ force, onOpenChange, loading, onSave }) {
    const [values, setValues] = useState({});
    const [errors, setErrors] = useState({});

    // Reset only when another force is opened, not on the props refresh after a validation error.
    useEffect(() => {
        if (!force) return;
        setErrors({});
        setValues({
            impact: force.impact ? String(force.impact) : "",
            uncertainty: force.uncertainty ? String(force.uncertainty) : "",
            impact_rationale: force.impact_rationale ?? "",
            uncertainty_rationale: force.uncertainty_rationale ?? "",
        });
    }, [force?.id]);

    const set = (name) => (value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        const missing = Object.fromEntries(["impact", "uncertainty"].filter((name) => !values[name]).map((name) => [name, "Pilih skor 1–10."]));
        if (Object.keys(missing).length > 0) return setErrors(missing);
        const serverErrors = await onSave(values);
        if (serverErrors) return setErrors(serverErrors);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={force ? `${force.number}. ${force.force.keyword}` : "Skor"}
            description="Skor kesepakatan tim untuk latihan ini. Penilaian di tingkat organisasi tidak berubah."
            open={Boolean(force)}
            onOpenChange={onOpenChange}
            onOk={save}
            isEditMode
            btnText="Simpan skor"
            cancelText="Batal"
            loading={loading}
            width={560}
        >
            <div className="flex flex-col gap-4">
                <ScoreField name="impact" label="Impact" values={values} set={set} errors={errors} />
                <ScoreField name="uncertainty" label="Uncertainty" values={values} set={set} errors={errors} />
            </div>
        </FormDialog>
    );
}
