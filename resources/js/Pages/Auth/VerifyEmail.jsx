import { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { LoaderCircleIcon, LogOutIcon, SendIcon } from "lucide-react";

import AuthAlert from "@/Components/auth/AuthAlert";
import GuestLayout, { GuestHeading } from "@/Layouts/GuestLayout";
import { Button } from "@/Components/ui/button";

export default function VerifyEmail({ status }) {
    const [loading, setLoading] = useState(false);

    const resend = (event) => {
        event.preventDefault();
        setLoading(true);
        router.post(route("verification.send"), {}, { onFinish: () => setLoading(false) });
    };

    return (
        <GuestLayout>
            <Head title="Verifikasi Email" />
            <GuestHeading title="Verifikasi Alamat Email">
                Terima kasih telah bergabung di Foresight Radar. Silakan verifikasi email Anda dengan mengklik tautan yang
                telah kami kirimkan.
            </GuestHeading>

            {status === "verification-link-sent" && (
                <AuthAlert tone="success" className="mb-5">
                    Tautan verifikasi baru telah berhasil dikirim ke alamat email Anda.
                </AuthAlert>
            )}

            <form onSubmit={resend} className="flex flex-col gap-3">
                <Button type="submit" className="h-11 w-full" disabled={loading} aria-busy={loading || undefined}>
                    {loading ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <SendIcon aria-hidden="true" />}
                    Kirim Ulang Email Verifikasi
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full border-input"
                    onClick={() => router.post(route("logout"))}
                >
                    <LogOutIcon aria-hidden="true" />
                    Keluar (Logout)
                </Button>
            </form>
        </GuestLayout>
    );
}
