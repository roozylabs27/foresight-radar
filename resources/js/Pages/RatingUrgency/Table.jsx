import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { FlameIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { useAnalysisFilters } from "@/Components/AnalysisFilters";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DataTable, { useServerTable } from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";

const RATINGS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
const TYPE_LABELS = { uncertainty: "Uncertainty", impact: "Impact" };

/** 1–10 scale; picking a value asks for confirmation before it is saved. */
function RatingScale({ record, type, onPick }) {
    const current = record[type];

    return (
        <div role="group" aria-label={`${TYPE_LABELS[type]} untuk ${record.keyword}`} className="flex justify-center gap-1">
            {RATINGS.map((value) => {
                const active = value === current;
                return (
                    <button
                        key={value}
                        type="button"
                        aria-pressed={active}
                        onClick={() => !active && onPick({ record, type, value })}
                        className={cn(
                            "inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md border border-solid text-sm font-semibold tabular-nums outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:transition-none",
                            active
                                ? "cursor-default border-warning bg-warning text-primary-foreground"
                                : "border-border bg-card text-foreground hover:border-warning hover:bg-warning-soft hover:text-warning"
                        )}
                    >
                        {value}
                    </button>
                );
            })}
        </div>
    );
}

export default function TableRatingUrgency({ auth, title, dimensions = [] }) {
    const listHeadingId = useId();

    const filters = useAnalysisFilters(dimensions);
    const { activeDimension } = filters;
    const table = useServerTable({ url: route("rating-urgency.fetch-data"), params: filters.params });

    // Confirm before saving; the pick stays set while the dialog animates closed.
    const [pending, setPending] = useState(null);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const pickRating = (pick) => {
        setPending(pick);
        setConfirmOpen(true);
    };

    const saveRating = async () => {
        const { record, type, value } = pending;
        try {
            const response = await axios.post(route("rating-urgency.create", record.id), {
                uuid: record.id,
                type,
                value,
            });
            toast.success(response.data.message);
            table.reload();
        } catch (error) {
            toast.error("Failed to set rating", {
                description: error.response?.data?.message ?? error.message,
            });
            throw error;
        }
    };

    const { pageIndex, pageSize } = table.pagination;

    const columns = useMemo(
        () => [
            {
                id: "no",
                header: "No",
                meta: { align: "center", width: "64px", className: "tabular-nums text-muted-foreground" },
                cell: ({ row }) => pageIndex * pageSize + row.index + 1,
            },
            {
                accessorKey: "keyword",
                header: "ITEM",
                meta: { className: "min-w-[200px]" },
                cell: ({ row }) => (
                    <div className="flex flex-col items-start gap-1.5">
                        <span className="font-medium text-foreground">{row.original.keyword}</span>
                        <DimensionChip name={row.original.dimension} />
                    </div>
                ),
            },
            {
                id: "rating",
                header: "RATING",
                columns: ["uncertainty", "impact"].map((type) => ({
                    accessorKey: type,
                    header: TYPE_LABELS[type],
                    meta: { align: "center" },
                    cell: ({ row }) => <RatingScale record={row.original} type={type} onPick={pickRating} />,
                })),
            },
        ],
        [pageIndex, pageSize]
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <FlameIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Urgency Scoring
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar filters={filters} dimensions={dimensions} />

                <section aria-labelledby={listHeadingId} className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 id={listHeadingId} className="m-0 text-base font-semibold text-foreground">
                            Rating per Driving Force
                        </h2>
                        {activeDimension ? (
                            <DimensionChip name={activeDimension.label} />
                        ) : (
                            <span className={statusBadge()}>Overall</span>
                        )}
                    </div>

                    <DataTable
                        columns={columns}
                        {...table.tableProps}
                        getRowId={(row) => row.id}
                        caption={`Rating of urgency ${activeDimension ? activeDimension.label : "overall"}`}
                    />
                </section>
            </main>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                variant="default"
                title={`Set ${TYPE_LABELS[pending?.type] ?? ""} rating`}
                description={
                    pending &&
                    `Are you sure to set rating ${pending.type} ${pending.value} for ${pending.record.keyword}?`
                }
                confirmText="Yes"
                cancelText="No"
                onConfirm={saveRating}
            />
        </AuthenticatedLayout>
    );
}
