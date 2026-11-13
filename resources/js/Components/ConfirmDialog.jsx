import { useId, useState } from "react";
import { LoaderCircleIcon } from "lucide-react";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/Components/ui/alert-dialog";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { useReturnFocus } from "@/hooks/use-return-focus";

/**
 * Replacement for antd Popconfirm. Pass the trigger as `children`, or control it
 * with `open`/`onOpenChange`. An async `onConfirm` keeps the dialog open with a
 * spinner until it settles; if it throws, the dialog stays open so the user can
 * retry (report the error from `onConfirm`, e.g. with a toast). With `withRationale` the dialog
 * asks for an optional reason (roadmap #13) and passes it to `onConfirm(rationale)`.
 */
export default function ConfirmDialog({
    children,
    title,
    description,
    confirmText = "Konfirmasi",
    cancelText = "Batal",
    variant = "destructive",
    onConfirm,
    open: openProp,
    onOpenChange,
    withRationale = false,
}) {
    const [openState, setOpenState] = useState(false);
    const [rationale, setRationale] = useState("");
    const rationaleId = useId();
    const [pending, setPending] = useState(false);
    const open = openProp ?? openState;
    const returnFocus = useReturnFocus(open);

    const setOpen = (next) => {
        if (pending) return;
        if (next) setRationale("");
        setOpenState(next);
        onOpenChange?.(next);
    };

    const handleConfirm = async (event) => {
        event.preventDefault();
        setPending(true);
        try {
            await onConfirm?.(withRationale && rationale.trim() ? rationale.trim() : null);
            setOpenState(false);
            onOpenChange?.(false);
        } catch {
            // Left open on purpose; onConfirm owns error reporting.
        } finally {
            setPending(false);
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            {children && <AlertDialogTrigger asChild>{children}</AlertDialogTrigger>}
            <AlertDialogContent onCloseAutoFocus={returnFocus}>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
                </AlertDialogHeader>
                {withRationale && (
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={rationaleId}>Alasan (opsional)</Label>
                        <Textarea id={rationaleId} value={rationale} maxLength={1000} disabled={pending} onChange={(event) => setRationale(event.target.value)} placeholder="Dicatat di riwayat keputusan." />
                    </div>
                )}
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={pending}>{cancelText}</AlertDialogCancel>
                    <AlertDialogAction
                        variant={variant}
                        disabled={pending}
                        aria-busy={pending || undefined}
                        onClick={handleConfirm}
                    >
                        {pending && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        {confirmText}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
