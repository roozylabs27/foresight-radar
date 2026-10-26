import { useEffect, useId, useMemo, useRef, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
    CirclePlusIcon,
    CircleXIcon,
    ClipboardCheckIcon,
    CloudUploadIcon,
    CpuIcon,
    EyeIcon,
    InfoIcon,
    LinkIcon,
    LoaderCircleIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    SlidersHorizontalIcon,
    ZapIcon,
} from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { FilterSelect, filterParam } from "@/Components/AnalysisFilters";
import DataTable, { useServerTable } from "@/Components/DataTable";
import DetailList, { DetailItem } from "@/Components/DetailList";
import DimensionChip from "@/Components/DimensionChip";
import FilterBar, { FilterField } from "@/Components/FilterBar";
import FormDialog from "@/Components/FormDialog";
import PageHeader from "@/Components/PageHeader";
import SearchInput from "@/Components/SearchInput";
import StatusBadge, { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { fromPaginator } from "@/hooks/use-server-table";
import { useReturnFocus } from "@/hooks/use-return-focus";
import { isPending, pollUntil, providerLabel } from "@/lib/llm";
import { useIngestion } from "@/context/IngestionContext";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import { cn } from "@/lib/utils";
import IngestForm from "./IngestForm";
import ReviewForm, { REVIEW_ACTIONS } from "./ReviewForm";

const REVIEW_FORM_ID = "signal-review-form";

const STATUS_OPTIONS = [
    { value: "PENDING", label: "Review Status: Pending" },
    { value: "ACCEPTED", label: "Review Status: Accepted" },
    { value: "REJECTED", label: "Review Status: Rejected" },
    { value: "ALL", label: "All Statuses" },
];

// review_status values; ACCEPTED uses the approved tone.
const STATUS_BADGES = {
    PENDING: { status: "pending", label: "Pending" },
    ACCEPTED: { status: "approved", label: "Accepted" },
    REJECTED: { status: "rejected", label: "Rejected" },
};

const confidence = (signal) => Math.round((signal.confidence_score || 0.8) * 100);

function HeaderWithTooltip({ title, tooltip }) {
    return (
        <span className="inline-flex items-center gap-1.5 font-semibold">
            <span>{title}</span>
            {tooltip && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span
                            role="button"
                            aria-label={`Informasi tentang ${title}`}
                            className="inline-flex cursor-help text-muted-foreground/70 transition-colors hover:text-foreground"
                            onClick={(e) => e.stopPropagation()}
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

/** Who proposed the signal: an LLM provider, keyword rules, or unknown (created before provenance was recorded). */
function ProviderBadge({ signal }) {
    if (signal.extraction_provider === "heuristic") {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className={cn(statusBadge(), "cursor-help bg-muted text-muted-foreground")}>
                        Aturan
                    </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs text-xs text-left">
                    <p className="mb-0.5 font-semibold text-foreground">Metode Aturan (Heuristik Offline)</p>
                    <p className="text-muted-foreground">
                        Skor dan sinyal diekstrak menggunakan aturan kata kunci lokal tanpa AI (terjadi saat server AI sibuk atau model nonaktif).
                    </p>
                </TooltipContent>
            </Tooltip>
        );
    }
    if (signal.extraction_provider) {
        const providerName = providerLabel(signal.extraction_provider);
        const modelName = signal.extraction_model ?? "AI Model";
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className={cn(statusBadge(), "cursor-help bg-primary text-primary-foreground")}>
                        {providerName}
                    </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs text-xs text-left">
                    <p className="mb-0.5 font-semibold text-foreground">Dianalisis oleh AI ({providerName})</p>
                    <p className="text-muted-foreground">
                        Sinyal, estimasi dampak, dan confidence dinilai langsung oleh model kecerdasan buatan <b className="text-foreground">{modelName}</b> berdasarkan makna semantik dokumen.
                    </p>
                </TooltipContent>
            </Tooltip>
        );
    }
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className={cn(statusBadge(), "cursor-help")}>Asal tidak tercatat</span>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs text-xs text-left">
                Data lama sebelum pencatatan mesin provenance disimpan.
            </TooltipContent>
        </Tooltip>
    );
}

/** Whether the evidence quote was found verbatim in the source text. */
function QuoteStatus({ verified }) {
    if (verified === true) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex cursor-help items-center gap-1 text-[11px] font-medium text-success">
                        <ShieldCheckIcon aria-hidden="true" className="size-3.5" />
                        Kutipan terverifikasi
                    </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs text-xs text-left">
                    Kutipan bukti ini telah diverifikasi verbatim (100% cocok huruf-demi-huruf) dengan teks sumber asli untuk menjamin anti-halusinasi.
                </TooltipContent>
            </Tooltip>
        );
    }
    if (verified === false) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex cursor-help items-center gap-1 text-[11px] font-medium text-destructive">
                        <ShieldAlertIcon aria-hidden="true" className="size-3.5" />
                        Kutipan tidak ditemukan di sumber
                    </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs text-xs text-left">
                    Kutipan tidak ditemukan persis di dokumen sumber. Sinyal ini ditahan dan tidak bisa di-approve demi integritas data strategis.
                </TooltipContent>
            </Tooltip>
        );
    }
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="cursor-help text-[11px] text-muted-foreground">Kutipan belum diverifikasi (data lama)</span>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs text-xs text-left">
                Sinyal historis yang dibuat sebelum sistem verifikasi kutipan otomatis diaktifkan.
            </TooltipContent>
        </Tooltip>
    );
}

