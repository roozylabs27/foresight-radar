import { useState } from "react";
import { CheckIcon, ChevronDownIcon } from "lucide-react";

import { fieldControlProps } from "@/Components/FormField";
import { buttonVariants } from "@/Components/ui/button";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/Components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { toFieldValue } from "@/hooks/use-dialog-form";
import { cn } from "@/lib/utils";

/**
 * Combobox for a react-hook-form `field` (Popover + Command, filterable by
 * label). `options` are `{ value, label }`; the field holds the value as a
 * string. `fallbackLabel` names the current value when it is not among the
 * options (e.g. a PIC whose role is not listed).
 */
export default function SearchableSelect({
    id,
    field,
    fieldState,
    options = [],
    placeholder,
    searchPlaceholder = "Cari...",
    emptyText = "Tidak ditemukan.",
    fallbackLabel,
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => toFieldValue(option.value) === field.value);
    const label = selected?.label ?? (field.value ? fallbackLabel : null);

    const handleOpenChange = (next) => {
        setOpen(next);
        if (!next) field.onBlur();
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange} modal>
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
                <span className="truncate">{label ?? placeholder}</span>
                <ChevronDownIcon aria-hidden="true" className="size-4 opacity-50" />
            </PopoverTrigger>
            <PopoverContent align="start" className="w-(--radix-popover-trigger-width) min-w-64 p-0">
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const value = toFieldValue(option.value);
                                return (
                                    <CommandItem
                                        key={value}
                                        value={value}
                                        keywords={[option.label]}
                                        onSelect={() => {
                                            field.onChange(value);
                                            handleOpenChange(false);
                                        }}
                                    >
                                        <CheckIcon
                                            aria-hidden="true"
                                            className={cn("text-primary", value !== field.value && "opacity-0")}
                                        />
                                        {option.label}
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
