import { useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { LoaderCircleIcon, MailIcon, SaveIcon, UserIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaVisit } from "@/lib/inertia-submit";
import SectionHeading from "./SectionHeading";

const schema = z.object({
    name: z.string().trim().min(1, "Nama lengkap wajib diisi."),
    email: z.string().trim().min(1, "Alamat email wajib diisi.").email("Format alamat email tidak valid."),
});

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const [loading, setLoading] = useState(false);

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { name: user?.name || "", email: user?.email || "" },
        ids: { name: "profile-name", email: "profile-email" },
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaVisit("patch", route("profile.update"), values, {
                preserveScroll: true,
                onSuccess: () => toast.success("Informasi profil berhasil disimpan."),
            }).finally(() => setLoading(false));
        },
    });

    return (
        <section aria-labelledby="profile-information-heading">
            <SectionHeading
                id="profile-information-heading"
                title="Informasi Profil"
                description="Perbarui nama lengkap dan alamat email akun organisasi Anda."
            />

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <Controller
                        name="name"
                        control={control}
                        render={(props) => (
                            <AuthInput
                                id="profile-name"
                                label="Nama Lengkap"
                                icon={UserIcon}
                                placeholder="Masukkan nama lengkap"
                                autoComplete="name"
                                {...rhfInputProps(props)}
                            />
                        )}
                    />
                    <Controller
                        name="email"
                        control={control}
                        render={(props) => (
                            <AuthInput
                                id="profile-email"
                                label="Alamat Email"
                                icon={MailIcon}
                                type="email"
                                placeholder="nama@organisasi.id"
                                autoComplete="email"
                                {...rhfInputProps(props)}
                            />
                        )}
                    />

                    {mustVerifyEmail && user?.email_verified_at === null && (
                        <AuthAlert tone="warning" title="Verifikasi Email Diperlukan">
                            <div className="flex flex-col items-start gap-2">
                                <p className="m-0 text-[13px]">Alamat email Anda belum diverifikasi.</p>
                                <Link
                                    href={route("verification.send")}
                                    method="post"
                                    as="button"
                                    className="cursor-pointer rounded-sm border-0 bg-transparent p-0 text-left text-[13px] font-medium text-primary underline underline-offset-4 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    Klik di sini untuk mengirim ulang email verifikasi.
                                </Link>
                                {status === "verification-link-sent" && (
                                    <p className="m-0 text-[13px] font-medium text-success">
                                        Tautan verifikasi baru telah dikirim ke alamat email Anda.
                                    </p>
                                )}
                            </div>
                        </AuthAlert>
                    )}

                    <div>
                        <Button type="submit" className="h-10" aria-busy={loading || undefined}>
                            {loading ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <SaveIcon aria-hidden="true" />}
                            Simpan Perubahan
                        </Button>
                    </div>
                </fieldset>
            </form>
        </section>
    );
}
