import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";

import FormField, { fieldControlProps } from "@/Components/FormField";
import SearchableSelect from "@/Components/SearchableSelect";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { toFieldValue, useDialogForm } from "@/hooks/use-dialog-form";
import { cn } from "@/lib/utils";

export const REVIEW_ACTIONS = {
    accept_create: {
        title: "Terima sinyal & buat driving force",
        submit: "Terima",
        intro: (
            <>
                Sinyal adalah bukti <em>untuk</em> sebuah driving force, bukan force itu sendiri. Beri nama kekuatan perubahan yang
                lebih luas yang ditunjuk sinyal ini; statusnya mulai <strong>PENDING</strong>. Pilih “Tautkan ke DF” bila force yang cocok sudah ada.
            </>
        ),
        fields: ["force_keyword", "force_description", "dimension_id", "confirm_signal_title"],
        schema: z.object({
            force_keyword: z
                .string()
                .trim()
                .min(1, "Beri nama driving force")
                .refine((value) => value.split(/\s+/).filter(Boolean).length <= 4, "Paling banyak 4 kata"),
            force_description: z.string().trim().min(1, "Jelaskan driving force"),
            dimension_id: z.string().min(1, "Pilih dimensi"),
            confirm_signal_title: z.boolean(),
        }),
    },
    accept_link: {
        title: "Terima sinyal & tautkan ke driving force yang ada",
        submit: "Terima",
        intro: "Lampirkan sinyal ini sebagai bukti pendukung yang dapat diverifikasi untuk driving force aktif yang sudah ada.",
        fields: ["driving_force_id"],
        schema: z.object({
            driving_force_id: z.string().min(1, "Pilih driving force tujuan"),
        }),
    },
    reject: {
        title: "Tolak sinyal kandidat",
        submit: "Tolak",
        intro: "Tulis alasan sinyal kandidat ini tidak memenuhi kriteria strategic foresight.",
        fields: ["rejection_reason"],
        schema: z.object({
            rejection_reason: z
                .string()
                .trim()
                .min(1, "Tulis alasan penolakan")
                .max(1000, "Alasan paling banyak 1000 karakter."),
        }),
    },
};

/** The evidence being accepted, read only: it cannot be changed while accepting. */
function EvidenceBlock({ signal }) {
    if (!signal?.evidence_quote) return null;
    const unverified = signal.quote_verified === false;

    return (
        <figure className="m-0 flex flex-col gap-1.5">
            <figcaption className="text-xs font-medium text-muted-foreground">Bukti dari sumber</figcaption>
            <blockquote
                className={cn(
                    "m-0 border-0 border-l-[3px] border-solid bg-muted/60 px-3 py-2 text-xs text-foreground italic",
                    unverified ? "border-destructive" : "border-primary"
                )}
            >
                “{signal.evidence_quote}”
            </blockquote>
            <span className={cn("text-[11px]", unverified ? "text-destructive" : "text-muted-foreground")}>
                {signal.quote_verified === true && "Kutipan ditemukan verbatim di dokumen sumber."}
                {unverified && "Kutipan tidak ditemukan di dokumen sumber, jadi sinyal ini tidak bisa diterima."}
                {signal.quote_verified == null && "Sinyal lama: kutipan belum pernah diverifikasi."}
                {signal.source?.title && ` Sumber: ${signal.source.title}.`}
            </span>
        </figure>
    );
}

/**
 * Review step for one candidate signal: accept as a new driving force, link it
 * to an existing one, or reject it. Render inside <FormDialog formId={id}>.
 * `onSubmit(values)` may resolve to the 422 `errors` bag.
 */
