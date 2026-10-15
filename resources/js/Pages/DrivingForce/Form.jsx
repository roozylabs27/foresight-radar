import { useId, useState } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { CheckOutlined } from "@ant-design/icons";
import { ChevronDownIcon } from "lucide-react";

import FormField, { fieldControlProps } from "@/Components/FormField";
import { buttonVariants } from "@/Components/ui/button";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/Components/ui/command";
import { Input } from "@/Components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { cn } from "@/lib/utils";
import { toFieldValue as toValue, useDialogForm } from "@/hooks/use-dialog-form";

const FIELDS = ["dimension_id", "keyword", "description", "pic_id"];

const required = (message) => z.string().trim().min(1, message);

const schema = z.object({
    dimension_id: required("Please select the dimension!"),
    keyword: required("Please input the keyword!"),
    description: required("Please input the description!"),
    pic_id: required("Please select the personal in charge!"),
});

function PicCombobox({ users, field, fieldState, id, initialPic }) {
    const [open, setOpen] = useState(false);
    const selected = users.find((user) => toValue(user.value) === field.value);
    // The current PIC can be missing from `users` (only some roles are listed); show their name anyway.
    const label = selected?.label ?? (field.value && field.value === initialPic.id ? initialPic.name : null);

    const handleOpenChange = (next) => {
        setOpen(next);
        if (!next) field.onBlur();
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange} modal>
            {/* Native trigger, not <Button asChild>: the ui Button has no forwardRef on React 18. */}
            <PopoverTrigger
                {...fieldControlProps(id, fieldState)}
                role="combobox"
                aria-expanded={open}
                className={cn(
                    buttonVariants({ variant: "outline" }),
                    "w-full justify-between border-input bg-transparent px-3 font-normal",
                    !label && "text-muted-foreground"
                )}
            >
                <span className="truncate">{label ?? "Select a personal in charge"}</span>
                <ChevronDownIcon aria-hidden="true" className="size-4 opacity-50" />
            </PopoverTrigger>
            <PopoverContent align="start" className="w-(--radix-popover-trigger-width) min-w-64 p-0">
                <Command>
                    <CommandInput placeholder="Cari personal in charge..." />
                    <CommandList>
                        <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                        <CommandGroup>
                            {users.map((user) => {
                                const value = toValue(user.value);
                                return (
                                    <CommandItem
                                        key={value}
                                        value={value}
                                        keywords={[user.label]}
                                        onSelect={() => {
                                            field.onChange(value);
                                            handleOpenChange(false);
                                        }}
                                    >
                                        <CheckOutlined
                                            aria-hidden="true"
                                            className={cn("text-primary", value !== field.value && "opacity-0")}
                                        />
                                        {user.label}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

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
                            <PicCombobox
                                users={users}
                                field={field}
                                fieldState={fieldState}
                                id={ids.pic_id}
                                initialPic={{ id: toValue(initialValues.pic_id), name: initialValues.pic }}
                            />
                        </FormField>
                    )}
                />
            </fieldset>
        </form>
    );
}
