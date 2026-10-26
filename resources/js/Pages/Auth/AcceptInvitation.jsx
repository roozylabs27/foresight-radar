import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { LoaderCircleIcon, LockIcon, MailIcon, UserIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import GuestLayout, { GuestHeading } from "@/Layouts/GuestLayout";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaPost } from "@/lib/inertia-submit";

const FIELDS = ["name", "password", "password_confirmation"];

const schema = z
    .object({
        name: z.string().trim().min(1, "Harap masukkan nama lengkap Anda!"),
        password: z.string().min(1, "Harap buat kata sandi Anda!").min(8, "Kata sandi minimal 8 karakter!"),
        password_confirmation: z.string().min(1, "Harap konfirmasi kata sandi Anda!"),
    })
    .refine((values) => !values.password_confirmation || values.password === values.password_confirmation, {
        path: ["password_confirmation"],
        message: "Konfirmasi kata sandi tidak cocok!",
    });

// Every state other than "valid" ends the flow here, with a way out instead of a redirect.
const BLOCKED = {
    invalid: {
        title: "Tautan undangan tidak valid",
        body: "Tautan ini tidak dikenali atau sudah diganti dengan undangan yang lebih baru. Minta admin organisasi mengirim ulang undangan.",
    },
    expired: {
        title: "Undangan sudah kedaluwarsa",
        body: "Masa berlaku undangan ini sudah habis. Minta admin organisasi mengirim ulang undangan.",
    },
    accepted: {
        title: "Undangan sudah dipakai",
        body: "Undangan ini sudah diterima. Masuk dengan akun yang dibuat saat menerima undangan.",
    },
    unavailable: {
        title: "Workspace tidak tersedia",
        body: "Workspace organisasi untuk undangan ini sedang ditangguhkan atau tidak tersedia. Hubungi admin organisasi Anda.",
    },
    signed_in: {
        title: "Anda sedang masuk dengan akun lain",
        body: "Keluar terlebih dahulu, lalu buka kembali tautan undangan dari email Anda.",
    },
};

export default function AcceptInvitation({ state, invitation, submitUrl, errors: serverErrors = {} }) {
    const [loading, setLoading] = useState(false);
    const firstServerError = Object.values(serverErrors)[0];

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { name: invitation?.name ?? "", password: "", password_confirmation: "" },
        ids: Object.fromEntries(FIELDS.map((name) => [name, name])),
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaPost(submitUrl, values).finally(() => setLoading(false));
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

    if (state !== "valid") {
        const blocked = BLOCKED[state] ?? BLOCKED.invalid;

        return (
            <GuestLayout>
                <Head title="Undangan Tim" />
                <AuthAlert tone="warning" title={blocked.title} className="mb-5">
                    {blocked.body}
                </AuthAlert>
                {state === "signed_in" ? (
                    // The login page would just send a signed-in user away again.
                    <Button asChild variant="outline" className="h-11 w-full">
                        <Link href={route("logout")} method="post" as="button">
                            Keluar dari akun ini
                        </Link>
                    </Button>
                ) : (
                    <Button asChild variant="outline" className="h-11 w-full">
                        <Link href={route("login")}>Ke halaman masuk</Link>
                    </Button>
                )}
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head title="Terima Undangan" />
            <GuestHeading title={`Bergabung dengan ${invitation.organization}`}>
                Buat akun Anda untuk menerima undangan sebagai {invitation.role}.
            </GuestHeading>

            {firstServerError && (
                <AuthAlert tone="error" className="mb-5">
                    {firstServerError}
                </AuthAlert>
            )}

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <AuthInput id="email" label="Email Kerja" icon={MailIcon} type="email" value={invitation.email} readOnly />
                    {field("name", "Nama Lengkap", UserIcon, {
                        placeholder: "Nama Anda",
                        autoComplete: "name",
                        autoFocus: true,
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
                        Terima Undangan &amp; Buat Akun
                    </Button>
                </fieldset>
            </form>
        </GuestLayout>
    );
}
