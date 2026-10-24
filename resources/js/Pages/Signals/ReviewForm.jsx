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
        title: "Accept Signal & Create Driving Force",
        submit: "Confirm Acceptance",
        intro: (
            <>
                Graduating this signal will initialize a new Driving Force in <strong>PENDING</strong> status. It will
                immediately be accessible in the existing 6-stage lifecycle for time horizon rating and approval.
            </>
        ),
        fields: ["title", "summary", "dimension_id"],
        schema: z.object({
            title: z.string().trim().min(1, "Title is required"),
            summary: z.string().trim().min(1, "Description is required"),
            dimension_id: z.string().min(1, "Select dimension"),
        }),
    },
    accept_link: {
        title: "Accept Signal & Link to Existing Driving Force",
        submit: "Confirm Acceptance",
        intro: "Attach this signal as verifiable supporting evidence to an existing active Driving Force.",
        fields: ["driving_force_id"],
        schema: z.object({
            driving_force_id: z.string().min(1, "Select target driving force"),
        }),
    },
    reject: {
        title: "Reject Candidate Signal",
        submit: "Confirm Rejection",
        intro: "Provide feedback on why this candidate signal does not meet strategic foresight criteria.",
        fields: ["rejection_reason"],
        schema: z.object({
            rejection_reason: z
                .string()
                .trim()
                .min(1, "Please provide a reason for rejection")
                .max(1000, "The reason may not be greater than 1000 characters."),
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
            title: signal?.title ? signal.title.replace(/[\.\s]+$/, "").trim() : "",
            summary: signal?.summary ? signal.summary.replace(/\.{2,}$/, "").trim() : "",
            dimension_id: toFieldValue(signal?.dimension_id),
            driving_force_id: toFieldValue(signal?.suggested_driving_force_id ?? ""),
            rejection_reason: "",
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
                        {textField("title", "Driving Force Title / Keyword (First 4 words will form keyword)", Input, {
                            autoComplete: "off",
                        })}
                        {textField("summary", "Strategic Description", Textarea, { rows: 3, className: "max-h-40 min-h-20" })}
                        <Controller
                            name="dimension_id"
                            control={control}
                            render={({ field, fieldState }) => (
                                <FormField id={ids.dimension_id} label="Taxonomy Dimension" error={fieldState.error}>
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
                                            {dimensions.map((dimension) => (
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
                            <FormField id={ids.driving_force_id} label="Target Driving Force" error={fieldState.error}>
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
                        placeholder: "e.g. Redundant with existing Driving Force #4; lacks verifiable market impact.",
                        className: "max-h-40 min-h-20",
                    })}
            </fieldset>
        </form>
    );
}
