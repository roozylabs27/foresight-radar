import { useState } from "react";
import { LoadingOutlined } from "@ant-design/icons";

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

/**
 * Replacement for antd Popconfirm. Pass the trigger as `children`, or control it
 * with `open`/`onOpenChange`. An async `onConfirm` keeps the dialog open with a
 * spinner until it settles; if it throws, the dialog stays open so the user can
 * retry (report the error from `onConfirm`, e.g. with a toast).
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
}) {
    const [openState, setOpenState] = useState(false);
    const [pending, setPending] = useState(false);
    const open = openProp ?? openState;

    const setOpen = (next) => {
        if (pending) return;
        setOpenState(next);
        onOpenChange?.(next);
    };

    const handleConfirm = async (event) => {
        event.preventDefault();
        setPending(true);
        try {
            await onConfirm?.();
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
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={pending}>{cancelText}</AlertDialogCancel>
                    <AlertDialogAction
                        variant={variant}
                        disabled={pending}
                        aria-busy={pending || undefined}
                        onClick={handleConfirm}
                    >
                        {pending && <LoadingOutlined aria-hidden="true" />}
                        {confirmText}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