function extractedBy(signal) {
    if (signal.extraction_provider === "heuristic") return "Aturan kata kunci (tanpa AI)";
    if (signal.extraction_provider) {
        return `${providerLabel(signal.extraction_provider)} (${signal.extraction_model ?? "model tidak tercatat"}), prompt ${signal.prompt_version ?? "-"}`;
    }
    return "Tidak tercatat (dibuat sebelum provenance disimpan)";
}

function errorMessage(error, fallback) {
    const errors = error.response?.data?.errors;
    return typeof errors === "string" ? errors : (error.response?.data?.message ?? fallback);
}

function SignalCell({ signal }) {
    return (
        <div className="flex min-w-[260px] flex-col gap-1.5">
            <span className="text-sm font-semibold text-foreground">{signal.title}</span>
            {signal.summary && <p className="m-0 line-clamp-2 text-xs text-muted-foreground">{signal.summary}</p>}
            {signal.evidence_quote && (
                <blockquote className="m-0 border-0 border-l-[3px] border-solid border-primary bg-muted/60 px-2.5 py-1.5 text-xs text-foreground italic">
                    “{signal.evidence_quote}”
                </blockquote>
            )}
            <QuoteStatus verified={signal.quote_verified} />
            {signal.suggested_driving_force && (
                <span className="text-[11px] text-muted-foreground">
                    Usulan force: <b className="text-foreground">{signal.suggested_driving_force.keyword}</b>
                </span>
            )}
        </div>
    );
}

function ActionButtons({ signal, onInspect, onAction }) {
    return (
        <div className="flex min-w-[150px] flex-col gap-1.5">
            <Button size="sm" variant="outline" className="justify-start border-input" onClick={() => onInspect(signal)}>
                <EyeIcon aria-hidden="true" />
                Inspect
            </Button>
            {signal.review_status === "PENDING" && (
                <>
                    <Button size="sm" className="justify-start" onClick={() => onAction("accept_create", signal)}>
                        <CirclePlusIcon aria-hidden="true" />
                        Accept as New DF
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        className="justify-start border-input"
                        onClick={() => onAction("accept_link", signal)}
                    >
                        <LinkIcon aria-hidden="true" />
                        Link to DF
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        className="justify-start border-destructive/50 text-destructive hover:bg-danger-soft hover:text-destructive"
                        onClick={() => onAction("reject", signal)}
                    >
                        <CircleXIcon aria-hidden="true" />
                        Reject
                    </Button>
                </>
            )}
        </div>
    );
}

