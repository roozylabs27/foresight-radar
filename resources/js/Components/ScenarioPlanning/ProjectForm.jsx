import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";

import FormField, { fieldControlProps } from "@/Components/FormField";
import SearchableSelect from "@/Components/SearchableSelect";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { targetYearBounds } from "@/domain/scenarioPlanning";
import { toFieldValue, useDialogForm } from "@/hooks/use-dialog-form";

const FIELDS = ["name", "focal_question", "purpose", "target_year", "geography", "industry", "owner_unit", "owner", "background", "objectives"];

const { min: MIN_YEAR, max: MAX_YEAR } = targetYearBounds();

const optional = (max) => z.string().trim().max(max, `Maksimal ${max} karakter.`).optional();

const schema = z.object({
    name: z.string().trim().min(1, "Nama latihan wajib diisi.").max(150, "Maksimal 150 karakter."),
    focal_question: z.string().trim().min(10, "Pertanyaan fokus minimal 10 karakter.").max(500, "Maksimal 500 karakter."),
    purpose: z.string().trim().min(1, "Tujuan wajib diisi.").max(1000, "Maksimal 1000 karakter."),
    target_year: z
        .string()
        .trim()
        .min(1, "Tahun target wajib diisi.")
        .refine((value) => Number(value) >= MIN_YEAR && Number(value) <= MAX_YEAR, `Tahun target ${MIN_YEAR}–${MAX_YEAR}.`),
    geography: optional(150),
    industry: optional(150),
    owner_unit: optional(150),
    owner: z.string().optional(),
    background: optional(2000),
    objectives: optional(2000),
});

const TEXT_FIELDS = [
    { name: "name", label: "Nama latihan", placeholder: "Contoh: Elektrifikasi Kendaraan 2030" },
    { name: "focal_question", label: "Pertanyaan fokus (key focal issue)", placeholder: "Contoh: How Indonesia vehicle (four wheeler) electrification look like in 2030?", area: true },
    { name: "purpose", label: "Tujuan", placeholder: "Contoh: Fungsi CP menyusun usulan strategis untuk skenario elektrifikasi 2030.", area: true },
];

const OPTIONAL_FIELDS = [
    { name: "geography", label: "Cakupan geografis", placeholder: "Contoh: Indonesia" },
    { name: "industry", label: "Industri", placeholder: "Contoh: Kendaraan roda empat" },
    { name: "owner_unit", label: "Unit pemilik", placeholder: "Contoh: Corporate Planning" },
];

/**
 * Create/edit form for a scenario planning exercise. Render it inside
 * <FormDialog formId={id}>; `onSubmit(values)` may resolve to Laravel's 422 errors.
 */
export default function ProjectForm({ id, initialValues = {}, owners = [], loading, onSubmit }) {
    const idPrefix = useId();
    const ids = Object.fromEntries(FIELDS.map((name) => [name, `${idPrefix}-${name}`]));

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: Object.fromEntries(FIELDS.map((name) => [name, toFieldValue(initialValues[name])])),
        ids,
        loading,
        onSubmit,
    });

    const controlProps = (name, field, fieldState, required = true) => ({
        ...fieldControlProps(ids[name], fieldState, { required }),
        name: field.name,
        value: field.value,
        onChange: field.onChange,
        onBlur: field.onBlur,
    });

    const text = ({ name, label, placeholder, area }, required = true) => (
        <Controller
            key={name}
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <FormField id={ids[name]} label={label} required={required} error={fieldState.error}>
                    {area ? (
                        <Textarea {...controlProps(name, field, fieldState, required)} placeholder={placeholder} rows={3} className="max-h-40 min-h-20" />
                    ) : (
                        <Input {...controlProps(name, field, fieldState, required)} placeholder={placeholder} autoComplete="off" />
                    )}
                </FormField>
            )}
        />
    );

    return (
        <form id={id} noValidate onSubmit={submit}>
            <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                {TEXT_FIELDS.map((item) => text(item))}
                <Controller
                    name="target_year"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.target_year} label="Tahun target" error={fieldState.error}>
                            <Input {...controlProps("target_year", field, fieldState)} type="number" inputMode="numeric" min={MIN_YEAR} max={MAX_YEAR} placeholder={String(MIN_YEAR + 4)} className="w-40" />
                        </FormField>
                    )}
                />
                <div className="grid gap-5 sm:grid-cols-2">{OPTIONAL_FIELDS.map((item) => text(item, false))}</div>
                <Controller
                    name="owner"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.owner} label="Pemilik latihan" required={false} error={fieldState.error}>
                            <SearchableSelect id={ids.owner} field={field} fieldState={fieldState} options={owners} placeholder="Pilih anggota" searchPlaceholder="Cari anggota..." />
                        </FormField>
                    )}
                />
                {text({ name: "background", label: "Latar belakang", placeholder: "Mengapa latihan ini dilakukan sekarang?", area: true }, false)}
                {text({ name: "objectives", label: "Sasaran", placeholder: "Keputusan apa yang ingin didukung hasilnya?", area: true }, false)}
            </fieldset>
        </form>
    );
}
