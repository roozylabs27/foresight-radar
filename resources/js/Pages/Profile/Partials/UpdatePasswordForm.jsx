import { useState } from "react";
import { Controller } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { LoaderCircleIcon, LockIcon, SaveIcon } from "lucide-react";

import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaVisit } from "@/lib/inertia-submit";
import SectionHeading from "./SectionHeading";

const EMPTY = { current_password: "", password: "", password_confirmation: "" };

const schema = z
    .object({
        current_password: z.string().min(1, "Kata sandi saat ini wajib diisi."),
        password: z.string().min(1, "Kata sandi baru wajib diisi.").min(8, "Kata sandi minimal 8 karakter."),
        password_confirmation: z.string().min(1, "Konfirmasi kata sandi wajib diisi."),
    })
    .refine((values) => !values.password_confirmation || values.password === values.password_confirmation, {
        path: ["password_confirmation"],
        message: "Konfirmasi kata sandi tidak cocok!",
    });

const FIELDS = [
    ["current_password", "Kata Sandi Saat Ini", "Masukkan kata sandi saat ini", "current-password"],
    ["password", "Kata Sandi Baru", "Minimal 8 karakter", "new-password"],
    ["password_confirmation", "Konfirmasi Kata Sandi Baru", "Ulangi kata sandi baru", "new-password"],
];

export default function UpdatePasswordForm() {
    const [loading, setLoading] = useState(false);

    const form = useDialogForm({
        schema,
        defaultValues: EMPTY,
        ids: Object.fromEntries(FIELDS.map(([name]) => [name, `profile-${name}`])),
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaVisit("put", route("password.update"), values, {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset(EMPTY);
                    toast.success("Kata sandi berhasil diperbarui.");
                },
                onError: (errors) => {
                    if (errors.password) {
                        form.setValue("password", "");
                        form.setValue("password_confirmation", "");
                    }
                    if (errors.current_password) form.setValue("current_password", "");
                },
            }).finally(() => setLoading(false));
        },
    });

    return (
        <section aria-labelledby="update-password-heading">
            <SectionHeading
                id="update-password-heading"
                title="Perbarui Kata Sandi"
                description="Pastikan akun Anda menggunakan kata sandi yang kuat dan aman."
            />

            <form noValidate onSubmit={form.submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    {FIELDS.map(([name, label, placeholder, autoComplete]) => (
                        <Controller
                            key={name}
                            name={name}
                            control={form.control}
                            render={(props) => (
                                <AuthInput
                                    id={`profile-${name}`}
                                    label={label}
                                    icon={LockIcon}
                                    type="password"
                                    placeholder={placeholder}
                                    autoComplete={autoComplete}
                                    {...rhfInputProps(props)}
                                />
                            )}
                        />
                    ))}
                    <div>
                        <Button type="submit" className="h-10" aria-busy={loading || undefined}>
                            {loading ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <SaveIcon aria-hidden="true" />}
                            Simpan Kata Sandi
                        </Button>
                    </div>
                </fieldset>
            </form>
        </section>
    );
}
