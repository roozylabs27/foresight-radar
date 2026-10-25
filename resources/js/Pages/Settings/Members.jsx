import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    AlertCircleIcon,
    Building2Icon,
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClockIcon,
    MailIcon,
    MoreHorizontalIcon,
    SearchIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserPlusIcon,
    UsersIcon,
    XIcon,
} from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
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
    const [inviteOpen, setInviteOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState(filters?.search || "");
    const [perPage, setPerPage] = useState(String(filters?.per_page || 10));

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
            links: [],
            prev_page_url: null,
            next_page_url: null,
        };

    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        name: "",
        role: "admin",
    });

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        router.get(
            route("settings.members.index"),
            {
                search: searchTerm,
                per_page: perPage,
                page: 1,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleClearSearch = () => {
        setSearchTerm("");
        router.get(
            route("settings.members.index"),
            {
                search: "",
                per_page: perPage,
                page: 1,
            },
            { preserveState: true, replace: true }
        );
    };

    const handlePerPageChange = (val) => {
        setPerPage(val);
        router.get(
            route("settings.members.index"),
            {
                search: searchTerm,
                per_page: val,
                page: 1,
            },
            { preserveState: true, replace: true }
        );
    };

    const handlePageChange = (url) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, replace: true });
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

    const handleRoleChange = (memberId, newRole) => {
        router.patch(route("settings.members.role", { user: memberId }), {
            role: newRole,
        });
    };

    const handleRemoveMember = (memberId, memberName) => {
        if (confirm(`Apakah Anda yakin ingin mengeluarkan ${memberName} dari organisasi ini?`)) {
            router.delete(route("settings.members.destroy", { user: memberId }));
        }
    };

    const handleCancelInvite = (inviteId) => {
        if (confirm("Batalkan undangan ini?")) {
            router.delete(route("settings.members.invitations.cancel", { invitation: inviteId }));
        }
    };

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
                                        Tambahkan rekan kerja ke workspace ini. Akun akan dibuatkan secara otomatis dengan peran akses yang dipilih.
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
                                            Anggota baru akan otomatis dihubungkan dengan ruang kerja organisasi Anda dengan kredensial akses awal.
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
                                        {processing ? "Memproses..." : "Kirim Undangan & Buat Akun"}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Active Members Card */}
                <div className="rounded-xl border border-border bg-card shadow-2xs overflow-hidden">
                    {/* Card Header & Toolbar */}
                    <div className="border-b border-border/60 px-5 py-3.5 bg-muted/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <h3 className="text-sm font-semibold text-foreground">
                                Daftar Anggota Aktif ({pagination.total})
                            </h3>
                            <Badge variant="outline" className="text-[11px] px-2 py-0.5">
                                Kapasitas: {pagination.total} / {maxUsers || 100}
                            </Badge>
                        </div>

                        {/* Search & Filter Toolbar */}
                        <div className="flex items-center gap-2">
                            <form onSubmit={handleSearchSubmit} className="relative flex items-center">
                                <SearchIcon className="absolute left-2.5 size-3.5 text-muted-foreground pointer-events-none" />
                                <Input
                                    type="text"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    placeholder="Cari nama atau email..."
                                    className="h-8 pl-8 pr-7 text-xs w-48 sm:w-60"
                                />
                                {searchTerm && (
                                    <button
                                        type="button"
                                        onClick={handleClearSearch}
                                        className="absolute right-2 text-muted-foreground hover:text-foreground"
                                    >
                                        <XIcon className="size-3.5" />
                                    </button>
                                )}
                            </form>

                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Select value={perPage} onValueChange={handlePerPageChange}>
                                    <SelectTrigger className="h-8 w-[95px] text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent align="end">
                                        <SelectItem value="10">10 / hal</SelectItem>
                                        <SelectItem value="20">20 / hal</SelectItem>
                                        <SelectItem value="50">50 / hal</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    {/* Members Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border/60 bg-muted/10 text-xs uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">Pengguna</th>
                                    <th className="px-4 py-3 font-semibold">Peran Akses</th>
                                    <th className="px-4 py-3 font-semibold">Tanggal Bergabung</th>
                                    <th className="px-4 py-3 font-semibold text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/40">
                                {memberList.map((member) => (
                                    <tr key={member.id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-5 py-3.5">
                                            <div className="flex flex-col">
                                                <div className="flex items-center gap-2 font-medium text-foreground">
                                                    <span>{member.name}</span>
                                                    {member.is_current_user && (
                                                        <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                                                            Anda
                                                        </Badge>
                                                    )}
                                                </div>
                                                <span className="text-xs text-muted-foreground">{member.email}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <Badge
                                                variant={member.role === "admin" ? "default" : "secondary"}
                                                className="text-xs capitalize font-medium"
                                            >
                                                {member.role_display || member.role}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                            {member.created_at ? new Date(member.created_at).toLocaleDateString("id-ID", {
                                                day: "numeric",
                                                month: "short",
                                                year: "numeric",
                                            }) : "-"}
                                        </td>
                                        <td className="px-4 py-3.5 text-right">
                                            {!member.is_current_user && (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon" className="size-8">
                                                            <MoreHorizontalIcon className="size-4" />
                                                            <span className="sr-only">Menu aksi</span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel className="text-xs">Ubah Peran</DropdownMenuLabel>
                                                        <DropdownMenuItem onClick={() => handleRoleChange(member.id, "admin")}>
                                                            Jadikan Admin
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => handleRoleChange(member.id, "bod")}>
                                                            Jadikan BOD (Viewer)
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() => handleRemoveMember(member.id, member.name)}
                                                        >
                                                            <Trash2Icon className="size-3.5 mr-1 text-destructive" />
                                                            <span>Keluarkan dari Tim</span>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {memberList.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-8 text-center text-sm text-muted-foreground">
                                            {searchTerm ? (
                                                <div className="flex flex-col items-center gap-1.5">
                                                    <p>Tidak ada anggota yang cocok dengan kata kunci "{searchTerm}".</p>
                                                    <Button variant="link" size="sm" onClick={handleClearSearch} className="text-xs">
                                                        Reset Pencarian
                                                    </Button>
                                                </div>
                                            ) : (
                                                <p>Belum ada anggota lain yang terdaftar dalam workspace ini.</p>
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Footer */}
                    <div className="border-t border-border/60 px-5 py-3 bg-muted/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-muted-foreground">
                        <div>
                            Menampilkan <span className="font-semibold text-foreground">{pagination.from ?? 0}</span>–
                            <span className="font-semibold text-foreground">{pagination.to ?? 0}</span> dari{" "}
                            <span className="font-semibold text-foreground">{pagination.total ?? 0}</span> anggota
                        </div>

                        {pagination.last_page > 1 && (
                            <div className="flex items-center gap-1.5 self-end sm:self-auto">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 px-2.5 text-xs gap-1"
                                    disabled={pagination.current_page <= 1}
                                    onClick={() => handlePageChange(pagination.prev_page_url)}
                                >
                                    <ChevronLeftIcon className="size-3.5" />
                                    <span>Sebelumnya</span>
                                </Button>

                                <span className="px-2 text-xs">
                                    Halaman <strong className="text-foreground">{pagination.current_page}</strong> dari{" "}
                                    <strong className="text-foreground">{pagination.last_page}</strong>
                                </span>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 px-2.5 text-xs gap-1"
                                    disabled={pagination.current_page >= pagination.last_page}
                                    onClick={() => handlePageChange(pagination.next_page_url)}
                                >
                                    <span>Berikutnya</span>
                                    <ChevronRightIcon className="size-3.5" />
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                {/* Pending Invitations Table (If any) */}
                {invitations.length > 0 && (
                    <div className="rounded-xl border border-border bg-card shadow-2xs overflow-hidden">
                        <div className="border-b border-border/60 px-5 py-3.5 bg-muted/20 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ClockIcon className="size-4 text-amber-500" />
                                <h3 className="text-sm font-semibold text-foreground">Undangan Menunggu Aktivasi ({invitations.length})</h3>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border/60 bg-muted/10 text-xs uppercase tracking-wider text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-semibold">Calon Anggota</th>
                                        <th className="px-4 py-3 font-semibold">Peran Akses</th>
                                        <th className="px-4 py-3 font-semibold">Diundang Oleh</th>
                                        <th className="px-4 py-3 font-semibold">Kedaluwarsa</th>
                                        <th className="px-4 py-3 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/40">
                                    {invitations.map((inv) => (
                                        <tr key={inv.id} className="hover:bg-muted/20 transition-colors">
                                            <td className="px-5 py-3.5">
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-foreground">{inv.name || inv.email}</span>
                                                    <span className="text-xs text-muted-foreground">{inv.email}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <Badge variant="outline" className="text-xs capitalize font-medium">
                                                    {inv.role}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {inv.invited_by}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {new Date(inv.expires_at).toLocaleDateString("id-ID", {
                                                    day: "numeric",
                                                    month: "short",
                                                    year: "numeric",
                                                })}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-xs text-destructive hover:bg-destructive/10"
                                                    onClick={() => handleCancelInvite(inv.id)}
                                                >
                                                    Batalkan
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}
