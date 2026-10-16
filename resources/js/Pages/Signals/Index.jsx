import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
    CirclePlusIcon,
    CircleXIcon,
    ClipboardCheckIcon,
    CloudUploadIcon,
    EyeIcon,
    LinkIcon,
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
                            </DetailItem>
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
                            <DetailItem label="Content SHA-256 Hash" className="sm:col-span-2">
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

export default function SignalIndex({ auth, title, dimensions = [], existingDrivingForces = [] }) {
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
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return errors;
            }
            toast.error("Action failed to execute.", { description: errorMessage(error, error.message) });
        } finally {
            setReviewing(false);
        }
    };

    // Ingest
    const [ingesting, setIngesting] = useState(false);

    const handleIngest = async (payload) => {
        setIngesting(true);
        try {
            const response = await axios.post("/signals/ingest", payload);
            toast.success(response.data.message || "Content ingested successfully.");
            setTab("review");
            setStatus("PENDING");
            table.reload();
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
                header: "Signal Title & Evidence",
                cell: ({ row }) => <SignalCell signal={row.original} />,
            },
            {
                id: "taxonomy",
                enableSorting: true,
                header: "Taxonomy & Origin",
                cell: ({ row }) => {
                    const signal = row.original;
                    return (
                        <div className="flex min-w-[150px] flex-col items-start gap-1.5">
                            <DimensionChip name={signal.dimension?.name || "Unassigned"} />
                            {signal.suggested_time_horizon && (
                                <span className={cn(statusBadge(), "bg-accent text-primary")}>
                                    {signal.suggested_time_horizon.name}
                                </span>
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
                header: "Assessment & Confidence",
                cell: ({ row }) => {
                    const signal = row.original;
                    const percent = confidence(signal);
                    return (
                        <div className="flex min-w-[170px] flex-col items-start gap-1.5 text-xs">
                            <span className="flex items-center gap-1.5 font-medium text-foreground">
                                <span
                                    aria-hidden="true"
                                    className={cn("size-2 rounded-full", percent >= 80 ? "bg-success" : "bg-warning")}
                                />
                                {percent}% confidence
                            </span>
                            <span className="text-[11px] text-muted-foreground">
                                Est. Impact: <b className="text-foreground">{signal.preliminary_impact ?? "N/A"}</b> |
                                Uncertainty: <b className="text-foreground">{signal.preliminary_uncertainty ?? "N/A"}</b>
                            </span>
                            <span className={cn(statusBadge(), signal.is_ai_generated && "bg-accent text-primary")}>
                                {signal.is_ai_generated ? "AI Candidate" : "Human Extracted"}
                            </span>
                        </div>
                    );
                },
            },
            {
                accessorKey: "review_status",
                enableSorting: true,
                header: "Status",
                cell: ({ getValue }) => {
                    const badge = STATUS_BADGES[getValue()];
                    return <StatusBadge status={badge?.status ?? getValue()} label={badge?.label} />;
                },
            },
            {
                id: "actions",
                header: "Action",
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
                />

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
                        <IngestForm loading={ingesting} onSubmit={handleIngest} />
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
