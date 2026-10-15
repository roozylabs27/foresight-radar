import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { InboxIcon, Undo2Icon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { useAnalysisFilters } from "@/Components/AnalysisFilters";
import DataTable, { useServerTable } from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import FormDialog from "@/Components/FormDialog";
import { reviewColumns, StatusWithDate } from "@/Components/ReviewColumns";
import SearchInput from "@/Components/SearchInput";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
import FormReopen from "./Form";

const FORM_ID = "reopen-form";

export default function TableClosedItems({ auth, title, dimensions = [] }) {
    const listHeadingId = useId();
    // The reopen route is gated by the same permission as approving.
    const canReopen = (auth?.permissions ?? []).includes("create-approval-items");

    const filters = useAnalysisFilters(dimensions);
    const { activeDimension } = filters;
    const [search, setSearch] = useState("");
    const table = useServerTable({
        url: route("closed-items.fetch-data"),
        params: { ...filters.params, search: search || undefined },
    });

    const handleResetFilter = () => {
        filters.reset();
        setSearch("");
    };

    // Reopen dialog
    const [formOpen, setFormOpen] = useState(false);
    const [selected, setSelected] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const openReopen = (record) => {
        setSelected(record);
        setFormOpen(true);
    };

    const handleReopen = async ({ reason }) => {
        setSubmitting(true);
        try {
            const response = await axios.post(route("closed-items.reopen", selected.id), { reason });
            toast.success(response.data.message);
            setFormOpen(false);
            table.reload();
        } catch (error) {
            const errors = error.response?.data?.errors;
            // 422 is either the validation bag or a state-transition message string.
            if (error.response?.status === 422 && errors && typeof errors === "object") {
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return errors;
            }
            toast.error("Failed to reopen item", {
                description: typeof errors === "string" ? errors : (error.response?.data?.message ?? error.message),
            });
        } finally {
            setSubmitting(false);
        }
    };

    const columns = useMemo(
        () => [
            ...reviewColumns({
                status: (record) => <StatusWithDate status={record.status} date={record.closed_at} />,
            }),
            ...(canReopen
                ? [
                      {
                          id: "actions",
                          header: () => <span className="sr-only">Aksi</span>,
                          meta: { align: "center" },
                          cell: ({ row }) => (
                              <Button
                                  size="sm"
                                  onClick={() => openReopen(row.original)}
                                  aria-label={`Reopen ${row.original.keyword}`}
                                  className="rounded-full"
                              >
                                  <Undo2Icon aria-hidden="true" />
                                  Reopen
                              </Button>
                          ),
                      },
                  ]
                : []),
        ],
        [canReopen]
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <InboxIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Archive Repository
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
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 id={listHeadingId} className="m-0 text-base font-semibold text-foreground">
                                Daftar Arsip
                            </h2>
                            {activeDimension ? (
                                <DimensionChip name={activeDimension.label} />
                            ) : (
                                <span className={statusBadge()}>Overall</span>
                            )}
                        </div>
                        <SearchInput
                            value={search}
                            onSearch={setSearch}
                            label="Cari data arsip"
                            placeholder="Cari data arsip..."
                            className="w-full sm:w-[260px]"
                        />
                    </div>

                    <DataTable
                        columns={columns}
                        {...table.tableProps}
                        getRowId={(row) => row.id}
                        caption="Daftar driving force yang ditutup"
                    />
                </section>
            </main>

            <FormDialog
                title={`Reopen Driving Force: ${selected?.keyword ?? ""}`}
                open={formOpen}
                onOpenChange={setFormOpen}
                formId={FORM_ID}
                btnText="Aktifkan Kembali"
                cancelText="Batal"
                loading={submitting}
                width={520}
            >
                <FormReopen id={FORM_ID} loading={submitting} onSubmit={handleReopen} />
            </FormDialog>
        </AuthenticatedLayout>
    );
}
