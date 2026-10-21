import { Label } from "@/Components/ui/label";

/** ARIA wiring for the control inside a <FormField id={id}>. */
export function fieldControlProps(id, fieldState, { required = true } = {}) {
    return {
        id,
        "aria-required": required || undefined,
        "aria-invalid": fieldState.invalid || undefined,
        "aria-describedby": fieldState.error ? `${id}-error` : undefined,
    };
}

/**
 * Label, control and error message. The control takes `fieldControlProps(id, …)`.
 * For a radio group, pass `group`: the label becomes `${id}-label`, for the
 * group's `aria-labelledby`.
 */
export default function FormField({ id, label, error, required = true, group = false, className, children }) {
    const labelContent = (
        <>
            {label}
            {required && (
                <span aria-hidden="true" className="-ml-1 text-destructive">
                    *
                </span>
            )}
        </>
    );

    return (
        <div className={className ?? "flex flex-col gap-2"}>
            {group ? (
                <span id={`${id}-label`} className="flex items-center gap-2 text-sm leading-none font-medium">
                    {labelContent}
                </span>
            ) : (
                <Label htmlFor={id}>{labelContent}</Label>
            )}
            {children}
            {error && (
                <p id={`${id}-error`} className="m-0 text-sm text-destructive">
                    {error.message}
                </p>
            )}
        </div>
    );
}
