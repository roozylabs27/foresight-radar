import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { CheckIcon, ClockIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import DataTable, { useServerTable } from "@/Components/DataTable";
import AnalysisFilterBar, { useAnalysisFilters } from "@/Components/AnalysisFilters";
import DimensionChip from "@/Components/DimensionChip";
import FormDialog from "@/Components/FormDialog";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
import FormTimeHorizon from "./Form";

const FORM_ID = "time-horizon-form";

// TimeHorizonResource marks the chosen horizon with "✔" in one of these fields.
const HORIZONS = [
    { key: "short_term", header: "SHORT TERM", label: "Short term", tint: "bg-horizon-short", cellTint: "bg-horizon-short/35" },
    { key: "mid_term", header: "MID TERM", label: "Mid term", tint: "bg-horizon-mid", cellTint: "bg-horizon-mid/35" },
    { key: "long_term", header: "LONG TERM", label: "Long term", tint: "bg-horizon-long", cellTint: "bg-horizon-long/35" },
];

function HorizonMark({ checked, label }) {
    if (!checked) return null;

    return (
        <span className="inline-flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground">
            <CheckIcon aria-hidden="true" />
            <span className="sr-only">{label}</span>
        </span>
    );
}

export default function TableTimeHorizon({ auth, title, dimensions = [], time_horizons = [] }) {
    const listHeadingId = useId();

    const filters = useAnalysisFilters(dimensions);
    const { activeDimension } = filters;
    const table = useServerTable({ url: route("time-horizon.fetch-data"), params: filters.params });

    // Set time horizon dialog
    const [formOpen, setFormOpen] = useState(false);
    const [selected, setSelected] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const openForm = (record) => {
        setSelected(record);
        setFormOpen(true);
    };

    const handleFormSubmit = async (values) => {
        setSubmitting(true);
        try {
            const response = await axios.post(route("time-horizon.create", selected.id), values);
            toast.success(response.data.message);
            setFormOpen(false);
            table.reload();
        } catch (error) {
            if (error.response?.status === 422) {
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return error.response.data.errors;
            }
            toast.error("Failed to set time horizon", {
                description: error.response?.data?.message ?? error.message,
            });
        } finally {
            setSubmitting(false);
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
                enableSorting: true,
                header: "Keyword",
                meta: { className: "min-w-[200px] font-medium text-foreground" },
            },
            {
                accessorKey: "dimension",
                enableSorting: true,
                header: "Dimension",
                cell: ({ getValue }) => <DimensionChip name={getValue()} />,
            },
            ...HORIZONS.map((horizon) => ({
                accessorKey: horizon.key,
                header: horizon.header,
                meta: {
                    align: "center",
                    width: "128px",
                    headerClassName: cn(horizon.tint, "text-foreground"),
                    className: horizon.cellTint,
                },
                cell: ({ getValue }) => <HorizonMark checked={!!getValue()} label={horizon.label} />,
            })),
            {
                id: "actions",
                header: () => <span className="sr-only">Aksi</span>,
                meta: { align: "right", width: "150px" },
                cell: ({ row }) => (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => openForm(row.original)}
                        aria-label={`Set Time Horizon untuk ${row.original.keyword}`}
                        className="border-input"
                    >
                        <ClockIcon aria-hidden="true" />
                        Set Horizon
                    </Button>
                ),
            },
        ],
        [pageIndex, pageSize]
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ClockIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Horizon Assessment
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
                            Horizon per Driving Force
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
                        getRowId={(row) => String(row.id)}
                        caption={`Time horizon ${activeDimension ? activeDimension.label : "overall"}`}
                    />
                </section>
            </main>

            <FormDialog
                title={`Set Time Horizon - ${selected?.keyword ?? ""}`}
                open={formOpen}
                onOpenChange={setFormOpen}
                formId={FORM_ID}
                btnText="Set"
                loading={submitting}
                width={560}
            >
                <FormTimeHorizon
                    id={FORM_ID}
                    initialValues={selected ?? {}}
                    time_horizons={time_horizons}
                    loading={submitting}
                    onSubmit={handleFormSubmit}
                />
            </FormDialog>
        </AuthenticatedLayout>
    );
}
