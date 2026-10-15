import { useState } from "react";
import { Head } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { LoaderCircleIcon, LockIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import GuestLayout, { GuestHeading } from "@/Layouts/GuestLayout";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaPost } from "@/lib/inertia-submit";

const schema = z.object({
    password: z.string().min(1, "Harap masukkan kata sandi Anda!"),
});

export default function ConfirmPassword({ errors: serverErrors = {} }) {
    const [loading, setLoading] = useState(false);

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { password: "" },
        ids: { password: "password" },
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaPost(route("password.confirm"), values).finally(() => setLoading(false));
        },
    });

    return (
        <GuestLayout>
            <Head title="Konfirmasi Kata Sandi" />
            <GuestHeading title="Konfirmasi Kata Sandi">
                Ini adalah area sensitif aplikasi. Harap masukkan kata sandi akun Anda sebelum melanjutkan.
            </GuestHeading>

            {serverErrors.password && (
                <AuthAlert tone="error" className="mb-5">
                    {serverErrors.password}
                </AuthAlert>
            )}

            <form noValidate onSubmit={submit}>
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <Controller
                        name="password"
                        control={control}
                        render={(props) => (
                            <AuthInput
                                id="password"
                                label="Kata Sandi"
                                icon={LockIcon}
                                type="password"
                                placeholder="Masukkan kata sandi akun"
                                autoComplete="current-password"
                                autoFocus
                                {...rhfInputProps(props)}
                            />
                        )}
                    />
                    <Button type="submit" className="h-11 w-full" aria-busy={loading || undefined}>
                        {loading && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        Konfirmasi
                    </Button>
                </fieldset>
            </form>
        </GuestLayout>
    );
}
