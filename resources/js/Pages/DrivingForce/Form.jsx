import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";

import FormField, { fieldControlProps } from "@/Components/FormField";
import SearchableSelect from "@/Components/SearchableSelect";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { toFieldValue as toValue, useDialogForm } from "@/hooks/use-dialog-form";

const FIELDS = ["dimension_id", "keyword", "description", "pic_id"];

const required = (message) => z.string().trim().min(1, message);

const schema = z.object({
    dimension_id: required("Please select the dimension!"),
    keyword: required("Please input the keyword!"),
    description: required("Please input the description!"),
    pic_id: required("Please select the personal in charge!"),
});

/**
 * Create/edit form for a driving force. Render it inside <FormDialog formId={id}>,
 * which owns the submit button. `onSubmit(values)` may resolve to the 422
 * `errors` bag from Laravel; those messages are shown under their fields.
 */
export default function FormDrivingForce({ id, initialValues = {}, dimensions = [], users = [], loading, onSubmit }) {
    const idPrefix = useId();
    const ids = Object.fromEntries(FIELDS.map((name) => [name, `${idPrefix}-${name}`]));

    const { control, submit } = useDialogForm({
        schema,
        defaultValues: Object.fromEntries(FIELDS.map((name) => [name, toValue(initialValues[name])])),
        ids,
        loading,
        onSubmit,
    });

    return (
        <form id={id} noValidate onSubmit={submit}>
            <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                <Controller
                    name="dimension_id"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.dimension_id} label="Dimension" error={fieldState.error}>
                            <Select
                                name={field.name}
                                value={field.value}
                                onValueChange={field.onChange}
                                onOpenChange={(open) => !open && field.onBlur()}
                            >
                                <SelectTrigger {...fieldControlProps(ids.dimension_id, fieldState)} className="w-full">
                                    <SelectValue placeholder="Select a dimension" />
                                </SelectTrigger>
                                <SelectContent position="popper">
                                    {dimensions.map((dimension) => (
                                        <SelectItem key={dimension.value} value={toValue(dimension.value)}>
                                            {dimension.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                    )}
                />

                <Controller
                    name="keyword"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.keyword} label="Keyword" error={fieldState.error}>
                            <Input
                                {...fieldControlProps(ids.keyword, fieldState)}
                                name={field.name}
                                value={field.value}
                                onChange={field.onChange}
                                onBlur={field.onBlur}
                                placeholder="Enter keyword"
                                autoComplete="off"
                            />
                        </FormField>
                    )}
                />

                <Controller
                    name="description"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.description} label="Description" error={fieldState.error}>
                            <Textarea
                                {...fieldControlProps(ids.description, fieldState)}
                                name={field.name}
                                value={field.value}
                                onChange={field.onChange}
                                onBlur={field.onBlur}
                                placeholder="Enter a description"
                                rows={3}
                                className="max-h-40 min-h-20"
                            />
                        </FormField>
                    )}
                />

                <Controller
                    name="pic_id"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.pic_id} label="Personal In Charge" error={fieldState.error}>
                            <SearchableSelect
                                id={ids.pic_id}
                                field={field}
                                fieldState={fieldState}
                                options={users}
                                placeholder="Select a personal in charge"
                                searchPlaceholder="Cari personal in charge..."
                                // The current PIC can be missing from `users` (only some roles are listed).
                                fallbackLabel={field.value === toValue(initialValues.pic_id) ? initialValues.pic : undefined}
                            />
                        </FormField>
                    )}
                />
            </fieldset>
        </form>
    );
}
