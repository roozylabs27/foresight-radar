import { router } from "@inertiajs/react";
import { toast } from "sonner";

function show(flash) {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}

/**
 * Shows the server's back()->with('success'|'error') messages as toasts:
 * once for the first page, then once per completed visit. Listening to
 * visits instead of watching props means a repeated identical message still
 * shows, and history back/forward (no request) never replays an old one.
 * A visit that fails without a server answer shows a connection error.
 */
export function registerFlashToasts(initialPage) {
    // Wait for the first render so the Toaster is mounted to receive it.
    setTimeout(() => show(initialPage?.props?.flash), 0);

    // Production readiness 2026-11-14 (found by the browser E2E): a visit that fails without a server
    // answer (network down, connection reset) used to fail silently. Tell the user it did not go through.
    router.on("exception", () => {
        toast.error("Koneksi ke server gagal. Perubahan belum tersimpan; periksa jaringan lalu coba lagi.");
    });

    return router.on("success", (event) => show(event.detail.page.props.flash));
}
