import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { EXTERNAL_SOURCES, INDICATOR_DIRECTIONS, INDICATOR_FREQUENCIES, INDICATOR_SOURCES, THRESHOLD_OPERATORS } from "@/domain/scenarioPlanning";

const EMPTY = {
    name: "", description: "", uncertainty: "", force: "", metric: "", unit: "", baseline_value: "", baseline_date: "",
    frequency: "quarterly", owner: "", source_type: "manual", threshold_rule: "", threshold_operator: "", threshold_value: "", status: "active", directions: {},
    external_source: "world_bank", external_series: "", external_area: "",
};
const NONE = "none";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/** Select with an explicit "none" option bound to an empty string. */
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
 * Add or edit an early warning indicator with its expected direction per quadrant. `target` is
 * `{ indicator, prefill }` or null when closed. `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function IndicatorDialog({ target, quadrants, uncertainties, forces, owners, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const indicator = target?.indicator;

    useEffect(() => {
        if (!target) return;
        setErrors({});
        setValues(indicator
            ? {
                  ...EMPTY,
                  ...Object.fromEntries(Object.entries(indicator).map(([key, value]) => [key, value ?? ""])),
                  uncertainty: indicator.uncertainty?.id ?? "",
                  force: indicator.force?.id ?? "",
                  owner: indicator.owner?.id ?? "",
                  baseline_value: indicator.baseline_value ?? "",
                  external_source: indicator.external_source || EMPTY.external_source,
                  directions: Object.fromEntries(Object.entries(indicator.directions).map(([position, row]) => [position, { direction: row.direction, note: row.note ?? "" }])),
              }
            : { ...EMPTY, ...target.prefill });
    }, [target, indicator?.id]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const setDirection = (position, patch) =>
        setValues((current) => ({ ...current, directions: { ...current.directions, [position]: { note: "", ...current.directions[position], ...patch } } }));
    const save = async () => {
        const blankToNull = (value) => (value === "" ? null : value);
        const result = await onSave({
            ...values,
            uncertainty: blankToNull(values.uncertainty),
            force: blankToNull(values.force),
            owner: blankToNull(values.owner),
            baseline_value: blankToNull(values.baseline_value),
            baseline_date: blankToNull(values.baseline_date),
            threshold_operator: blankToNull(values.threshold_operator),
            threshold_value: blankToNull(values.threshold_value),
            directions: Object.fromEntries(Object.entries(values.directions).filter(([, row]) => row.direction)),
        });
        if (result) return setErrors(result);
        onOpenChange(false);
    };
    const directionError = Object.keys(errors).find((key) => key.startsWith("directions"));

    return (
        <FormDialog
            title={indicator ? (indicator.review_status === "proposed" ? "Tinjau usulan indikator" : "Ubah indikator") : "Indikator peringatan dini"}
            description="Tentukan apa yang dipantau dan ke arah mana indikator ini bergerak di tiap skenario."
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan indikator"
            cancelText="Batal"
            loading={loading}
            className="sm:max-w-2xl"
        >
            <div className="flex flex-col gap-5">
                <FormField id="indicator-name" label="Nama" error={error(errors, "name")}>
                    <Input id="indicator-name" value={values.name} onChange={(event) => set("name", event.target.value)} maxLength={160} placeholder="Contoh: xEV adoption growth" />
                </FormField>
                <FormField id="indicator-description" label="Apa yang dipantau" required={false} error={error(errors, "description")}>
                    <Textarea id="indicator-description" value={values.description} onChange={(event) => set("description", event.target.value)} maxLength={2000} rows={2} />
                </FormField>
                <FormField id="indicator-directions" label="Arah yang diharapkan per kuadran" required={false} error={directionError ? { message: [].concat(errors[directionError])[0] } : undefined} group>
                    <div aria-labelledby="indicator-directions-label" className="flex flex-col gap-2">
                        {quadrants.map((quadrant) => (
                            <div key={quadrant.position} className="flex flex-wrap items-center justify-between gap-2">
                                <span className="min-w-0 text-sm text-foreground">{quadrant.title}</span>
                                <ToggleGroup type="single" variant="outline" size="sm" value={values.directions[quadrant.position]?.direction ?? ""} onValueChange={(value) => setDirection(quadrant.position, { direction: value })} aria-label={`Arah di ${quadrant.title}`}>
                                    {Object.entries(INDICATOR_DIRECTIONS).map(([value, direction]) => (
                                        <ToggleGroupItem key={value} value={value} className="px-2.5 text-xs">
                                            {direction.short} {direction.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </div>
                        ))}
                    </div>
                </FormField>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="indicator-uncertainty" label="Critical uncertainty" required={false} error={error(errors, "uncertainty")}>
                        <OptionalSelect id="indicator-uncertainty" value={values.uncertainty} onChange={(value) => set("uncertainty", value)} placeholder="Tidak dikaitkan" options={uncertainties.map((item) => ({ value: item.id, label: item.name }))} />
                    </FormField>
                    <FormField id="indicator-force" label="Driving force" required={false} error={error(errors, "force")}>
                        <OptionalSelect id="indicator-force" value={values.force} onChange={(value) => set("force", value)} placeholder="Tidak dikaitkan" options={forces.map((item) => ({ value: item.id, label: item.keyword }))} />
                    </FormField>
                    <FormField id="indicator-metric" label="Metrik" required={false} error={error(errors, "metric")}>
                        <Input id="indicator-metric" value={values.metric} onChange={(event) => set("metric", event.target.value)} maxLength={120} placeholder="Kosongkan untuk indikator kualitatif" />
                    </FormField>
                    <FormField id="indicator-unit" label="Satuan" required={false} error={error(errors, "unit")}>
                        <Input id="indicator-unit" value={values.unit} onChange={(event) => set("unit", event.target.value)} maxLength={40} />
                    </FormField>
                    <FormField id="indicator-baseline" label="Nilai awal" required={false} error={error(errors, "baseline_value")}>
                        <Input id="indicator-baseline" type="number" step="any" value={values.baseline_value} onChange={(event) => set("baseline_value", event.target.value)} />
                    </FormField>
                    <FormField id="indicator-baseline-date" label="Tanggal nilai awal" required={false} error={error(errors, "baseline_date")}>
                        <Input id="indicator-baseline-date" type="date" value={values.baseline_date} onChange={(event) => set("baseline_date", event.target.value)} />
                    </FormField>
                    <FormField id="indicator-frequency" label="Frekuensi" error={error(errors, "frequency")}>
                        <Select value={values.frequency} onValueChange={(value) => set("frequency", value)}>
                            <SelectTrigger id="indicator-frequency" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(INDICATOR_FREQUENCIES).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="indicator-source" label="Sumber pemantauan" error={error(errors, "source_type")}>
                        <Select value={values.source_type} onValueChange={(value) => set("source_type", value)}>
                            <SelectTrigger id="indicator-source" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(INDICATOR_SOURCES).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="indicator-owner" label="Penanggung jawab" required={false} error={error(errors, "owner")}>
                        <OptionalSelect id="indicator-owner" value={values.owner} onChange={(value) => set("owner", value)} placeholder="Belum ditentukan" options={owners} />
                    </FormField>
                    <FormField id="indicator-status" label="Status" error={error(errors, "status")}>
                        <Select value={values.status} onValueChange={(value) => set("status", value)}>
                            <SelectTrigger id="indicator-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="retired">Tidak dipantau lagi</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
                <fieldset className="m-0 grid gap-4 rounded-md border border-border p-3 sm:grid-cols-2">
                    <legend className="px-1 text-xs font-semibold text-muted-foreground">Ambang batas · nilai angka dihitung oleh sistem</legend>
                    <FormField id="indicator-threshold-operator" label="Operator" required={false} error={error(errors, "threshold_operator")}>
                        <OptionalSelect
                            id="indicator-threshold-operator"
                            value={values.threshold_operator}
                            onChange={(value) => set("threshold_operator", value)}
                            placeholder="Tanpa ambang angka (kualitatif)"
                            options={Object.entries(THRESHOLD_OPERATORS).map(([value, label]) => ({ value, label }))}
                        />
                    </FormField>
                    <FormField id="indicator-threshold-value" label={`Nilai ambang${values.unit ? ` (${values.unit})` : ""}`} required={Boolean(values.threshold_operator)} error={error(errors, "threshold_value")}>
                        <Input id="indicator-threshold-value" type="number" step="any" value={values.threshold_value} disabled={!values.threshold_operator} onChange={(event) => set("threshold_value", event.target.value)} />
                    </FormField>
                    <FormField id="indicator-threshold" label="Catatan ambang" required={false} error={error(errors, "threshold_rule")} className="flex flex-col gap-2 sm:col-span-2">
                        <Input id="indicator-threshold" value={values.threshold_rule} onChange={(event) => set("threshold_rule", event.target.value)} maxLength={500} placeholder="Contoh: di atas 10% sebelum 2028" />
                    </FormField>
                </fieldset>
                {values.source_type === "external" && (
                    <fieldset className="m-0 grid gap-4 rounded-md border border-border p-3 sm:grid-cols-3">
                        <legend className="px-1 text-xs font-semibold text-muted-foreground">Sumber eksternal · nilai baru masuk sebagai usulan</legend>
                        <FormField id="indicator-external-source" label="Penyedia data" error={error(errors, "external_source")}>
                            <Select value={values.external_source} onValueChange={(value) => set("external_source", value)}>
                                <SelectTrigger id="indicator-external-source" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(EXTERNAL_SOURCES).map(([value, source]) => (
                                        <SelectItem key={value} value={value}>
                                            {source.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField id="indicator-external-series" label="Kode seri" error={error(errors, "external_series")}>
                            <Input id="indicator-external-series" value={values.external_series} onChange={(event) => set("external_series", event.target.value)} maxLength={60} placeholder={EXTERNAL_SOURCES[values.external_source]?.seriesHint} />
                        </FormField>
                        <FormField id="indicator-external-area" label="Kode wilayah" error={error(errors, "external_area")}>
                            <Input id="indicator-external-area" value={values.external_area} onChange={(event) => set("external_area", event.target.value)} maxLength={3} placeholder={EXTERNAL_SOURCES[values.external_source]?.areaHint} />
                        </FormField>
                    </fieldset>
                )}
                {errors.indicators && <p role="alert" className="m-0 text-sm text-danger">{errors.indicators}</p>}
            </div>
        </FormDialog>
    );
}
