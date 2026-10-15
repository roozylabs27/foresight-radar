import { useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { DeleteOutlined, EditOutlined, MoreOutlined, PlusOutlined, UserOutlined } from "@ant-design/icons";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DataTable, { useServerTable } from "@/Components/DataTable";
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
import { cn } from "@/lib/utils";
import FormUser from "./Form";

const FORM_ID = "user-form";

function RowActions({ record, onEdit, onDelete }) {
    return (
        // Non-modal so the dialogs opened from it get focus and pointer events cleanly.
        <DropdownMenu modal={false}>
            {/* Native trigger, not <Button asChild>: the ui Button has no forwardRef on React 18. */}
            <DropdownMenuTrigger
                aria-label={`Aksi untuk ${record.name}`}
                className={buttonVariants({ variant: "ghost", size: "icon-sm" })}
            >
                <MoreOutlined aria-hidden="true" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-36">
                <DropdownMenuItem onSelect={() => onEdit(record)}>
                    <EditOutlined aria-hidden="true" />
                    Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={() => onDelete(record)}>
                    <DeleteOutlined aria-hidden="true" />
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function TableUser({ auth, title, roles = [] }) {
    const listHeadingId = useId();

    const [search, setSearch] = useState("");
    const table = useServerTable({
        url: route("user-management.user.fetch-data"),
        params: { search: search || undefined },
    });

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
            // UserRequest applies the create rules (password, name length) only when `type` is "create".
            const response = editing
                ? await axios.put(route("user-management.user.update", editing.id), values)
                : await axios.post(route("user-management.user.create"), { ...values, type: "create" });

            toast.success(response.data.message);
            setFormOpen(false);
            table.reload();
        } catch (error) {
            if (error.response?.status === 422) {
                toast.error("Validation failed", { description: "Periksa kembali isian yang ditandai." });
                return error.response.data.errors;
            }
            toast.error(`Failed to ${editing ? "update" : "create"} user`, {
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
            const response = await axios.delete(route("user-management.user.delete", deleteTarget.id));
            toast.success(response.data.message);
            table.reload();
        } catch (error) {
            toast.error(`Failed to delete user ${deleteTarget.name}`, {
                description: error.response?.data?.message ?? error.message,
            });
            throw error;
        }
    };

    const columns = useMemo(
        () => [
            {
                accessorKey: "date_created",
                header: "Date Created",
                meta: { className: "whitespace-nowrap" },
            },
            {
                accessorKey: "name",
                header: "Name",
                meta: { className: "font-medium text-foreground" },
            },
            {
                accessorKey: "email",
                header: "Email",
                meta: { className: "text-muted-foreground" },
            },
            {
                accessorKey: "role",
                header: "Role/Division",
                cell: ({ getValue }) => (
                    <span className={cn(statusBadge(), "bg-accent text-primary")}>{getValue() || "-"}</span>
                ),
            },
            {
                id: "actions",
                header: () => <span className="sr-only">Aksi</span>,
                meta: { align: "right", width: "56px" },
                cell: ({ row }) => <RowActions record={row.original} onEdit={openEdit} onDelete={confirmDelete} />,
            },
        ],
        []
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <UserOutlined aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Access &amp; Roles
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <section aria-labelledby={listHeadingId} className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id={listHeadingId} className="m-0 text-base font-semibold text-foreground">
                            Daftar Pengguna
                        </h2>
                        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <SearchInput
                                value={search}
                                onSearch={setSearch}
                                label="Cari pengguna"
                                placeholder="Cari pengguna..."
                                className="flex-1 sm:w-[240px] sm:flex-none"
                            />
                            <Button onClick={openCreate}>
                                <PlusOutlined aria-hidden="true" />
                                Tambah Pengguna
                            </Button>
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        {...table.tableProps}
                        getRowId={(row) => row.id}
                        caption="Daftar pengguna"
                    />
                </section>
            </main>

            <FormDialog
                title={editing ? `Update User - ${editing.name}` : "Create User"}
                open={formOpen}
                onOpenChange={setFormOpen}
                isEditMode={!!editing}
                formId={FORM_ID}
                loading={submitting}
                width={520}
            >
                <FormUser
                    id={FORM_ID}
                    isEditMode={!!editing}
                    initialValues={editing ?? {}}
                    roles={roles}
                    loading={submitting}
                    onSubmit={handleFormSubmit}
                />
            </FormDialog>

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title="Delete user"
                description={`Are you sure to delete ${deleteTarget?.name ?? "this user"}? This can't be undone.`}
                confirmText="Delete"
                cancelText="Cancel"
                onConfirm={handleDelete}
            />
        </AuthenticatedLayout>
    );
}
