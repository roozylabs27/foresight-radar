import { useState } from "react";
import { LoaderCircleIcon } from "lucide-react";

import DiscardChangesDialog from "@/Components/DiscardChangesDialog";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { useReturnFocus } from "@/hooks/use-return-focus";
import { cn } from "@/lib/utils";

/**
 * Form dialog: title, scrollable body and a Cancel / submit footer.
 *
 * - `onOk` runs from the submit button. Pass `formId` instead to make it a native
 *   submit button for a <form id={formId}> in `children` (react-hook-form).
 * - Width: `width` in px, or `className` (e.g. "sm:max-w-2xl").
 * - `dirty`: closing without saving (Cancel, Escape, outside click) asks first.
 */
export default function FormDialog({
    title,
    description,
    open,
    onCancel,
    onOpenChange,
    isEditMode,
    onOk,
    formId,
    btnText = isEditMode ? "Save Changes" : "Submit",
    btnVariant = "default",
    cancelText = "Cancel",
    loading = false,
    width,
    className,
    dirty = false,
    children,
}) {
    const returnFocus = useReturnFocus(open);
    const [asking, setAsking] = useState(false);

    const close = () => {
        onOpenChange?.(false);
        onCancel?.();
    };
    const handleOpenChange = (next) => {
        if (loading) return;
        if (next) return onOpenChange?.(true);
        if (dirty) return setAsking(true);
        close();
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DiscardChangesDialog open={asking} onOpenChange={setAsking} onDiscard={close} />
            <DialogContent
                showCloseButton={!loading}
                className={cn("max-h-[calc(100svh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto]", className)}
                style={width ? { maxWidth: `min(${width}px, calc(100% - 2rem))` } : undefined}
                onInteractOutside={(event) => loading && event.preventDefault()}
                onCloseAutoFocus={returnFocus}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : (
                        <DialogDescription className="sr-only">{title}</DialogDescription>
                    )}
                </DialogHeader>

                <div className="-mx-6 overflow-y-auto px-6">
                    {children}
                </div>

                <DialogFooter>
                    <Button variant="outline" disabled={loading} onClick={() => handleOpenChange(false)}>
                        {cancelText}
                    </Button>
                    <Button
                        type={formId ? "submit" : "button"}
                        variant={btnVariant}
                        form={formId}
                        disabled={loading}
                        aria-busy={loading || undefined}
                        onClick={formId ? undefined : onOk}
                    >
                        {loading && <LoaderCircleIcon aria-hidden="true" className="animate-spin" />}
                        {btnText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
