import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";

import DetailList, { DetailItem } from "@/Components/DetailList";
import DimensionChip from "@/Components/DimensionChip";
import FormField, { fieldControlProps } from "@/Components/FormField";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { toFieldValue, useDialogForm } from "@/hooks/use-dialog-form";

const schema = z.object({
    time_horizon_id: z.string().min(1, "Please select the time horizon!"),
});

/**
 * Sets the time horizon of one driving force. Render it inside
 * <FormDialog formId={id}>. The driving force's own fields are shown read-only;
 * only `time_horizon_id` is submitted. `onSubmit(values)` may resolve to the
 * 422 `errors` bag, shown under the field.
 */
export default function FormTimeHorizon({ id, initialValues = {}, time_horizons = [], loading, onSubmit }) {
    const fieldId = useId();

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { time_horizon_id: toFieldValue(initialValues.time_horizon_id) },
        ids: { time_horizon_id: fieldId },
        loading,
        onSubmit,
    });

    return (
        <form id={id} noValidate onSubmit={submit}>
            <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                <DetailList>
                    <DetailItem label="Keyword">
                        <span className="font-medium">{initialValues.keyword || "-"}</span>
                    </DetailItem>
                    <DetailItem label="Description">{initialValues.description || "-"}</DetailItem>
                    <DetailItem label="Dimension">
                        <DimensionChip name={initialValues.dimension} />
                    </DetailItem>
                </DetailList>

                <Controller
                    name="time_horizon_id"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={fieldId} label="Time Horizon" error={fieldState.error} group>
                            <ToggleGroup
                                {...fieldControlProps(fieldId, fieldState)}
                                type="single"
                                variant="outline"
                                value={field.value}
                                // Radix lets a single group be emptied by clicking the checked item; keep the choice.
                                onValueChange={(value) => value && field.onChange(value)}
                                aria-labelledby={`${fieldId}-label`}
                                className="w-full"
                            >
                                {time_horizons.map((horizon) => (
                                    <ToggleGroupItem
                                        key={horizon.value}
                                        value={toFieldValue(horizon.value)}
                                        className="flex-1 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                                    >
                                        {horizon.label}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </FormField>
                    )}
                />
            </fieldset>
        </form>
    );
}
