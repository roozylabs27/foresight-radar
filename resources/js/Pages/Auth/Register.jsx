import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { LoaderCircleIcon, LockIcon, MailIcon, UserIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import AuthSplitLayout from "@/Components/auth/AuthSplitLayout";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaPost } from "@/lib/inertia-submit";

const SLIDES = [
    {
        title: "Akses kanvas intelijen masa depan.",
        description:
            "Kolaborasi lintas divisi untuk mendeteksi sinyal lemah, tren disrupsi, dan faktor pendorong strategis industri.",
    },
    {
        title: "Pemetaan dampak dan horizon waktu.",
        description: "Analisis tingkat urgensi dan prioritas inisiatif organisasi secara visual dan terstruktur.",
    },
    {
        title: "Keputusan tepat waktu, risiko termitigasi.",
        description:
            "Dukung dewan direksi dan tim perencana dengan laporan radar berbasis data konsensus real-time.",
    },
];

const FIELDS = ["name", "email", "password", "password_confirmation"];

const schema = z
    .object({
        name: z.string().trim().min(1, "Harap masukkan nama lengkap Anda!"),
        email: z.string().trim().min(1, "Harap masukkan email kerja Anda!").email("Format email tidak valid!"),
        password: z.string().min(1, "Harap masukkan kata sandi!").min(8, "Kata sandi minimal 8 karakter!"),
        password_confirmation: z.string().min(1, "Harap konfirmasi kata sandi Anda!"),
    })
    .refine((values) => !values.password_confirmation || values.password === values.password_confirmation, {
        path: ["password_confirmation"],
        message: "Konfirmasi kata sandi tidak cocok!",
    });

export default function Register({ errors: serverErrors = {} }) {
    const [loading, setLoading] = useState(false);
    const firstServerError = Object.values(serverErrors)[0];

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: Object.fromEntries(FIELDS.map((name) => [name, ""])),
        ids: Object.fromEntries(FIELDS.map((name) => [name, name])),
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaPost(route("register"), values).finally(() => setLoading(false));
        },
    });

    const field = (name, label, icon, inputProps) => (
        <Controller
            name={name}
            control={control}
            render={(props) => (
                <AuthInput id={name} label={label} icon={icon} {...inputProps} {...rhfInputProps(props)} />
            )}
        />
    );

    return (
        <AuthSplitLayout slides={SLIDES}>
            <Head title="Daftar Akun" />

            <h1 className="m-0 text-[28px] leading-tight font-bold tracking-[-0.01em] text-foreground">Daftar Akun Baru</h1>
            <p className="m-0 mt-2 mb-6 text-sm text-muted-foreground">
                Lengkapi data diri Anda untuk membuat akun di Foresight Radar.
            </p>

            {firstServerError && (
                <AuthAlert tone="error" className="mb-5">
                    {firstServerError}
                </AuthAlert>
            )}

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-4 border-0 p-0">
                    {field("name", "Nama Lengkap", UserIcon, {
                        placeholder: "Nama lengkap",
                        autoComplete: "name",
                        autoFocus: true,
                    })}
                    {field("email", "Email Kerja", MailIcon, {
                        type: "email",
                        placeholder: "analis@perusahaan.com",
                        autoComplete: "email",
                    })}
                    {field("password", "Kata Sandi", LockIcon, {
                        type: "password",
                        placeholder: "Minimal 8 karakter",
                        autoComplete: "new-password",
                    })}
                    {field("password_confirmation", "Konfirmasi Kata Sandi", LockIcon, {
                        type: "password",
                        placeholder: "Ulangi kata sandi",
                        autoComplete: "new-password",
                    })}

                    <Button type="submit" className="mt-2 h-11 w-full" aria-busy={loading || undefined}>
                        {loading && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        Daftar Akun
                    </Button>

                    <p className="m-0 text-center text-sm text-muted-foreground">
                        Sudah memiliki akun?{" "}
                        <Link
                            href={route("login")}
                            className="rounded-sm font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            Masuk di sini
                        </Link>
                    </p>
                </fieldset>
            </form>
        </AuthSplitLayout>
    );
}
