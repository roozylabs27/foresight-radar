import { useMemo, useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    Building2Icon,
    ClockIcon,
    MoreHorizontalIcon,
    Trash2Icon,
    UserPlusIcon,
    UsersIcon,
} from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
import DataTable from "@/Components/DataTable";
import SearchInput from "@/Components/SearchInput";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/Components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";

export default function Members({
    auth,
    members = {},
    invitations = [],
    availableRoles = [],
    maxUsers = 20,
    filters = {},
    title = "Kelola Anggota & Undangan Tim",
}) {
    // Mirrors the server-side manage-members gate; the server stays the authority.
    const canManage = Boolean(auth?.permissions?.includes("manage-members"));
    const [inviteOpen, setInviteOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState(filters?.search || "");

    const isPaginated = Boolean(members && typeof members === "object" && !Array.isArray(members) && members.data);
    const memberList = Array.isArray(members) ? members : (members?.data || []);
    const pagination = isPaginated
        ? members
        : {
            current_page: 1,
            last_page: 1,
            per_page: memberList.length,
            total: memberList.length,
            from: memberList.length > 0 ? 1 : 0,
            to: memberList.length,
        };

    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        name: "",
        role: "admin",
    });

    const handleSearch = (keyword) => {
        setSearchTerm(keyword);
        router.get(
            route("settings.members.index"),
            {
                search: keyword,
                per_page: pagination.per_page || 10,
                page: 1,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleInviteSubmit = (e) => {
        e.preventDefault();
        post(route("settings.members.invite"), {
            onSuccess: () => {
                reset();
                setInviteOpen(false);
            },
        });
    };

    const handleRoleChange = (memberUuid, newRole) => {
        router.patch(route("settings.members.role", { user: memberUuid }), {
            role: newRole,
        });
    };

    const handleRemoveMember = (memberUuid, memberName) => {
        if (confirm(`Apakah Anda yakin ingin mengeluarkan ${memberName} dari organisasi ini?`)) {
            router.delete(route("settings.members.destroy", { user: memberUuid }));
        }
    };

    const handleCancelInvite = (inviteId) => {
        if (confirm("Batalkan undangan ini?")) {
            router.delete(route("settings.members.invitations.cancel", { invitation: inviteId }));
        }
    };

    const columns = useMemo(
        () => [
            {
                accessorKey: "name",
                header: "Pengguna",
                cell: ({ row }) => (
                    <div className="flex flex-col">
                        <div className="flex items-center gap-2 font-medium text-foreground">
                            <span>{row.original.name}</span>
                            {row.original.is_current_user && (
                                <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                                    Anda
                                </Badge>
                            )}
                        </div>
                        <span className="text-xs text-muted-foreground">{row.original.email}</span>
                    </div>
                ),
            },
            {
                accessorKey: "role",
                header: "Peran Akses",
                cell: ({ row }) => (
                    <Badge
                        variant={row.original.role === "admin" ? "default" : "secondary"}
                        className="text-xs capitalize font-medium"
                    >
                        {row.original.role_display || row.original.role}
                    </Badge>
                ),
            },
            {
                accessorKey: "created_at",
                header: "Tanggal Bergabung",
                cell: ({ getValue }) => {
                    const val = getValue();
                    return (
                        <span className="text-xs text-muted-foreground">
                            {val ? new Date(val).toLocaleDateString("id-ID", {
                                day: "numeric",
                                month: "short",
                                year: "numeric",
                            }) : "-"}
                        </span>
                    );
                },
            },
            {
                id: "actions",
                header: () => <span className="sr-only">Aksi</span>,
                meta: { align: "right", width: "56px" },
                cell: ({ row }) => {
                    const member = row.original;
                    if (!canManage || member.is_current_user) return null;
                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="size-8">
                                    <MoreHorizontalIcon className="size-4" />
                                    <span className="sr-only">Menu aksi</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel className="text-xs">Ubah Peran</DropdownMenuLabel>
                                <DropdownMenuItem onClick={() => handleRoleChange(member.uuid, "admin")}>
                                    Jadikan Admin
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleRoleChange(member.uuid, "bod")}>
                                    Jadikan BOD (Viewer)
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => handleRemoveMember(member.uuid, member.name)}
                                >
                                    <Trash2Icon className="size-3.5 mr-1 text-destructive" />
                                    <span>Keluarkan dari Tim</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [canManage]
    );

    const invitationColumns = useMemo(
        () => [
            {
                accessorKey: "name",
                header: "Calon Anggota",
                cell: ({ row }) => (
                    <div className="flex flex-col">
                        <span className="font-medium text-foreground">{row.original.name || row.original.email}</span>
                        <span className="text-xs text-muted-foreground">{row.original.email}</span>
                    </div>
                ),
            },
            {
                accessorKey: "role",
                header: "Peran Akses",
                cell: ({ getValue }) => (
                    <Badge variant="outline" className="text-xs capitalize font-medium">
                        {getValue()}
                    </Badge>
                ),
            },
            {
                accessorKey: "invited_by",
                header: "Diundang Oleh",
                cell: ({ getValue }) => (
                    <span className="text-xs text-muted-foreground">{getValue()}</span>
                ),
            },
            {
                accessorKey: "expires_at",
                header: "Kedaluwarsa",
                cell: ({ getValue }) => (
                    <span className="text-xs text-muted-foreground">
                        {new Date(getValue()).toLocaleDateString("id-ID", {
                            day: "numeric",
                            month: "short",
                            year: "numeric",
                        })}
                    </span>
                ),
            },
            {
                id: "actions",
                header: () => <span className="sr-only">Aksi</span>,
                meta: { align: "right", width: "80px" },
                cell: ({ row }) => canManage && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-xs text-destructive hover:bg-destructive/10"
                        onClick={() => handleCancelInvite(row.original.id)}
                    >
                        Batalkan
                    </Button>
                ),
            },
        ],
        [canManage]
    );

    return (
        <SettingsLayout auth={auth} title={title} activeTab="members">
            <Head title={title} />

            <div className="flex flex-col gap-6">
                {/* Header Actions Card */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-border bg-card p-5 shadow-2xs">
                    <div>
                        <div className="flex items-center gap-2">
                            <UsersIcon className="size-5 text-primary" />
                            <h2 className="text-base font-semibold text-foreground">Anggota Tim &amp; Peran Akses</h2>
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Kelola pengguna yang memiliki akses ke workspace organisasi ini ({pagination.total} dari maks {maxUsers} anggota).
                        </p>
                    </div>

                    {canManage && (
                    <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                        <DialogTrigger asChild>
                            <Button className="gap-2 h-10 px-4 self-start sm:self-auto">
                                <UserPlusIcon className="size-4" />
                                <span>Undang Anggota Baru</span>
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <form onSubmit={handleInviteSubmit}>
                                <DialogHeader>
                                    <DialogTitle>Undang Anggota Tim Baru</DialogTitle>
                                    <DialogDescription className="text-xs">
                                        Kirim email undangan ke rekan kerja. Akun dibuat saat penerima membuka tautan dan menerima undangan; tautan berlaku 7 hari dan hanya dapat dipakai sekali.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="flex flex-col gap-4 py-4">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="invite-email" className="text-xs font-semibold">
                                            Alamat Surel (Email)
                                        </Label>
                                        <Input
                                            id="invite-email"
                                            name="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData("email", e.target.value)}
                                            placeholder="nama@perusahaan.co.id"
                                            required
                                            className="h-9"
                                        />
                                        {errors.email && (
                                            <p className="text-xs text-destructive">{errors.email}</p>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="invite-name" className="text-xs font-semibold">
                                            Nama Lengkap (Opsional)
                                        </Label>
                                        <Input
                                            id="invite-name"
                                            name="name"
                                            value={data.name}
                                            onChange={(e) => setData("name", e.target.value)}
                                            placeholder="Contoh: Budi Pratama"
                                            className="h-9"
                                        />
                                        {errors.name && (
                                            <p className="text-xs text-destructive">{errors.name}</p>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="invite-role" className="text-xs font-semibold">
                                            Peran Akses
                                        </Label>
                                        <select
                                            id="invite-role"
                                            value={data.role}
                                            onChange={(e) => setData("role", e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        >
                                            {availableRoles.map((role) => (
                                                <option key={role.name} value={role.name}>
                                                    {role.display_name} {role.name === "admin" ? "(Admin Workspace)" : "(Read-Only & Review)"}
                                                </option>
                                            ))}
                                            {availableRoles.length === 0 && (
                                                <>
                                                    <option value="admin">Admin Organisasi</option>
                                                    <option value="bod">Board of Directors</option>
                                                </>
                                            )}
                                        </select>
                                    </div>

                                    <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground flex gap-2.5">
                                        <Building2Icon className="size-4 shrink-0 text-primary mt-0.5" />
                                        <p>
                                            Penerima menerima email berisi tautan undangan. Akun dibuat dan dihubungkan ke organisasi ini hanya setelah penerima menerima undangan; kata sandi ditentukan sendiri oleh penerima.
                                        </p>
                                    </div>
                                </div>

                                <DialogFooter className="gap-2 sm:gap-0">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline" size="sm">
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing} size="sm">
                                        {processing ? "Memproses..." : "Kirim Undangan"}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                    )}
                </div>

                {/* Active Members Section with Standard DataTable */}
                <section aria-labelledby="members-list-heading" className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <h3 id="members-list-heading" className="m-0 text-base font-semibold text-foreground">
                                Daftar Anggota Aktif ({pagination.total})
                            </h3>
                            <Badge variant="outline" className="text-[11px] px-2 py-0.5">
                                Kapasitas: {pagination.total} / {maxUsers || 100}
                            </Badge>
                        </div>
                        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <SearchInput
                                value={searchTerm}
                                onSearch={handleSearch}
                                label="Cari nama atau email"
                                placeholder="Cari nama atau email..."
                                className="flex-1 sm:w-[240px] sm:flex-none"
                            />
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={memberList}
                        rowCount={pagination.total}
                        pagination={{
                            pageIndex: Math.max(0, (pagination.current_page || 1) - 1),
                            pageSize: pagination.per_page || 10,
                        }}
                        onPaginationChange={(updater) => {
                            const currentPagination = {
                                pageIndex: Math.max(0, (pagination.current_page || 1) - 1),
                                pageSize: pagination.per_page || 10,
                            };
                            const nextState = typeof updater === "function" ? updater(currentPagination) : updater;
                            router.get(
                                route("settings.members.index"),
                                {
                                    search: searchTerm,
                                    page: nextState.pageIndex + 1,
                                    per_page: nextState.pageSize,
                                },
                                { preserveState: true, replace: true }
                            );
                        }}
                        pageSizeOptions={[10, 20, 50]}
                        getRowId={(row) => row.id}
                        caption="Daftar Anggota Aktif"
                        emptyMessage={searchTerm ? `Tidak ada anggota yang cocok dengan kata kunci "${searchTerm}".` : "Belum ada anggota lain yang terdaftar."}
                    />
                </section>

                {/* Pending Invitations Section (If any) */}
                {invitations.length > 0 && (
                    <section aria-labelledby="invitations-heading" className="flex flex-col gap-3">
                        <div className="flex items-center gap-2">
                            <ClockIcon className="size-4 text-amber-500" />
                            <h3 id="invitations-heading" className="m-0 text-sm font-semibold text-foreground">
                                Undangan Menunggu Aktivasi ({invitations.length})
                            </h3>
                        </div>

                        <DataTable
                            columns={invitationColumns}
                            data={invitations}
                            rowCount={invitations.length}
                            getRowId={(row) => row.id}
                            caption="Daftar Undangan Menunggu Aktivasi"
                        />
                    </section>
                )}
            </div>
        </SettingsLayout>
    );
}
