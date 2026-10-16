import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { CrosshairIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { useAnalysisFilters } from "@/Components/AnalysisFilters";
import DataTable, { useServerTable } from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import FormDialog from "@/Components/FormDialog";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
import FormStatusAction from "./Form";

const FORM_ID = "status-action-form";

// StatusActionResource puts the status code ("?" / "O") in the column of the chosen status.
const STATUS_COLUMNS = [
    { key: "monitoring", header: "MONITORING", label: "Monitoring", tone: "bg-warning-soft text-warning" },
    { key: "decided_plan", header: "DECIDED PLAN", label: "Decided Plan", tone: "bg-success-soft text-success" },
];

function StatusMark({ code, label, tone }) {
    if (!code) return null;

    return (
        <span
            title={label}
            className={cn("inline-flex size-8 items-center justify-center rounded-full text-sm font-bold", tone)}
        >
            <span aria-hidden="true">{code}</span>
            <span className="sr-only">{label}</span>
        </span>
    );
}

export default function TableStatusAction({ auth, title, dimensions = [], status_actions = [] }) {
    const listHeadingId = useId();

    const filters = useAnalysisFilters(dimensions);
    const { activeDimension } = filters;
    const table = useServerTable({ url: route("status-action.fetch-data"), params: filters.params });

    // Set status action dialog
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
            const response = await axios.post(route("status-action.create", selected.id), values);
            toast.success(response.data.message);
            setFormOpen(false);
            table.reload();
        } catch (error) {
            if (error.response?.status === 422) {
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return error.response.data.errors;
            }
            toast.error("Failed to set status action", {
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
            ...STATUS_COLUMNS.map((status) => ({
                accessorKey: status.key,
                header: status.header,
                meta: { align: "center", width: "140px" },
                cell: ({ getValue }) => <StatusMark code={getValue()} label={status.label} tone={status.tone} />,
            })),
            {
                id: "actions",
                header: () => <span className="sr-only">Aksi</span>,
                meta: { align: "right", width: "170px" },
                cell: ({ row }) => (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => openForm(row.original)}
                        aria-label={`Set Status Action untuk ${row.original.keyword}`}
                        className="border-input"
                    >
                        <CrosshairIcon aria-hidden="true" />
                        Set Status
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
                    <CrosshairIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Strategic Actions
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
                            Status per Driving Force
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
                        caption={`Status of action ${activeDimension ? activeDimension.label : "overall"}`}
                    />
                </section>
            </main>

            <FormDialog
                title={`Set Status Action - ${selected?.keyword ?? ""}`}
                open={formOpen}
                onOpenChange={setFormOpen}
                formId={FORM_ID}
                btnText="Save Changes"
                loading={submitting}
                width={1040}
            >
                <FormStatusAction
                    id={FORM_ID}
                    initialValues={selected ?? {}}
                    status_actions={status_actions}
                    loading={submitting}
                    onSubmit={handleFormSubmit}
                />
            </FormDialog>
        </AuthenticatedLayout>
    );
}