export default function ReviewForm({ id, action, signal, dimensions = [], drivingForces = [], loading, onSubmit }) {
    const idPrefix = useId();
    const config = REVIEW_ACTIONS[action];
    const ids = Object.fromEntries(config.fields.map((name) => [name, `${idPrefix}-${name}`]));

    const { control, submit } = useDialogForm({
        schema: config.schema,
        defaultValues: {
            title: signal?.title ? signal.title.replace(/[.\s]+$/, "").trim() : "",
            summary: signal?.summary ? signal.summary.replace(/\.{2,}$/, "").trim() : "",
            dimension_id: toFieldValue(signal?.dimension_id),
            driving_force_id: toFieldValue(signal?.suggested_driving_force_id ?? ""),
            rejection_reason: "",
            force_keyword: "",
            force_description: "",
            confirm_signal_title: false,
        },
        ids,
        loading,
        onSubmit: (values) => onSubmit(Object.fromEntries(config.fields.map((name) => [name, values[name]]))),
    });

    const textField = (name, label, Control, extra = {}) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <FormField id={ids[name]} label={label} error={fieldState.error}>
                    <Control
                        {...fieldControlProps(ids[name], fieldState)}
                        name={field.name}
                        value={field.value}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        {...extra}
                    />
                </FormField>
            )}
        />
    );

    return (
        <form id={id} noValidate onSubmit={submit}>
            <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                <p className="m-0 text-sm text-muted-foreground">{config.intro}</p>

                {action !== "reject" && <EvidenceBlock signal={signal} />}

                {action === "accept_create" && (
                    <>
                        {textField("force_keyword", "Driving force name (max. 4 words)", Input, {
                            autoComplete: "off",
                            placeholder: "Contoh: Battery Technology Shift",
                        })}
                        {textField("force_description", "Driving force description", Textarea, { rows: 3, className: "max-h-40 min-h-20" })}
                        <Controller
                            name="confirm_signal_title"
                            control={control}
                            render={({ field }) => (
                                <label className="flex items-start gap-2 text-xs text-muted-foreground">
                                    <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={field.value} onChange={(event) => field.onChange(event.target.checked)} />
                                    The name may repeat the signal title (only when the signal itself names the force).
                                </label>
                            )}
                        />
                        <Controller
                            name="dimension_id"
                            control={control}
                            render={({ field, fieldState }) => (
                                <FormField id={ids.dimension_id} label="Dimensi taksonomi" error={fieldState.error}>
                                    <Select
                                        name={field.name}
                                        value={field.value}
                                        onValueChange={field.onChange}
                                        onOpenChange={(open) => !open && field.onBlur()}
                                    >
                                        <SelectTrigger {...fieldControlProps(ids.dimension_id, fieldState)} className="w-full">
                                            <SelectValue placeholder="Select a dimension" />
                                        </SelectTrigger>
                                        <SelectContent position="popper">
                                            {/* Archived dimensions take no new signals; the signal may keep its own. */}
                                            {dimensions.filter((dimension) => !dimension.archived || dimension.value === signal?.dimension_id).map((dimension) => (
                                                <SelectItem key={dimension.value} value={toFieldValue(dimension.value)}>
                                                    {dimension.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            )}
                        />
                    </>
                )}

                {action === "accept_link" && (
                    <Controller
                        name="driving_force_id"
                        control={control}
                        render={({ field, fieldState }) => (
                            <FormField id={ids.driving_force_id} label="Driving force tujuan" error={fieldState.error}>
                                <SearchableSelect
                                    id={ids.driving_force_id}
                                    field={field}
                                    fieldState={fieldState}
                                    options={drivingForces}
                                    placeholder="Select driving force to attach evidence"
                                    searchPlaceholder="Cari driving force..."
                                />
                            </FormField>
                        )}
                    />
                )}

                {action === "reject" &&
                    textField("rejection_reason", "Rejection Reason", Textarea, {
                        rows: 3,
                        maxLength: 1000,
                        placeholder: "Contoh: sama dengan Driving Force #4; dampak pasar tidak dapat diverifikasi.",
                        className: "max-h-40 min-h-20",
                    })}
            </fieldset>
        </form>
    );
}
