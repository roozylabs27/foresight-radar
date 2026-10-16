import { useId, useMemo, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
    ArrowRightIcon,
    EllipsisVerticalIcon,
    GlobeIcon,
    PencilIcon,
    PlusIcon,
    RadarIcon,
    Trash2Icon,
    ZapIcon,
} from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DataTable, { useServerTable } from "@/Components/DataTable";
import AnalysisFilterBar, { useAnalysisFilters } from "@/Components/AnalysisFilters";
import DimensionChip from "@/Components/DimensionChip";
import FormDialog from "@/Components/FormDialog";
import SearchInput from "@/Components/SearchInput";
import { statusBadge } from "@/Components/StatusBadge";
import { Button, buttonVariants } from "@/Components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/Components/ui/sheet";
import { useReturnFocus } from "@/hooks/use-return-focus";
import { cn } from "@/lib/utils";
import FormDrivingForce from "./Form";

const FORM_ID = "driving-force-form";

// DrivingForceResource sends antd tag colors for the pipeline stage.
const PIPELINE_TONES = {
    gold: "bg-warning-soft text-warning",
    orange: "bg-warning-soft text-warning",
    cyan: "bg-accent text-primary",
    blue: "bg-accent text-primary",
    green: "bg-success-soft text-success",
    red: "bg-danger-soft text-danger",
};

function PipelineStage({ pipeline }) {
    if (!pipeline) return null;

    return (
        <div className="flex flex-col items-center gap-1">
            <span className={cn(statusBadge(), PIPELINE_TONES[pipeline.badge_color])}>
                <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />
                {pipeline.label}
            </span>
            {pipeline.next_url && (
                <Link
                    href={pipeline.next_url}
                    className="inline-flex items-center gap-1 rounded-sm text-xs font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    Lanjut ke {pipeline.next_label}
                    <ArrowRightIcon aria-hidden="true" className="text-[10px]" />
                </Link>
            )}
        </div>
    );
}

function SignalsCell({ record, onView }) {
    if (!record.signals_count) {
        return <span className={statusBadge()}>Direct</span>;
    }

    return (
        <button
            type="button"
            onClick={() => onView(record)}
            className={cn(
                statusBadge(),
                "cursor-pointer border-0 bg-accent text-primary outline-none hover:bg-accent/70 focus-visible:ring-[3px] focus-visible:ring-ring/50"
            )}
        >
            <ZapIcon aria-hidden="true" />
            {record.signals_count} sinyal
        </button>
    );
}

function RowActions({ record, onViewSignals, onEdit, onDelete }) {
    return (
        // Non-modal so the dialogs opened from it get focus and pointer events cleanly.
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger
                aria-label={`Aksi untuk ${record.keyword}`}
                className={buttonVariants({ variant: "ghost", size: "icon-sm" })}
            >
                <EllipsisVerticalIcon aria-hidden="true" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-44">
                <DropdownMenuItem onSelect={() => onViewSignals(record)}>
                    <ZapIcon aria-hidden="true" className="text-primary" />
                    Sinyal Asal ({record.signals_count || 0})
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => onEdit(record)}>
                    <PencilIcon aria-hidden="true" />
                    Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={() => onDelete(record)}>
                    <Trash2Icon aria-hidden="true" />
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function SourceLink({ url, title }) {
    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex min-w-0 items-start gap-1.5 text-xs text-primary underline-offset-4 hover:underline"
        >
            <GlobeIcon aria-hidden="true" className="mt-0.5" />
            <span className="break-all">{title || url}</span>
        </a>
    );
}

