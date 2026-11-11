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

/** Mirrors App\Http\Requests\DrivingForceRequest: keyword ≤ 4 words, description ≤ 120 words (roadmap R1.2). */
const DESCRIPTION_WORDS = 120;
const countWords = (text) => String(text ?? "").trim().split(/\s+/).filter(Boolean).length;

const schema = z.object({
    dimension_id: required("Please select the dimension!"),
    keyword: required("Please input the keyword!").refine((value) => countWords(value) <= 4, "The keyword may have at most 4 words."),
    description: required("Please input the description!").refine((value) => countWords(value) <= DESCRIPTION_WORDS, `The description may have at most ${DESCRIPTION_WORDS} words.`),
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
                                    {/* Archived dimensions take no new forces; an edit may keep the force's own. */}
                                    {dimensions.filter((dimension) => !dimension.archived || toValue(dimension.value) === toValue(initialValues.dimension_id)).map((dimension) => (
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
                                placeholder="What is changing, why, and how it affects the organisation"
                                rows={5}
                                className="max-h-64 min-h-24"
                            />
                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                {countWords(field.value)} / {DESCRIPTION_WORDS} words
                            </span>
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
