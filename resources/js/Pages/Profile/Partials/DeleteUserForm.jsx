import { useState } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { LockIcon, Trash2Icon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import AuthInput, { rhfInputProps } from "@/Components/auth/AuthInput";
import FormDialog from "@/Components/FormDialog";
import { Button } from "@/Components/ui/button";
import { useDialogForm } from "@/hooks/use-dialog-form";
import { inertiaVisit } from "@/lib/inertia-submit";
import SectionHeading from "./SectionHeading";

const FORM_ID = "delete-account-form";

const schema = z.object({
    password: z.string().min(1, "Kata sandi wajib diisi untuk konfirmasi."),
});

export default function DeleteUserForm() {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    const form = useDialogForm({
        schema,
        defaultValues: { password: "" },
        ids: { password: "delete-account-password" },
        loading,
        onSubmit: (values) => {
            setLoading(true);
            return inertiaVisit("delete", route("profile.destroy"), values, {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: () => form.setValue("password", ""),
            }).finally(() => setLoading(false));
        },
    });

    const close = () => {
        setOpen(false);
        form.reset();
    };

    return (
        <section aria-labelledby="delete-account-heading">
            <SectionHeading
                id="delete-account-heading"
                title="Hapus Akun Pengguna"
                className="text-danger"
                description="Setelah akun Anda dihapus, semua sumber daya dan data terkait akan dihapus secara permanen. Harap unduh data atau informasi yang ingin Anda simpan sebelum melanjutkan."
            />

            <Button variant="destructive" className="h-10" onClick={() => setOpen(true)}>
                <Trash2Icon aria-hidden="true" />
                Hapus Akun Ini
            </Button>

            <FormDialog
                title="Konfirmasi Penghapusan Akun"
                description="Masukkan kata sandi Anda saat ini untuk mengonfirmasi bahwa Anda ingin menghapus akun ini."
                open={open}
                onCancel={close}
                formId={FORM_ID}
                btnText="Hapus Akun Secara Permanen"
                btnVariant="destructive"
                cancelText="Batal"
                loading={loading}
                width={520}
            >
                <form id={FORM_ID} noValidate onSubmit={form.submit}>
                    <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-4 border-0 p-0 pb-1">
                        <AuthAlert tone="error" title="Peringatan Tindakan Tidak Dapat Dibatalkan">
                            Semua data dan hak akses Anda di Foresight Radar akan dihapus secara permanen.
                        </AuthAlert>
                        <Controller
                            name="password"
                            control={form.control}
                            render={(props) => (
                                <AuthInput
                                    id="delete-account-password"
                                    label="Kata Sandi Akun"
                                    icon={LockIcon}
                                    type="password"
                                    placeholder="Masukkan kata sandi Anda"
                                    autoComplete="current-password"
                                    autoFocus
                                    {...rhfInputProps(props)}
                                />
                            )}
                        />
                    </fieldset>
                </form>
            </FormDialog>
        </section>
    );
}
