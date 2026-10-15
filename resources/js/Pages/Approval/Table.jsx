import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { CheckOutlined, CloseOutlined, SafetyCertificateOutlined } from "@ant-design/icons";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { useAnalysisFilters } from "@/Components/AnalysisFilters";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DataTable, { useServerTable } from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import { reviewColumns, StatusWithDate } from "@/Components/ReviewColumns";
import SearchInput from "@/Components/SearchInput";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";

// Pending items can be shown (approved); anything else can be taken out (closed).
function approvalAction(record) {
    return record.status === "PENDING"
        ? { status: "APPROVED", text: "show", label: "Show", icon: CheckOutlined }
        : { status: "CLOSED", text: "take out", label: "Take out", icon: CloseOutlined };
}

export default function TableApproval({ auth, title, dimensions = [] }) {
    const listHeadingId = useId();
    const canApprove = (auth?.permissions ?? []).includes("create-approval-items");

    const filters = useAnalysisFilters(dimensions);
    const { activeDimension } = filters;
    const [search, setSearch] = useState("");
    const table = useServerTable({
        url: route("approval-items.fetch-data"),
        params: { ...filters.params, search: search || undefined },
    });

    const handleResetFilter = () => {
        filters.reset();
        setSearch("");
    };

    // Show / take out, after confirmation; the target stays set while the dialog animates closed.
    const [target, setTarget] = useState(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const action = target ? approvalAction(target) : null;

    const confirmAction = (record) => {
        setTarget(record);
        setConfirmOpen(true);
    };

    const runAction = async () => {
        const { status, text } = approvalAction(target);
        try {
            const response = await axios.post(route("approval-items.create", target.id), { status, text });
            toast.success(response.data.message);
            table.reload();
        } catch (error) {
            toast.error("Failed to process approval item", {
                description: error.response?.data?.message ?? error.message,
            });
            throw error;
        }
    };

    const columns = useMemo(
        () => [
            ...reviewColumns({
                status: (record) => (
                    <StatusWithDate
                        status={record.status}
                        date={record.status === "PENDING" ? null : record.approved_at}
                    />
                ),
            }),
            ...(canApprove
                ? [
                      {
                          id: "actions",
                          header: () => <span className="sr-only">Aksi</span>,
                          meta: { align: "center" },
                          cell: ({ row }) => {
                              const { label, icon: Icon } = approvalAction(row.original);
                              return (
                                  <Button
                                      size="sm"
                                      onClick={() => confirmAction(row.original)}
                                      aria-label={`${label} ${row.original.keyword}`}
                                      className="rounded-full"
                                  >
                                      {label}
                                      <Icon aria-hidden="true" />
                                  </Button>
                              );
                          },
                      },
                  ]
                : []),
        ],
        [canApprove]
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <SafetyCertificateOutlined aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Review &amp; Consensus
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
                                Daftar Persetujuan
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
                            label="Cari data persetujuan"
                            placeholder="Cari data persetujuan..."
                            className="w-full sm:w-[260px]"
                        />
                    </div>

                    <DataTable
                        columns={columns}
                        {...table.tableProps}
                        getRowId={(row) => row.id}
                        caption="Daftar persetujuan driving force"
                    />
                </section>
            </main>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                variant={action?.status === "CLOSED" ? "destructive" : "default"}
                title={action ? `${action.label} ${target.keyword}` : ""}
                description={action && `Are you sure to ${action.text} this ${target.keyword}?`}
                confirmText="Yes"
                cancelText="No"
                onConfirm={runAction}
            />
        </AuthenticatedLayout>
    );
}
