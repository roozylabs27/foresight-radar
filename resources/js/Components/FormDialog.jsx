import { LoaderCircleIcon } from "lucide-react";

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
    children,
}) {
    const returnFocus = useReturnFocus(open);

    const handleOpenChange = (next) => {
        if (loading) return;
        onOpenChange?.(next);
        if (!next) onCancel?.();
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
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