function SignalsSheet({ record, open, onOpenChange }) {
    const returnFocus = useReturnFocus(open);
    const supporting = record?.supporting_signals ?? [];
    const source = record?.source_signal;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" onCloseAutoFocus={returnFocus} className="w-full gap-0 sm:max-w-[560px]">
                <SheetHeader className="border-b pr-12">
                    <SheetTitle className="flex items-center gap-2">
                        <ZapIcon aria-hidden="true" className="text-primary" />
                        <span>Originating Intelligence: {record?.keyword}</span>
                    </SheetTitle>
                    <SheetDescription>Sinyal yang menjadi asal dan bukti pendukung driving force ini.</SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto p-4">
                    {!record?.signals_count ? (
                        <div className="px-4 py-8 text-center text-muted-foreground">
                            <p className="m-0 mb-2 text-[15px] font-medium text-foreground">Tidak ada sinyal terhubung</p>
                            <p className="m-0 text-sm">
                                Driving force ini dibuat secara manual tanpa melalui promosi sinyal lemah eksternal.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-6">
                            {source && (
                                <article className="flex flex-col gap-3 rounded-lg border border-solid border-primary/40 bg-card p-4">
                                    <span className={cn(statusBadge(), "bg-accent text-primary")}>Primary Origin Signal</span>
                                    <h3 className="m-0 text-sm font-semibold text-foreground">{source.title}</h3>
                                    {source.summary && (
                                        <p className="m-0 text-[13px] text-muted-foreground">{source.summary}</p>
                                    )}
                                    {source.source_url && (
                                        <p className="m-0 flex flex-wrap items-start gap-1.5 text-xs text-muted-foreground">
                                            Sumber: <SourceLink url={source.source_url} title={source.source_title} />
                                        </p>
                                    )}
                                </article>
                            )}

                            {supporting.length > 0 && (
                                <section className="flex flex-col gap-3">
                                    <h3 className="m-0 text-sm font-semibold text-foreground">
                                        Supporting Evidence Signals ({supporting.length})
                                    </h3>
                                    <ul className="m-0 flex list-none flex-col gap-3 p-0">
                                        {supporting.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex flex-col gap-2 rounded-lg border border-solid border-border bg-card p-4"
                                            >
                                                <p className="m-0 text-sm font-semibold text-foreground">{item.title}</p>
                                                {item.summary && (
                                                    <p className="m-0 text-[13px] text-muted-foreground">{item.summary}</p>
                                                )}
                                                {item.notes && (
                                                    <p className="m-0 text-xs text-muted-foreground italic">
                                                        Catatan: {item.notes}
                                                    </p>
                                                )}
                                                {item.source_url && (
                                                    <SourceLink url={item.source_url} title={item.source_title} />
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

export default function TableDrivingForce({ auth, title, dimensions = [], users = [] }) {
    const listHeadingId = useId();

    // Filters
    const filters = useAnalysisFilters(dimensions);
    const [search, setSearch] = useState("");

    const table = useServerTable({
        url: route("driving-force.fetch-data"),
        params: { ...filters.params, search: search || undefined },
    });

    const handleResetFilter = () => {
        filters.reset();
        setSearch("");
    };

    // Create / edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (record) => {
        setEditing(record);
        setFormOpen(true);
    };

    const handleFormSubmit = async (values) => {
        setSubmitting(true);
        try {
            const response = editing
                ? await axios.put(route("driving-force.update", editing.id), values)
                : await axios.post(route("driving-force.create"), values);

            toast.success(response.data.message);
            setFormOpen(false);
            table.reload();
        } catch (error) {
            if (error.response?.status === 422) {
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return error.response.data.errors;
            }
            toast.error(`Failed to ${editing ? "update" : "create"} signal changes`, {
                description: error.response?.data?.message ?? error.message,
            });
        } finally {
            setSubmitting(false);
        }
    };

    // Delete; the target stays set while the dialog animates closed.
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const confirmDelete = (record) => {
        setDeleteTarget(record);
        setDeleteOpen(true);
    };

    const handleDelete = async () => {
        try {
            const response = await axios.delete(route("driving-force.delete", deleteTarget.id));
            toast.success(response.data.message);
            table.reload();
        } catch (error) {
            toast.error("Failed to delete signal changes", {
                description: error.response?.data?.message ?? error.message,
            });
            throw error;
        }
    };

    // Originating signals sheet; the record stays set while the sheet animates closed.
    const [signalsRecord, setSignalsRecord] = useState(null);
    const [signalsOpen, setSignalsOpen] = useState(false);

    const viewSignals = (record) => {
        setSignalsRecord(record);
        setSignalsOpen(true);
    };

    const columns = useMemo(
        () => [
            {
                accessorKey: "date_created",
                enableSorting: true,
                header: "Date Created",
                // Already formatted by the model ("Sab, 10 Oktober 2026").
                meta: { className: "whitespace-nowrap" },
            },
            {
                accessorKey: "dimension",
                enableSorting: true,
                header: "Dimension",
                cell: ({ getValue }) => <DimensionChip name={getValue()} />,
            },
            {
                accessorKey: "keyword",
                enableSorting: true,
                header: "Keyword",
                meta: { className: "font-medium text-foreground" },
            },
            {
                accessorKey: "description",
                header: "Description",
                meta: { className: "min-w-[280px] max-w-[440px] text-muted-foreground" },
            },
            {
                accessorKey: "pic",
                enableSorting: true,
                header: "Personal In Charge",
                cell: ({ getValue }) => getValue() || "-",
            },
            {
                accessorKey: "signals_count",
                header: "Signals",
                meta: { align: "center" },
                cell: ({ row }) => <SignalsCell record={row.original} onView={viewSignals} />,
            },
            {
                accessorKey: "pipeline",
                header: "Pipeline Stage",
                meta: { align: "center", className: "min-w-[180px]" },
                cell: ({ getValue }) => <PipelineStage pipeline={getValue()} />,
            },
            {
                id: "actions",
                header: () => <span className="sr-only">Aksi</span>,
                meta: { align: "right", width: "56px" },
                cell: ({ row }) => (
                    <RowActions
                        record={row.original}
                        onViewSignals={viewSignals}
                        onEdit={openEdit}
                        onDelete={confirmDelete}
                    />
                ),
            },
        ],
        []
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <RadarIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Signal Repository
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar
                    filters={filters}
                    dimensions={dimensions}
                    isFiltered={filters.isFiltered || search !== ""}
                    onReset={handleResetFilter}
                />

                <section aria-labelledby={listHeadingId} className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id={listHeadingId} className="m-0 text-base font-semibold text-foreground">
                            Daftar Sinyal Perubahan
                        </h2>
                        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <SearchInput
                                value={search}
                                onSearch={setSearch}
                                label="Cari kata kunci"
                                placeholder="Cari kata kunci..."
                                className="flex-1 sm:w-[240px] sm:flex-none"
                            />
                            <Button onClick={openCreate}>
                                <PlusIcon aria-hidden="true" />
                                Tambah Sinyal
                            </Button>
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        {...table.tableProps}
                        getRowId={(row) => row.id}
                        caption="Daftar sinyal perubahan"
                    />
                </section>
            </main>

            <FormDialog
                title={editing ? "Update Signal Changes" : "Create Signal Changes"}
                open={formOpen}
                onOpenChange={setFormOpen}
                isEditMode={!!editing}
                formId={FORM_ID}
                loading={submitting}
                width={560}
            >
                <FormDrivingForce
                    id={FORM_ID}
                    initialValues={editing ?? {}}
                    dimensions={dimensions}
                    users={users}
                    loading={submitting}
                    onSubmit={handleFormSubmit}
                />
            </FormDialog>

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title="Delete signal changes"
                description={`Are you sure you want to delete "${deleteTarget?.keyword ?? ""}"? This can't be undone.`}
                confirmText="Delete"
                cancelText="Cancel"
                onConfirm={handleDelete}
            />

            <SignalsSheet record={signalsRecord} open={signalsOpen} onOpenChange={setSignalsOpen} />
        </AuthenticatedLayout>
    );
}
