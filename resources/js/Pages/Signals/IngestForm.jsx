import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { InfoIcon, LoaderCircleIcon, ZapIcon } from "lucide-react";

import FormField, { fieldControlProps } from "@/Components/FormField";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { useDialogForm } from "@/hooks/use-dialog-form";

const FIELDS = ["title", "publisher", "url", "published_at", "content"];
const EMPTY = Object.fromEntries(FIELDS.map((name) => [name, ""]));

function isUrl(value) {
    try {
        const url = new URL(value);
        return url.protocol === "http:" || url.protocol === "https:";
    } catch {
        return false;
    }
}

const schema = z.object({
    title: z.string().trim().min(1, "Please provide a source title"),
    publisher: z.string().trim(),
    url: z
        .string()
        .trim()
        .refine((value) => value === "" || isUrl(value), "Enter a valid URL"),
    published_at: z.string(),
    content: z
        .string()
        .trim()
        .min(1, "Please paste the article or document body")
        .min(30, "Must be at least 30 characters"),
});

/**
 * Source document / URL ingestion. `onSubmit(payload)` sends it and may
 * resolve to the 422 `errors` bag; resolve `true` on success to clear the form.
 */
export default function IngestForm({ loading, onSubmit }) {
    const idPrefix = useId();
    const ids = Object.fromEntries(FIELDS.map((name) => [name, `${idPrefix}-${name}`]));

    const { control, submit, reset } = useDialogForm({
        schema,
        defaultValues: EMPTY,
        ids,
        loading,
        onSubmit: async (values) => {
            const result = await onSubmit({
                title: values.title,
                content: values.content,
                url: values.url || null,
                publisher: values.publisher || null,
                published_at: values.published_at || null,
            });
            if (result === true) {
                reset(EMPTY);
                return undefined;
            }
            return result;
        },
    });

    const field = (name, label, Control, { required = false, ...extra } = {}) => (
        <Controller
            name={name}
            control={control}
            render={({ field: rhf, fieldState }) => (
                <FormField id={ids[name]} label={label} error={fieldState.error} required={required}>
                    <Control
                        {...fieldControlProps(ids[name], fieldState, { required })}
                        name={rhf.name}
                        value={rhf.value}
                        onChange={rhf.onChange}
                        onBlur={rhf.onBlur}
                        {...extra}
                    />
                </FormField>
            )}
        />
    );

    return (
        <div className="mx-auto flex w-full max-w-[840px] flex-col gap-6 py-2">
            <div className="flex gap-3 rounded-lg border border-solid border-primary/30 bg-accent p-4" role="note">
                <InfoIcon aria-hidden="true" className="mt-0.5 text-primary" />
                <div className="flex flex-col gap-1">
                    <p className="m-0 text-sm font-semibold text-foreground">Evidence Provenance Guarantee</p>
                    <p className="m-0 text-[13px] text-muted-foreground">
                        Every candidate signal extracted from this document will permanently retain an exact verbatim excerpt
                        and a SHA-256 fingerprint of the source text for audit verification.
                    </p>
                </div>
            </div>

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <div className="grid gap-5 sm:grid-cols-[2fr_1fr]">
                        {field("title", "Document / Article Title", Input, {
                            required: true,
                            placeholder: "e.g. EU Cleantech Regulatory Directive 2026/42",
                        })}
                        {field("publisher", "Publisher / Source Organization", Input, {
                            placeholder: "e.g. European Commission, Reuters",
                        })}
                    </div>
                    <div className="grid gap-5 sm:grid-cols-[2fr_1fr]">
                        {field("url", "Source URL (Optional)", Input, {
                            type: "url",
                            inputMode: "url",
                            placeholder: "https://ec.europa.eu/commission/presscorner/detail/en/ip_26_...",
                        })}
                        {field("published_at", "Publication Date", Input, { type: "date" })}
                    </div>
                    {field("content", "Raw Content / Document Text", Textarea, {
                        required: true,
                        rows: 8,
                        placeholder:
                            "Paste the excerpt, report paragraphs, or article transcript here. The extraction pipeline will parse verifiable evidence sentences, suggest taxonomies, and generate candidate signals.",
                        className: "min-h-40",
                    })}
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" aria-busy={loading || undefined}>
                            {loading ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <ZapIcon aria-hidden="true" />}
                            Ingest &amp; Extract Signals
                        </Button>
                        <Button type="button" variant="outline" onClick={() => reset(EMPTY)}>
                            Clear
                        </Button>
                    </div>
                </fieldset>
            </form>
        </div>
    );
}
