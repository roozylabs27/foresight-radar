import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { ArrowLeftIcon, LoaderCircleIcon, MailIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import GuestLayout, { GuestHeading } from "@/Layouts/GuestLayout";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaPost } from "@/lib/inertia-submit";

const schema = z.object({
    email: z.string().trim().min(1, "Harap masukkan email kerja Anda!").email("Format email tidak valid!"),
});

export default function ForgotPassword({ status, errors: serverErrors = {} }) {
    const [loading, setLoading] = useState(false);

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { email: "" },
        ids: { email: "email" },
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaPost(route("password.email"), values).finally(() => setLoading(false));
        },
    });

    return (
        <GuestLayout>
            <Head title="Lupa Kata Sandi" />
            <GuestHeading title="Lupa Kata Sandi?">
                Masukkan alamat email yang terdaftar. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.
            </GuestHeading>

            {status && (
                <AuthAlert tone="success" className="mb-5">
                    {status}
                </AuthAlert>
            )}
            {serverErrors.email && (
                <AuthAlert tone="error" className="mb-5">
                    {serverErrors.email}
                </AuthAlert>
            )}

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <Controller
                        name="email"
                        control={control}
                        render={(props) => (
                            <AuthInput
                                id="email"
                                label="Email Kerja"
                                icon={MailIcon}
                                type="email"
                                placeholder="analis@perusahaan.com"
                                autoComplete="email"
                                autoFocus
                                {...rhfInputProps(props)}
                            />
                        )}
                    />
                    <Button type="submit" className="h-11 w-full" aria-busy={loading || undefined}>
                        {loading && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        Kirim Tautan Reset Kata Sandi
                    </Button>
                    <Link
                        href={route("login")}
                        className="mx-auto inline-flex items-center gap-1.5 rounded-sm text-sm text-muted-foreground underline-offset-4 outline-none hover:text-foreground hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <ArrowLeftIcon aria-hidden="true" className="text-xs" />
                        Kembali ke Halaman Masuk
                    </Link>
                </fieldset>
            </form>
        </GuestLayout>
    );
}
