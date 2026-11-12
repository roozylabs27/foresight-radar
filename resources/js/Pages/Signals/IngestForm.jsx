import { useId, useMemo } from "react";
import { Link } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { AlertCircleIcon, InfoIcon, LoaderCircleIcon, SlidersHorizontalIcon, ZapIcon } from "lucide-react";

import FormField, { fieldControlProps } from "@/Components/FormField";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { cn } from "@/lib/utils";

function LabelWithTooltip({ label, tooltip }) {
    return (
        <span className="inline-flex items-center gap-1.5 font-medium">
            <span>{label}</span>
            {tooltip && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span
                            role="button"
                            aria-label={`Informasi ${label}`}
                            className="inline-flex cursor-help text-muted-foreground/70 transition-colors hover:text-foreground"
                        >
                            <InfoIcon className="size-3.5" />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent side="top" className="max-w-xs text-xs font-normal text-left">
                        {tooltip}
                    </TooltipContent>
                </Tooltip>
            )}
        </span>
    );
}

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

const number = new Intl.NumberFormat("id-ID");

const makeSchema = (maxChars) =>
    z.object({
        title: z.string().trim().min(1, "Isi judul sumber"),
        publisher: z.string().trim(),
        url: z
            .string()
            .trim()
            .refine((value) => value === "" || isUrl(value), "Masukkan URL yang valid"),
        published_at: z.string(),
        content: z
            .string()
            .trim()
            .min(1, "Tempel isi artikel atau dokumen")
            .min(30, "Must be at least 30 characters")
            .max(
                maxChars,
                `Dokumen terlalu panjang (maks. ${number.format(maxChars)} karakter). Pecah menjadi beberapa bagian dan ingest satu per satu.`
            ),
    });

