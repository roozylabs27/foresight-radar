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
 */
export function registerFlashToasts(initialPage) {
    // Wait for the first render so the Toaster is mounted to receive it.
    setTimeout(() => show(initialPage?.props?.flash), 0);

    return router.on("success", (event) => show(event.detail.page.props.flash));
}
