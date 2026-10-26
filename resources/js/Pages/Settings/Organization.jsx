import { Head, useForm } from "@inertiajs/react";
import {
    Building2Icon,
    LoaderCircleIcon,
    SaveIcon,
    ShieldCheckIcon,
} from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
import FormField from "@/Components/FormField";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Badge } from "@/Components/ui/badge";

export default function Organization({ auth, organization, stats, title = "Pengaturan Organisasi & Workspace" }) {
    // Mirrors the server-side manage-organization gate; the server stays the authority.
    const canManage = Boolean(auth?.permissions?.includes("manage-organization"));
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        name: organization.name || "",
        domain: organization.domain || "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route("settings.organization.update"));
    };

    return (
        <SettingsLayout auth={auth} title={title} activeTab="organization">
            <Head title={title} />

            <div className="flex flex-col gap-6">
                {/* Workspace Overview Banner */}
                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-5 shadow-2xs">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-10 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                                <Building2Icon className="size-5" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="text-base font-semibold text-foreground">{organization.name}</h2>
                                    <Badge variant="outline" className="text-xs uppercase font-mono tracking-wider">
                                        {organization.plan}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Workspace ID: <span className="font-mono">{organization.uuid}</span>
                                </p>
                            </div>
                        </div>

                        <Badge variant="secondary" className="gap-1.5 px-3 py-1 font-medium text-xs border border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                            <ShieldCheckIcon className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            <span>Ruang Kerja Terverifikasi</span>
                        </Badge>
                    </div>

                    <div className="grid grid-cols-2 gap-3 border-t border-border/60 pt-4 sm:grid-cols-4">
                        <div className="flex flex-col rounded-lg border border-border/50 bg-muted/20 p-3">
                            <span className="text-[11px] font-medium text-muted-foreground uppercase">Anggota Aktif</span>
                            <span className="text-lg font-bold text-foreground mt-0.5">
                                {stats.members_count} <span className="text-xs font-normal text-muted-foreground">/ {organization.max_users}</span>
                            </span>
                        </div>
                        <div className="flex flex-col rounded-lg border border-border/50 bg-muted/20 p-3">
                            <span className="text-[11px] font-medium text-muted-foreground uppercase">Sinyal Perubahan</span>
                            <span className="text-lg font-bold text-foreground mt-0.5">
                                {stats.signals_count}
                            </span>
                        </div>
                        <div className="flex flex-col rounded-lg border border-border/50 bg-muted/20 p-3">
                            <span className="text-[11px] font-medium text-muted-foreground uppercase">Driving Forces</span>
                            <span className="text-lg font-bold text-foreground mt-0.5">
                                {stats.driving_forces_count}
                            </span>
                        </div>
                        <div className="flex flex-col rounded-lg border border-border/50 bg-muted/20 p-3">
                            <span className="text-[11px] font-medium text-muted-foreground uppercase">Skenario 2x2</span>
                            <span className="text-lg font-bold text-foreground mt-0.5">
                                {stats.scenarios_count}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Organization Edit Form */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-2xs">
                    <div className="mb-5 border-b border-border/60 pb-3">
                        <h3 className="text-sm font-semibold text-foreground">Informasi Profil Organisasi</h3>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Perbarui identitas visual dan konfigurasi workspace strategis Anda.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="flex flex-col gap-5 max-w-xl" noValidate>
                        <fieldset disabled={!canManage || processing} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                            <FormField
                                id="org-name"
                                label="Nama Organisasi / Perusahaan"
                                required
                                error={errors.name ? { message: errors.name } : undefined}
                            >
                                <Input
                                    id="org-name"
                                    name="org-name"
                                    value={data.name}
                                    onChange={(e) => setData("name", e.target.value)}
                                    placeholder="Contoh: PT Strategic Indonesia"
                                    aria-required="true"
                                    aria-invalid={errors.name ? true : undefined}
                                    aria-describedby={errors.name ? "org-name-error" : undefined}
                                    className="h-10"
                                />
                            </FormField>

                            <FormField
                                id="org-domain"
                                label="Domain Email Resmi"
                                required={false}
                                error={errors.domain ? { message: errors.domain } : undefined}
                            >
                                <Input
                                    id="org-domain"
                                    name="org-domain"
                                    value={data.domain}
                                    onChange={(e) => setData("domain", e.target.value)}
                                    placeholder="contoh: strategic.co.id"
                                    aria-invalid={errors.domain ? true : undefined}
                                    aria-describedby={errors.domain ? "org-domain-error" : undefined}
                                    className="h-10 font-mono text-sm"
                                />
                                <p className="m-0 text-[11px] text-muted-foreground">
                                    Opsional. Hanya anggota dengan domain email ini yang dapat diundang ke workspace organisasi ini.
                                </p>
                            </FormField>

                            <FormField
                                id="org-slug"
                                label="URL Workspace Slug"
                                required={false}
                            >
                                <Input
                                    id="org-slug"
                                    name="org-slug"
                                    value={organization.slug}
                                    disabled
                                    className="h-10 font-mono text-sm bg-muted/40 cursor-not-allowed"
                                />
                                <p className="m-0 text-[11px] text-muted-foreground">
                                    Slug unik ini dibuat otomatis dari nama awal dan tidak dapat diubah setelah terdaftar.
                                </p>
                            </FormField>
                        </fieldset>

                        {!canManage && (
                            <p className="text-xs text-muted-foreground">
                                Hanya admin organisasi yang dapat mengubah profil ini.
                            </p>
                        )}

                        {canManage && (
                            <div className="flex items-center gap-3 pt-1">
                                <Button type="submit" disabled={processing} aria-busy={processing || undefined} className="h-10 gap-2 px-5">
                                    {processing ? <LoaderCircleIcon aria-hidden="true" className="size-4 animate-spin" /> : <SaveIcon aria-hidden="true" className="size-4" />}
                                    <span>{processing ? "Menyimpan..." : "Simpan Perubahan"}</span>
                                </Button>
                                {recentlySuccessful && (
                                    <span className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                        Tersimpan berhasil!
                                    </span>
                                )}
                            </div>
                        )}
                    </form>
                </div>
            </div>
        </SettingsLayout>
    );
}

