import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Button } from "@/Components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { formatSpread, medianScores } from "@/domain/scenarioPlanning";

const SCORES = Array.from({ length: 10 }, (_, index) => String(index + 1));

const HINTS = {
    impact: "1–3 kecil atau lokal · 4–6 berarti bagi sebagian bisnis · 7–8 besar bagi seluruh bisnis · 9–10 bisa mengubah industri",
    uncertainty: "1–3 sudah pasti · 4–6 kemungkinan besar, waktu atau skala terbuka · 7–8 diperdebatkan · 9–10 spekulatif",
};

const MODES = {
    consensus: { description: "Skor kesepakatan tim untuk latihan ini. Penilaian di tingkat organisasi tidak berubah.", button: "Simpan kesepakatan" },
    mine: { description: "Skor pribadi Anda. Koordinator melihat sebaran skor peserta sebelum menetapkan kesepakatan.", button: "Simpan skor saya" },
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

/** The participants' scores behind a consensus, with a shortcut to start from their medians. */
function SpreadSummary({ spread, onUseMedian }) {
    if (!spread?.count) return <p className="m-0 text-xs text-muted-foreground">Belum ada skor peserta untuk driving force ini.</p>;

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-muted/40 p-3 text-xs">
            <p className="m-0 font-semibold text-foreground">
                Skor peserta ({spread.count}): impact {formatSpread(spread.impact)} · uncertainty {formatSpread(spread.uncertainty)}
            </p>
            <ul className="m-0 flex list-none flex-col gap-0.5 p-0 text-muted-foreground">
                {spread.assessors.map((row, index) => (
                    <li key={index}>{row.name}: impact {row.impact} · uncertainty {row.uncertainty}</li>
                ))}
            </ul>
            <Button type="button" size="sm" variant="outline" className="self-start" onClick={onUseMedian}>
                Pakai median
            </Button>
        </div>
    );
}

/**
 * Impact and uncertainty of one driving force, with rationale: the team's consensus or, with
 * `mode = "mine"`, the signed-in participant's own score (roadmap R2.1).
 * `onSave(values)` resolves to the 422 errors or undefined on success.
 */
export default function ScoreDialog({ force, mode = "consensus", onOpenChange, loading, onSave }) {
    const [values, setValues] = useState({});
    const [errors, setErrors] = useState({});
    const copy = MODES[mode];

    // Reset only when another force or mode is opened, not on the props refresh after a validation error.
    const resetForm = useEffectEvent(() => {
        if (!force) return;
        const source = mode === "mine" ? force.mine ?? {} : force;
        setErrors({});
        setValues({
            impact: source.impact ? String(source.impact) : "",
            uncertainty: source.uncertainty ? String(source.uncertainty) : "",
            impact_rationale: source.impact_rationale ?? "",
            uncertainty_rationale: source.uncertainty_rationale ?? "",
        });
    });
    useEffect(() => resetForm(), [force?.id, mode]);

    const set = (name) => (value) => setValues((current) => ({ ...current, [name]: value }));
    const applyMedian = () => {
        const median = medianScores(force?.individual);
        if (median) setValues((current) => ({ ...current, impact: String(median.impact), uncertainty: String(median.uncertainty) }));
    };
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
            description={copy.description}
            open={Boolean(force)}
            onOpenChange={onOpenChange}
            onOk={save}
            isEditMode
            btnText={copy.button}
            cancelText="Batal"
            loading={loading}
            width={560}
        >
            <div className="flex flex-col gap-4">
                {mode === "consensus" && <SpreadSummary spread={force?.individual} onUseMedian={applyMedian} />}
                <ScoreField name="impact" label="Impact" values={values} set={set} errors={errors} />
                <ScoreField name="uncertainty" label="Uncertainty" values={values} set={set} errors={errors} />
            </div>
        </FormDialog>
    );
}
