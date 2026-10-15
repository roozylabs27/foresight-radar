import { useState } from "react";
import { Head } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { LoaderCircleIcon, LockIcon, MailIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import GuestLayout, { GuestHeading } from "@/Layouts/GuestLayout";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaPost } from "@/lib/inertia-submit";

const FIELDS = ["email", "password", "password_confirmation"];

const schema = z
    .object({
        email: z.string().trim().min(1, "Harap masukkan email kerja Anda!").email("Format email tidak valid!"),
        password: z.string().min(1, "Harap masukkan kata sandi baru Anda!").min(8, "Kata sandi minimal 8 karakter!"),
        password_confirmation: z.string().min(1, "Harap konfirmasi kata sandi Anda!"),
    })
    .refine((values) => !values.password_confirmation || values.password === values.password_confirmation, {
        path: ["password_confirmation"],
        message: "Konfirmasi kata sandi tidak cocok!",
    });

export default function ResetPassword({ token, email, errors: serverErrors = {} }) {
    const [loading, setLoading] = useState(false);
    const firstServerError = Object.values(serverErrors)[0];

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { email: email ?? "", password: "", password_confirmation: "" },
        ids: Object.fromEntries(FIELDS.map((name) => [name, name])),
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaPost(route("password.store"), { ...values, token }).finally(() => setLoading(false));
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
        <GuestLayout>
            <Head title="Atur Ulang Kata Sandi" />
            <GuestHeading title="Atur Ulang Kata Sandi">
                Silakan masukkan kata sandi baru untuk mengakses akun Anda.
            </GuestHeading>

            {firstServerError && (
                <AuthAlert tone="error" className="mb-5">
                    {firstServerError}
                </AuthAlert>
            )}

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    {field("email", "Email Kerja", MailIcon, {
                        type: "email",
                        placeholder: "analis@perusahaan.com",
                        autoComplete: "username",
                    })}
                    {field("password", "Kata Sandi Baru", LockIcon, {
                        type: "password",
                        placeholder: "Minimal 8 karakter",
                        autoComplete: "new-password",
                        autoFocus: true,
                    })}
                    {field("password_confirmation", "Konfirmasi Kata Sandi", LockIcon, {
                        type: "password",
                        placeholder: "Ulangi kata sandi baru",
                        autoComplete: "new-password",
                    })}
                    <Button type="submit" className="mt-2 h-11 w-full" aria-busy={loading || undefined}>
                        {loading && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        Simpan Kata Sandi Baru
                    </Button>
                </fieldset>
            </form>
        </GuestLayout>
    );
}
