import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { LoaderCircleIcon, LockIcon, UserIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import AuthSplitLayout from "@/Components/auth/AuthSplitLayout";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaPost } from "@/lib/inertia-submit";

const SLIDES = [
    {
        title: "Navigasi ketidakpastian melalui radar strategis.",
        description:
            "Deteksi sinyal perubahan dan faktor pendorong industri sebelum berkembang menjadi disrupsi organisasi.",
    },
    {
        title: "Strukturkan prioritas lintas tiga horizon waktu.",
        description:
            "Petakan inisiatif ke dalam jangka pendek, menengah, dan panjang untuk mitigasi risiko serta kesiapan masa depan.",
    },
    {
        title: "Konsensus kepemimpinan berbasis data visual.",
        description:
            "Hubungkan perspektif ekonomi, teknologi, regulasi, dan pasar dalam satu radar terintegrasi untuk aksi presisi.",
    },
];

const schema = z.object({
    email: z.string().trim().min(1, "Harap masukkan email kerja Anda!").email("Format email tidak valid!"),
    password: z.string().min(1, "Harap masukkan kata sandi Anda!"),
    remember: z.boolean(),
});

export default function Login({ errors: serverErrors = {} }) {
    const [loading, setLoading] = useState(false);

    const { control, submit, setValue } = useDialogForm({
        schema,
        defaultValues: { email: "", password: "", remember: false },
        ids: { email: "email", password: "password" },
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaPost(route("login"), values, {
                onError: () => setValue("password", ""),
            }).finally(() => setLoading(false));
        },
    });

    return (
        <AuthSplitLayout slides={SLIDES}>
            <Head title="Masuk ke Foresight Radar" />

            <h1 className="m-0 text-[28px] leading-tight font-bold tracking-[-0.01em] text-foreground">
                Masuk ke Foresight Radar
            </h1>
            <p className="m-0 mt-2 mb-8 text-sm text-muted-foreground">
                Silakan masukkan email dan kata sandi Anda untuk mengakses sistem.
            </p>

            {serverErrors.email && (
                <AuthAlert tone="error" className="mb-6">
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
                                icon={UserIcon}
                                type="email"
                                placeholder="analis@perusahaan.com"
                                autoComplete="email"
                                autoFocus
                                {...rhfInputProps(props)}
                            />
                        )}
                    />
                    <Controller
                        name="password"
                        control={control}
                        render={(props) => (
                            <AuthInput
                                id="password"
                                label="Kata Sandi"
                                icon={LockIcon}
                                type="password"
                                placeholder="Masukkan kata sandi"
                                autoComplete="current-password"
                                {...rhfInputProps(props)}
                            />
                        )}
                    />

                    <div className="flex items-center justify-between gap-3 text-sm">
                        <Controller
                            name="remember"
                            control={control}
                            render={({ field }) => (
                                <label className="flex cursor-pointer items-center gap-2 text-foreground">
                                    <input
                                        type="checkbox"
                                        name={field.name}
                                        checked={field.value}
                                        onChange={(event) => field.onChange(event.target.checked)}
                                        className="size-4 accent-primary"
                                    />
                                    Ingat saya
                                </label>
                            )}
                        />
                        <Link
                            href={route("password.request")}
                            className="rounded-sm font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            Lupa kata sandi?
                        </Link>
                    </div>

                    <Button type="submit" className="h-11 w-full" aria-busy={loading || undefined}>
                        {loading && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        Masuk ke Platform
                    </Button>
                </fieldset>
            </form>
        </AuthSplitLayout>
    );
}
