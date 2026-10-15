import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";

import FormField, { fieldControlProps } from "@/Components/FormField";
import { Textarea } from "@/Components/ui/textarea";
import { useDialogForm } from "@/hooks/use-dialog-form";

const MAX_REASON = 1000;

const schema = z.object({
    reason: z
        .string()
        .trim()
        .min(1, "Harap masukkan alasan pengaktifan kembali.")
        .min(5, "Alasan minimal 5 karakter.")
        .max(MAX_REASON, `Alasan maksimal ${MAX_REASON} karakter.`),
});

/**
 * Reason for reopening a closed driving force. Render it inside
 * <FormDialog formId={id}>. `onSubmit(values)` may resolve to the 422 `errors` bag.
 */
export default function FormReopen({ id, loading, onSubmit }) {
    const reasonId = useId();
    const counterId = `${reasonId}-count`;

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { reason: "" },
        ids: { reason: reasonId },
        loading,
        onSubmit,
    });

    return (
        <form id={id} noValidate onSubmit={submit}>
            <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-4 border-0 p-0">
                <p className="m-0 text-sm text-muted-foreground">
                    Mengaktifkan kembali driving force akan mengembalikan statusnya ke{" "}
                    <strong className="font-semibold text-foreground">PENDING</strong> pada tahap evaluasi dan mencatat
                    justifikasi perubahan pada riwayat audit.
                </p>

                <Controller
                    name="reason"
                    control={control}
                    render={({ field, fieldState }) => {
                        const props = fieldControlProps(reasonId, fieldState);
                        return (
                            <FormField
                                id={reasonId}
                                label="Alasan Pengaktifan Kembali (Justifikasi)"
                                error={fieldState.error}
                            >
                                <Textarea
                                    {...props}
                                    aria-describedby={[props["aria-describedby"], counterId].filter(Boolean).join(" ")}
                                    name={field.name}
                                    value={field.value}
                                    onChange={field.onChange}
                                    onBlur={field.onBlur}
                                    rows={4}
                                    maxLength={MAX_REASON}
                                    placeholder="Contoh: Perubahan lanskap regulasi Q4 memicu kebutuhan analisis ulang..."
                                    className="max-h-48 min-h-24"
                                />
                                <span id={counterId} className="self-end text-xs text-muted-foreground tabular-nums">
                                    {field.value.length} / {MAX_REASON}
                                </span>
                            </FormField>
                        );
                    }}
                />
            </fieldset>
        </form>
    );
}