/** Who extracts the signals, so analysts know whether text leaves the server. */
function ProvenanceNote({ mode, model, label }) {
    const ai = mode !== "heuristic";

    return (
        <div className="flex gap-3 rounded-lg border border-solid border-primary/30 bg-accent p-4" role="note">
            <InfoIcon aria-hidden="true" className="mt-0.5 shrink-0 text-primary" />
            <div className="flex flex-col gap-1 w-full">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="m-0 flex flex-wrap items-center gap-2 text-sm font-semibold text-foreground">
                        Jaminan asal-usul bukti
                        <span className={cn(statusBadge(), ai ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
                            {ai ? `${label} · ${model}` : "Aturan"}
                        </span>
                    </p>
                    <Link
                        href={route("settings.llm.index")}
                        className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        <SlidersHorizontalIcon aria-hidden="true" className="size-3" />
                        <span>Ubah Mesin AI</span>
                    </Link>
                </div>
                <p className="m-0 text-[13px] text-muted-foreground">
                    {ai
                        ? `Teks dokumen dikirim ke ${label} (${model}) untuk diusulkan sebagai sinyal. Setiap kutipan bukti dicocokkan verbatim ke teks yang Anda tempel; kutipan yang tidak ditemukan dibuang. Semua sinyal masuk sebagai PENDING dan tetap Anda putuskan.`
                        : "Sinyal diusulkan dengan aturan kata kunci, tanpa AI, dan teks tidak keluar dari server."}{" "}
                    Setiap sinyal menyimpan kutipan verbatim dan sidik jari SHA-256 dari teks sumber untuk audit. URL hanya
                    disimpan sebagai metadata; isinya tidak diambil.
                </p>
            </div>
        </div>
    );
}

/** External sources are extracted by AI only; without a provider the form is closed and says where to set one. */
function AiMissingNote() {
    return (
        <div className="flex gap-3 rounded-lg border border-solid border-warning/40 bg-warning-soft p-4" role="alert">
            <AlertCircleIcon aria-hidden="true" className="mt-0.5 shrink-0 text-warning" />
            <div className="flex flex-col gap-1">
                <p className="m-0 text-sm font-semibold text-foreground">Ingest memerlukan AI</p>
                <p className="m-0 text-[13px] text-muted-foreground">
                    Sinyal dari sumber eksternal hanya diekstrak oleh AI. Layanan AI belum diatur untuk akun atau organisasi Anda.
                </p>
                <Link href={route("settings.llm.index")} className="inline-flex items-center gap-1 self-start text-xs font-medium text-primary hover:underline">
                    <SlidersHorizontalIcon aria-hidden="true" className="size-3" />
                    Atur provider AI di Pengaturan
                </Link>
            </div>
        </div>
    );
}

/**
 * Source document ingestion. `onSubmit(payload)` sends it and may resolve to
 * the 422 `errors` bag; resolve `true` on success to clear the form.
 * `mode` is the LLM provider id or "heuristic", with `label` and `model` naming
 * it; content longer than `maxChars` is refused. `aiMissing` closes the form.
 */
export default function IngestForm({ loading, onSubmit, mode = "heuristic", model = null, label = "Aturan", maxChars = 60000, aiMissing = false }) {
    const idPrefix = useId();
    const ids = Object.fromEntries(FIELDS.map((name) => [name, `${idPrefix}-${name}`]));
    const schema = useMemo(() => makeSchema(maxChars), [maxChars]);

    const { control, submit, reset, watch } = useDialogForm({
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

    const length = (watch("content") ?? "").trim().length;
    const counterId = `${idPrefix}-counter`;

    return (
        <div className="mx-auto flex w-full max-w-[840px] flex-col gap-6 py-2">
            {aiMissing ? <AiMissingNote /> : <ProvenanceNote mode={mode} model={model} label={label} />}

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading || aiMissing} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <div className="grid gap-5 sm:grid-cols-[2fr_1fr]">
                        {field(
                            "title",
                            <LabelWithTooltip
                                label="Judul dokumen / artikel"
                                tooltip="Nama dokumen, judul artikel, atau regulasi yang dianalisis sebagai rujukan utama."
                            />,
                            Input,
                            {
                                required: true,
                                placeholder: "Contoh: EU Cleantech Regulatory Directive 2026/42",
                            }
                        )}
                        {field(
                            "publisher",
                            <LabelWithTooltip
                                label="Penerbit / organisasi sumber"
                                tooltip="Nama lembaga atau organisasi penerbit (misal: Bank Indonesia, Reuters, McKinsey) untuk mengukur otoritas sumber."
                            />,
                            Input,
                            {
                                placeholder: "e.g. European Commission, Reuters",
                            }
                        )}
                    </div>
                    <div className="grid gap-5 sm:grid-cols-[2fr_1fr]">
                        {field(
                            "url",
                            <LabelWithTooltip
                                label="URL sumber (opsional)"
                                tooltip="Tautan artikel asli untuk rujukan cepat analis (hanya disimpan sebagai metadata rujukan; isi teks diambil dari kolom Isi dokumen di bawah)."
                            />,
                            Input,
                            {
                                type: "url",
                                inputMode: "url",
                                placeholder: "https://ec.europa.eu/commission/presscorner/detail/en/ip_26_...",
                            }
                        )}
                        {field(
                            "published_at",
                            <LabelWithTooltip
                                label="Tanggal terbit"
                                tooltip="Tanggal dokumen diterbitkan. Digunakan AI untuk mengukur cakrawala waktu (Time Horizon)."
                            />,
                            Input,
                            { type: "date" }
                        )}
                    </div>
                    {field(
                        "content",
                        <LabelWithTooltip
                            label="Isi dokumen"
                            tooltip="Teks atau paragraf isi dokumen yang ditempel analis. AI akan memindai teks ini, mengekstrak sinyal, mencocokkan kutipan verbatim, dan menghitung sidik jari SHA-256 untuk integritas audit."
                        />,
                        Textarea,
                        {
                            required: true,
                            rows: 8,
                            placeholder:
                                "Paste the excerpt, report paragraphs, or article transcript here. The extraction pipeline will parse verifiable evidence sentences, suggest taxonomies, and generate candidate signals.",
                            className: "min-h-40",
                        }
                    )}
                    <p
                        id={counterId}
                        className={cn("m-0 -mt-3 text-xs tabular-nums", length > maxChars ? "text-destructive" : "text-muted-foreground")}
                    >
                        {number.format(length)} / {number.format(maxChars)} karakter
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" aria-busy={loading || undefined}>
                            {loading ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <ZapIcon aria-hidden="true" />}
                            {loading ? "Menyimpan sumber…" : "Simpan & ekstrak sinyal"}
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