function InspectDialog({ signal, open, onOpenChange }) {
    const returnFocus = useReturnFocus(open);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                onCloseAutoFocus={returnFocus}
                className="max-h-[calc(100svh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto] sm:max-w-[720px]"
            >
                <DialogHeader>
                    <DialogTitle>Signal Inspection &amp; Provenance</DialogTitle>
                    <DialogDescription className="sr-only">Detail, bukti, dan asal sinyal kandidat.</DialogDescription>
                </DialogHeader>
                {signal && (
                    <div className="-mx-6 overflow-y-auto px-6">
                        <DetailList className="sm:grid-cols-2">
                            <DetailItem label="Signal Title" className="sm:col-span-2">
                                <span className="font-semibold">{signal.title}</span>
                            </DetailItem>
                            <DetailItem label="Summary" className="sm:col-span-2">
                                {signal.summary}
                            </DetailItem>
                            <DetailItem label="Significance" className="sm:col-span-2">
                                {signal.significance || "N/A"}
                            </DetailItem>
                            <DetailItem label="Verbatim Evidence Quote" className="sm:col-span-2">
                                <code className="block rounded-md bg-muted px-3 py-2 font-mono text-xs whitespace-pre-wrap">
                                    “{signal.evidence_quote}”
                                </code>
                                <QuoteStatus verified={signal.quote_verified} />
                            </DetailItem>
                            <DetailItem label="Diekstrak oleh" className="sm:col-span-2">
                                {extractedBy(signal)}
                            </DetailItem>
                            {signal.suggested_driving_force && (
                                <DetailItem label="Usulan Driving Force" className="sm:col-span-2">
                                    {signal.suggested_driving_force.keyword}
                                </DetailItem>
                            )}
                            <DetailItem label="Dimension">
                                <DimensionChip name={signal.dimension?.name || "General"} />
                            </DetailItem>
                            <DetailItem label="Estimated Horizon">
                                {signal.suggested_time_horizon?.name || "Unassigned"}
                            </DetailItem>
                            <DetailItem label="Scoring Estimates">
                                Impact: {signal.preliminary_impact ?? "N/A"} | Uncertainty:{" "}
                                {signal.preliminary_uncertainty ?? "N/A"}
                            </DetailItem>
                            <DetailItem label="Confidence">
                                {confidence(signal)}% ({signal.confidence_rationale || "Algorithmic heuristic"})
                            </DetailItem>
                            <DetailItem label="Source Title" className="sm:col-span-2">
                                {signal.source?.title || "N/A"}
                            </DetailItem>
                            {signal.source?.url && (
                                <DetailItem label="Source URL" className="sm:col-span-2">
                                    <a
                                        href={signal.source.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="break-all text-primary underline-offset-4 hover:underline"
                                    >
                                        {signal.source.url}
                                    </a>
                                </DetailItem>
                            )}
                            <DetailItem
                                label={
                                    <span className="inline-flex items-center gap-1">
                                        <span>Content SHA-256 Hash</span>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span role="button" aria-label="Info SHA-256" className="cursor-help text-muted-foreground/70 hover:text-foreground">
                                                    <InfoIcon className="size-3" />
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" className="max-w-xs text-xs font-normal text-left">
                                                Sidik jari kriptografi 256-bit unik dari dokumen sumber mentah untuk audit forensik dan jaminan data tidak dimanipulasi (anti-tampering).
                                            </TooltipContent>
                                        </Tooltip>
                                    </span>
                                }
                                className="sm:col-span-2"
                            >
                                <code className="font-mono text-[11px] break-all">{signal.source?.content_hash || "N/A"}</code>
                            </DetailItem>
                            {signal.rejection_reason && (
                                <DetailItem label="Rejection Reason" className="sm:col-span-2">
                                    <span className="text-destructive">{signal.rejection_reason}</span>
                                </DetailItem>
                            )}
                        </DetailList>
                    </div>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const RESULT_TOAST_MS = 12000;

/**
 * One source whose signals are being extracted by a queued job. Polls its
 * status and calls `onDone(progress)` once, or `onDone(null)` when it gives up.
 */
function ExtractionProgress({ source, label, onDone }) {
    const [status, setStatus] = useState(source.status);
    const done = useRef(onDone);
    done.current = onDone;

    useEffect(() => {
        const controller = new AbortController();
        pollUntil(
            () =>
                axios.get(route("signals.extraction", source.id), { signal: controller.signal }).then((response) => {
                    setStatus(response.data.status);
                    return response.data;
                }),
            (data) => !isPending(data.status),
            { signal: controller.signal }
        )
            .then((data) => !controller.signal.aborted && done.current(data))
            .catch((error) => {
                if (!controller.signal.aborted) {
                    toast.error("Status ekstraksi tidak dapat dimuat.", { description: errorMessage(error, error.message) });
                    done.current(null);
                }
            });
        return () => controller.abort();
    }, [source.id]);

    return (
        <li className="flex items-start gap-2 text-[13px] text-muted-foreground">
            <LoaderCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 animate-spin text-primary" />
            <span>
                <b className="font-semibold text-foreground">{source.title}</b>:{" "}
                {status === "processing" ? `${label} sedang membaca dokumen` : "menunggu giliran di antrean"}; biasanya
                10–150 detik. Halaman ini boleh ditinggalkan, hasilnya tetap tersimpan.
            </span>
        </li>
    );
}

export default function SignalIndex({
    auth,
    title,
    dimensions = [],
    existingDrivingForces = [],
    extractionMode = "heuristic",
    extractionModel = null,
    extractionLabel = "Aturan",
    activeLlm = null,
    maxContentChars = 60000,
    pendingExtractions = [],
}) {
    const { startTracking } = useIngestion();
    const statusFieldId = useId();
    const [tab, setTab] = useState("review");

    // Review queue
    const [status, setStatus] = useState("PENDING");
    const [dimension, setDimension] = useState("all");
    const [search, setSearch] = useState("");

    const table = useServerTable({
        url: route("signals.fetch"),
        params: { status, dimension: filterParam(dimension), search: search || undefined },
        select: fromPaginator,
        pageSizeParam: "pageSize",
    });

    // Inspect
    const [inspected, setInspected] = useState(null);
    const [inspectOpen, setInspectOpen] = useState(false);
    const openInspect = (signal) => {
        setInspected(signal);
        setInspectOpen(true);
    };

    // Review action
    const [review, setReview] = useState(null);
    const [reviewOpen, setReviewOpen] = useState(false);
    const [reviewing, setReviewing] = useState(false);

    const openReview = (action, signal) => {
        setReview({ action, signal });
        setReviewOpen(true);
    };

    const handleReview = async (values) => {
        setReviewing(true);
        try {
            const response = await axios.post(`/signals/${review.signal.uuid}/review`, { action: review.action, ...values });
            toast.success(response.data.message || "Review action processed.");
            setReviewOpen(false);
            table.reload();
        } catch (error) {
            const errors = error.response?.data?.errors;
            if (error.response?.status === 422 && errors && typeof errors === "object") {
                toast.error("Validation failed", {
                    description: errors.evidence_quote?.[0] ?? "Periksa kembali isian yang ditandai.",
                });
                return errors;
            }
            toast.error("Action failed to execute.", { description: errorMessage(error, error.message) });
        } finally {
            setReviewing(false);
        }
    };

    // Ingest: the request saves the source; signals are extracted by a queued job.
    const [ingesting, setIngesting] = useState(false);
    const [extractions, setExtractions] = useState(pendingExtractions);

    useEffect(() => {
        if (Array.isArray(pendingExtractions) && pendingExtractions.length > 0) {
            pendingExtractions.forEach((source) => {
                startTracking(source);
            });
        }
    }, [pendingExtractions, startTracking]);

    const showReview = () => {
        setTab("review");
        setStatus("PENDING");
    };

    // The result arrives while the analyst may be looking elsewhere, so it stays up longer than a default toast.
    const finishExtraction = (source) => (progress) => {
        setExtractions((current) => current.filter((item) => item.id !== source.id));
        const duration = RESULT_TOAST_MS;
        if (!progress) {
            toast.info(`Ekstraksi “${source.title}” masih berjalan.`, { description: "Muat ulang halaman ini nanti untuk melihat hasilnya.", duration });
            return;
        }
        if (progress.status === "failed") {
            toast.error(`Ekstraksi “${source.title}” gagal.`, { description: progress.error, duration });
            return;
        }
        const action = { label: "Lihat sinyal", onClick: showReview };
        if (progress.extraction?.fallback) {
            toast.warning(progress.message, { description: progress.extraction.notice, action, duration });
        } else {
            toast.success(progress.message, { action, duration });
        }
        table.reload();
    };

    const handleIngest = async (payload) => {
        setIngesting(true);
        try {
            const response = await axios.post("/signals/ingest", payload);
            const source = response.data.source;
            if (isPending(source.status)) {
                toast.info(response.data.message);
                setExtractions((current) => [source, ...current]);
                startTracking(source);
            } else {
                // Without a queue worker (QUEUE_CONNECTION=sync) the job has already run.
                finishExtraction(source)(source);
            }
            return true;
        } catch (error) {
            const errors = error.response?.data?.errors;
            if (error.response?.status === 422 && errors && typeof errors === "object") {
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return errors;
            }
            toast.error("Ingestion failed.", { description: errorMessage(error, error.message) });
            return undefined;
        } finally {
            setIngesting(false);
        }
    };

    const columns = useMemo(
        () => [
            {
                accessorKey: "title",
                enableSorting: true,
                header: () => (
                    <HeaderWithTooltip
                        title="Signal Title & Evidence"
                        tooltip="Judul indikasi perubahan masa depan dan kutipan bukti verbatim dari teks sumber yang mendasarinya."
                    />
                ),
                cell: ({ row }) => <SignalCell signal={row.original} />,
            },
            {
                id: "taxonomy",
                enableSorting: true,
                header: () => (
                    <HeaderWithTooltip
                        title="Taxonomy & Origin"
                        tooltip="Klasifikasi dimensi makro (STEEP-V), estimasi cakrawala waktu (Time Horizon), dan identitas penerbit dokumen sumber."
                    />
                ),
                cell: ({ row }) => {
                    const signal = row.original;
                    const horizonName = signal.suggested_time_horizon?.name;
                    const horizonTooltip =
                        horizonName === "Short Term"
                            ? "Cakrawala Waktu Pendek: dampak diperkirakan terjadi dalam kurun < 1 tahun."
                            : horizonName === "Mid Term"
                            ? "Cakrawala Waktu Menengah: dampak diperkirakan terjadi dalam kurun 1–3 tahun."
                            : horizonName === "Long Term"
                            ? "Cakrawala Waktu Panjang: dampak transformasional diperkirakan terjadi > 3 tahun."
                            : "Estimasi cakrawala waktu implementasi sinyal.";

                    return (
                        <div className="flex min-w-[150px] flex-col items-start gap-1.5">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <div className="cursor-help">
                                        <DimensionChip name={signal.dimension?.name || "Unassigned"} />
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent side="top" className="max-w-xs text-xs">
                                    Dimensi Taksonomi Analisis Lingkungan Makro (STEEP-V)
                                </TooltipContent>
                            </Tooltip>

                            {signal.suggested_time_horizon && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className={cn(statusBadge(), "cursor-help bg-accent text-primary")}>
                                            {signal.suggested_time_horizon.name}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="max-w-xs text-xs">
                                        {horizonTooltip}
                                    </TooltipContent>
                                </Tooltip>
                            )}
                            <span className="text-[11px] text-muted-foreground">
                                Source: {signal.source?.publisher || signal.source?.title || "Manual entry"}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: "assessment",
                enableSorting: true,
                header: () => (
                    <HeaderWithTooltip
                        title="Assessment & Confidence"
                        tooltip="Metrik evaluasi sinyal: Confidence (keyakinan bukti), Est. Impact (skala pengaruh 1–10), Uncertainty (tingkat ketidakpastian 1–10), dan Mesin pengekstrak."
                    />
                ),
                cell: ({ row }) => {
                    const signal = row.original;
                    const percent = confidence(signal);
                    return (
                        <div className="flex min-w-[170px] flex-col items-start gap-1.5 text-xs">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <span className="flex cursor-help items-center gap-1.5 font-medium text-foreground">
                                        <span
                                            aria-hidden="true"
                                            className={cn("size-2 rounded-full", percent >= 80 ? "bg-success" : "bg-warning")}
                                        />
                                        {percent}% confidence
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent side="top" className="max-w-xs text-xs text-left">
                                    <p className="mb-0.5 font-semibold text-foreground">Confidence Score ({percent}%)</p>
                                    <p className="text-muted-foreground">
                                        Tingkat keyakinan bahwa kalimat kutipan bukti secara solid dan akurat mendukung usulan sinyal strategis ini.
                                    </p>
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <span className="cursor-help text-[11px] text-muted-foreground">
                                        Est. Impact: <b className="text-foreground">{signal.preliminary_impact ?? "N/A"}</b> |
                                        Uncertainty: <b className="text-foreground">{signal.preliminary_uncertainty ?? "N/A"}</b>
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent side="top" className="max-w-xs text-xs text-left space-y-1.5">
                                    <div>
                                        <span className="font-semibold text-foreground">Est. Impact (1–10):</span>
                                        <p className="text-[11px] text-muted-foreground">
                                            Pengaruh terhadap organisasi (1 = minor/lokal, 5 = penting di divisi terkait, 10 = disrupsi industri).
                                        </p>
                                    </div>
                                    <div>
                                        <span className="font-semibold text-foreground">Uncertainty (1–10):</span>
                                        <p className="text-[11px] text-muted-foreground">
                                            Tingkat ketidakpastian peristiwa (1 = sudah terkonfirmasi/terjadi, 5 = tren kuat tapi jadwal belum pasti, 10 = spekulatif).
                                        </p>
                                    </div>
                                </TooltipContent>
                            </Tooltip>

                            <ProviderBadge signal={signal} />
                        </div>
                    );
                },
            },
            {
                accessorKey: "review_status",
                enableSorting: true,
                header: () => (
                    <HeaderWithTooltip
                        title="Status"
                        tooltip="Status kurasi analis: PENDING (menunggu review), ACCEPTED (diterima menjadi Driving Force), atau REJECTED."
                    />
                ),
                cell: ({ getValue }) => {
                    const badge = STATUS_BADGES[getValue()];
                    return <StatusBadge status={badge?.status ?? getValue()} label={badge?.label} />;
                },
            },
            {
                id: "actions",
                header: () => (
                    <HeaderWithTooltip
                        title="Action"
                        tooltip="Tindakan analisis: Inspect (rincian lengkap & SHA-256), Accept as New DF (luluskan jadi Driving Force), Link to DF, atau Reject."
                    />
                ),
                cell: ({ row }) => <ActionButtons signal={row.original} onInspect={openInspect} onAction={openReview} />,
            },
        ],
        []
    );

    const reviewConfig = review ? REVIEW_ACTIONS[review.action] : null;

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ZapIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <PageHeader
                    as="h2"
                    icon={<ZapIcon />}
                    title="Signal-to-Insight Workspace"
                    description="Ingest external publications and review evidence-backed candidate signals before formal repository graduation."
                    badge={
                        <span
                            className={cn(
                                statusBadge(),
                                extractionMode !== "heuristic"
                                    ? "border border-primary/20 bg-primary/10 text-primary font-medium"
                                    : "bg-muted text-muted-foreground"
                            )}
                        >
                            <CpuIcon aria-hidden="true" className="mr-1 inline-block size-3.5" />
                            {extractionMode !== "heuristic"
                                ? `Mesin AI: ${extractionLabel}${extractionModel ? ` (${extractionModel})` : ""}`
                                : "Ekstraksi: Aturan Kata Kunci (Non-AI)"}
                        </span>
                    }
                    actions={
                        <Link
                            href={route("settings.llm.index")}
                            className="inline-flex items-center gap-1.5 rounded-md border border-input bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            <SlidersHorizontalIcon aria-hidden="true" className="size-3.5 text-primary" />
                            <span>Pengaturan Mesin AI</span>
                        </Link>
                    }
                />

                {/* Outside the tabs, so polling continues on either tab. */}
                {extractions.length > 0 && (
                    <section aria-label="Ekstraksi yang sedang berjalan" className="rounded-lg border border-solid border-border bg-card p-4">
                        <ul role="status" className="m-0 flex list-none flex-col gap-2 p-0">
                            {extractions.map((source) => (
                                <ExtractionProgress
                                    key={source.id}
                                    source={source}
                                    label={extractionLabel}
                                    onDone={finishExtraction(source)}
                                />
                            ))}
                        </ul>
                    </section>
                )}

                <Tabs value={tab} onValueChange={setTab} className="gap-4">
                    <TabsList className="h-auto w-full flex-wrap justify-start sm:w-fit">
                        <TabsTrigger value="review" className="px-3 py-1.5">
                            <ClipboardCheckIcon aria-hidden="true" />
                            Candidate Review Queue
                        </TabsTrigger>
                        <TabsTrigger value="ingest" className="px-3 py-1.5">
                            <CloudUploadIcon aria-hidden="true" />
                            Ingest Source Document / URL
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="review" className="flex flex-col gap-4">
                        <FilterBar
                            label="Filter sinyal"
                            actions={
                                <Button variant="outline" className="border-input" onClick={() => setTab("ingest")}>
                                    <CloudUploadIcon aria-hidden="true" />
                                    Ingest New
                                </Button>
                            }
                        >
                            <FilterField label="Status" htmlFor={statusFieldId} className="w-full sm:w-auto">
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger id={statusFieldId} className="w-full border-input bg-card sm:w-[220px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent position="popper">
                                        {STATUS_OPTIONS.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FilterField>
                            <FilterSelect
                                label="Dimensi"
                                allLabel="Semua Dimensi"
                                value={dimension}
                                onChange={setDimension}
                                options={dimensions}
                            />
                            <FilterField label="Cari" className="w-full sm:w-auto">
                                <SearchInput
                                    value={search}
                                    onSearch={setSearch}
                                    label="Cari sinyal"
                                    placeholder="Search title, summary, or quotes..."
                                    className="w-full sm:w-[280px]"
                                />
                            </FilterField>
                        </FilterBar>

                        <DataTable
                            columns={columns}
                            {...table.tableProps}
                            getRowId={(row) => String(row.id)}
                            caption="Antrean review sinyal kandidat"
                        />
                    </TabsContent>

                    <TabsContent value="ingest">
                        <IngestForm
                            loading={ingesting}
                            onSubmit={handleIngest}
                            mode={extractionMode}
                            model={extractionModel}
                            label={extractionLabel}
                            maxChars={maxContentChars}
                        />
                    </TabsContent>
                </Tabs>
            </main>

            <InspectDialog signal={inspected} open={inspectOpen} onOpenChange={setInspectOpen} />

            <FormDialog
                title={reviewConfig?.title ?? ""}
                open={reviewOpen}
                onOpenChange={setReviewOpen}
                formId={REVIEW_FORM_ID}
                btnText={reviewConfig?.submit}
                btnVariant={review?.action === "reject" ? "destructive" : "default"}
                loading={reviewing}
                width={560}
            >
                {review && (
                    <ReviewForm
                        key={`${review.action}-${review.signal.uuid}`}
                        id={REVIEW_FORM_ID}
                        action={review.action}
                        signal={review.signal}
                        dimensions={dimensions}
                        drivingForces={existingDrivingForces}
                        loading={reviewing}
                        onSubmit={handleReview}
                    />
                )}
            </FormDialog>
        </AuthenticatedLayout>
    );
}
