import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    AlertCircleIcon,
    Building2Icon,
    CheckIcon,
    ClockIcon,
    MailIcon,
    MoreHorizontalIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserPlusIcon,
    UsersIcon,
} from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
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

export default function Members({ auth, members = [], invitations = [], availableRoles = [], maxUsers = 20, title = "Kelola Anggota & Undangan Tim" }) {
    const [inviteOpen, setInviteOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        name: "",
        role: "admin",
    });

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
                            Kelola pengguna yang memiliki akses ke workspace organisasi ini ({members.length} dari maks {maxUsers} anggota).
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
                                            Peran &amp; Hak Akses
                                        </Label>
                                        <select
                                            id="invite-role"
                                            name="role"
                                            value={data.role}
                                            onChange={(e) => setData("role", e.target.value)}
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option value="admin">Admin Organisasi (Akses Penuh Kelola Analisis &amp; Tim)</option>
                                            <option value="bod">Board of Directors (Eksekutif / Read-Only &amp; Approval)</option>
                                        </select>
                                        {errors.role && (
                                            <p className="text-xs text-destructive">{errors.role}</p>
                                        )}
                                    </div>

                                    <div className="rounded-lg border border-border/80 bg-muted/40 p-3 text-[11px] text-muted-foreground flex gap-2">
                                        <ShieldCheckIcon className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" />
                                        <span>
                                            <strong>Isolasi Data Aman:</strong> Setiap pengguna hanya terikat pada 1 organisasi. Kredensial dan data strategis tidak dapat diakses dari workspace lain.
                                        </span>
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

                {/* Active Members Table */}
                <div className="rounded-xl border border-border bg-card shadow-2xs overflow-hidden">
                    <div className="border-b border-border/60 px-5 py-3.5 bg-muted/20 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-foreground">Daftar Anggota Aktif ({members.length})</h3>
                        <span className="text-xs text-muted-foreground">Kapasitas: {members.length} / {maxUsers || 100} Anggota</span>
                    </div>

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
                                {members.map((member) => (
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
                            </tbody>
                        </table>
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
                                        <th className="px-5 py-3 font-semibold">Surel Tujuan</th>
                                        <th className="px-4 py-3 font-semibold">Peran</th>
                                        <th className="px-4 py-3 font-semibold">Diundang Oleh</th>
                                        <th className="px-4 py-3 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/40">
                                    {invitations.map((inv) => (
                                        <tr key={inv.id} className="hover:bg-muted/20 transition-colors">
                                            <td className="px-5 py-3 text-xs font-mono">{inv.email}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline" className="text-xs uppercase font-mono">
                                                    {inv.role}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">{inv.invited_by}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-8 text-xs text-destructive hover:bg-destructive/10"
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

