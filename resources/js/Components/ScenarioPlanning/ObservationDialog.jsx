import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { INDICATOR_DIRECTIONS, crossesThreshold } from "@/domain/scenarioPlanning";
import { useDraft } from "@/hooks/use-draft";

const NONE = "none";
const OBSERVED = ["rising", "falling", "stable"];
const today = () => new Date().toISOString().slice(0, 10);
const empty = () => ({ observed_at: today(), value_numeric: "", value_text: "", direction: "", crossed_threshold: false, signal: "", note: "" });
const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/** The server decides a numeric crossing; this shows what it will decide for the value typed. */
function ThresholdPreview({ indicator, value }) {
    const crossed = crossesThreshold(indicator.threshold_operator, indicator.threshold_value, value);
    return (
        <p role="status" className="m-0 rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
            Ambang {indicator.threshold_text}
            {indicator.unit ? ` ${indicator.unit}` : ""}:{" "}
            {crossed === null ? "dihitung otomatis dari nilai angka." : crossed ? <b className="text-danger">nilai ini melewati ambang; kerangka dan respons akan ditandai untuk ditinjau.</b> : "nilai ini belum melewati ambang."}
        </p>
    );
}

/**
 * Record one observation of an indicator (roadmap R2.3): date, value or description, the direction
 * it moved, and optionally the accepted signal behind it. For an indicator with a numeric threshold
 * the server decides the crossing from the value (shown here as a preview); for a qualitative one a
 * person flags it and explains why in the note (audit U4).
 * `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function ObservationDialog({ indicator, signals, loading, onOpenChange, onSave }) {
    const { values, setValues, reset, dirty } = useDraft(empty);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (!indicator) return;
        setErrors({});
        reset(empty());
    }, [indicator?.id]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        const result = await onSave({
            ...values,
            value_numeric: values.value_numeric === "" ? null : Number(values.value_numeric),
            value_text: values.value_text || null,
            direction: values.direction || null,
            crossed_threshold: indicator?.threshold_numeric ? false : values.crossed_threshold,
            signal: values.signal || null,
            note: values.note || null,
        });
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={indicator ? `Observasi · ${indicator.name}` : "Observasi"}
            description="Anda mencatat dan mengonfirmasi observasi ini. Isi nilai angka, keterangan, atau keduanya."
            open={Boolean(indicator)}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Catat observasi"
            cancelText="Batal"
            loading={loading}
            dirty={dirty}
            width={560}
        >
            <div className="flex flex-col gap-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="observation-date" label="Tanggal" error={error(errors, "observed_at")}>
                        <Input id="observation-date" type="date" max={today()} value={values.observed_at} onChange={(event) => set("observed_at", event.target.value)} />
                    </FormField>
                    <FormField id="observation-value" label={`Nilai${indicator?.unit ? ` (${indicator.unit})` : ""}`} required={false} error={error(errors, "value_numeric")}>
                        <Input id="observation-value" type="number" step="any" value={values.value_numeric} onChange={(event) => set("value_numeric", event.target.value)} />
                    </FormField>
                </div>
                <FormField id="observation-text" label="Keterangan" required={false} error={error(errors, "value_text")}>
                    <Input id="observation-text" value={values.value_text} onChange={(event) => set("value_text", event.target.value)} maxLength={255} placeholder="Contoh: regulasi turunan terbit" />
                </FormField>
                <FormField id="observation-direction" label="Arah perubahan" required={false} error={error(errors, "direction")} group>
                    <ToggleGroup type="single" variant="outline" size="sm" value={values.direction} onValueChange={(value) => set("direction", value)} aria-labelledby="observation-direction-label" className="self-start">
                        {OBSERVED.map((direction) => (
                            <ToggleGroupItem key={direction} value={direction} className="px-3">
                                {INDICATOR_DIRECTIONS[direction].short} {INDICATOR_DIRECTIONS[direction].label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </FormField>
                {indicator?.threshold_numeric ? (
                    <ThresholdPreview indicator={indicator} value={values.value_numeric} />
                ) : (
                    <label className="flex items-start gap-2 text-sm text-foreground">
                        <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={values.crossed_threshold} onChange={(event) => set("crossed_threshold", event.target.checked)} />
                        <span>
                            Melewati ambang batas
                            <span className="block text-xs text-muted-foreground">
                                {indicator?.threshold_rule ? `Ambang: ${indicator.threshold_rule}. ` : ""}Indikator kualitatif: jelaskan alasannya di catatan.
                            </span>
                        </span>
                    </label>
                )}
                <FormField id="observation-signal" label="Sinyal pendukung" required={false} error={error(errors, "signal")}>
                    <Select value={values.signal || NONE} onValueChange={(value) => set("signal", value === NONE ? "" : value)}>
                        <SelectTrigger id="observation-signal" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>Tanpa sinyal</SelectItem>
                            {signals.map((signal) => (
                                <SelectItem key={signal.id} value={signal.id}>
                                    {signal.title}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="m-0 text-[11px] text-muted-foreground">Hanya sinyal yang sudah diterima.</p>
                </FormField>
                <FormField id="observation-note" label="Catatan" required={false} error={error(errors, "note")}>
                    <Textarea id="observation-note" value={values.note} onChange={(event) => set("note", event.target.value)} maxLength={1000} rows={2} />
                </FormField>
            </div>
        </FormDialog>
    );
}
