import { useState } from "react";
import { EyeIcon, EyeOffIcon } from "lucide-react";

import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { cn } from "@/lib/utils";

/**
 * Labelled input with a leading icon, used by the auth and profile forms.
 * `error` is the message to show (client or server). A `type="password"`
 * input gets a show/hide button.
 */
export default function AuthInput({ id, label, icon: Icon, error, required = true, type = "text", className, ...inputProps }) {
    const [visible, setVisible] = useState(false);
    const isPassword = type === "password";
    const ToggleIcon = visible ? EyeIcon : EyeOffIcon;

    return (
        <FormField id={id} label={label} error={error ? { message: error } : undefined} required={required}>
            <div className="relative">
                {Icon && (
                    <Icon
                        aria-hidden="true"
                        className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                    />
                )}
                <Input
                    id={id}
                    type={isPassword && visible ? "text" : type}
                    aria-required={required || undefined}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${id}-error` : undefined}
                    className={cn("h-11", Icon && "pl-9", isPassword && "pr-11", className)}
                    {...inputProps}
                />
                {isPassword && (
                    <button
                        type="button"
                        onClick={() => setVisible((value) => !value)}
                        aria-label={visible ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"}
                        aria-pressed={visible}
                        className="absolute top-1/2 right-1.5 inline-flex size-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-sm border-0 bg-transparent p-0 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <ToggleIcon aria-hidden="true" />
                    </button>
                )}
            </div>
        </FormField>
    );
}

/** AuthInput wired to a react-hook-form Controller render prop. */
export function rhfInputProps({ field, fieldState }) {
    return {
        name: field.name,
        value: field.value,
        onChange: field.onChange,
        onBlur: field.onBlur,
        error: fieldState.error?.message,
    };
}
