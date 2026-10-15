import { useId, useState } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";
import { EyeInvisibleOutlined, EyeOutlined } from "@ant-design/icons";

import FormField, { fieldControlProps } from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { toFieldValue, useDialogForm } from "@/hooks/use-dialog-form";

const baseSchema = {
    name: z.string().trim().min(1, "Please input the name!"),
    email: z.string().trim().min(1, "Please input the email!").email("Email is not valid!"),
    role_id: z.string().min(1, "Please select the role!"),
};

const createSchema = z.object({
    ...baseSchema,
    password: z.string().min(1, "Please input the password!").min(6, "Password minimal 6 characters !"),
});

const editSchema = z.object(baseSchema);

function PasswordInput({ field, fieldState, id }) {
    const [visible, setVisible] = useState(false);
    const Icon = visible ? EyeOutlined : EyeInvisibleOutlined;

    return (
        <div className="relative">
            <Input
                {...fieldControlProps(id, fieldState)}
                type={visible ? "text" : "password"}
                name={field.name}
                value={field.value}
                onChange={field.onChange}
                onBlur={field.onBlur}
                placeholder="Enter password"
                autoComplete="new-password"
                className="pr-10"
            />
            <button
                type="button"
                onClick={() => setVisible((value) => !value)}
                aria-label={visible ? "Sembunyikan password" : "Tampilkan password"}
                aria-pressed={visible}
                className="absolute top-1/2 right-1 inline-flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <Icon aria-hidden="true" />
            </button>
        </div>
    );
}

/**
 * Create/edit form for a user. Render it inside <FormDialog formId={id}>.
 * The password is only asked when creating. `onSubmit(values)` may resolve to
 * the 422 `errors` bag.
 */
export default function FormUser({ id, isEditMode, initialValues = {}, roles = [], loading, onSubmit }) {
    const idPrefix = useId();
    const fields = isEditMode ? ["name", "email", "role_id"] : ["name", "email", "role_id", "password"];
    const ids = Object.fromEntries(fields.map((name) => [name, `${idPrefix}-${name}`]));

    const { control, submit } = useDialogForm({
        schema: isEditMode ? editSchema : createSchema,
        defaultValues: {
            name: initialValues.name ?? "",
            email: initialValues.email ?? "",
            role_id: toFieldValue(initialValues.role_id),
            ...(!isEditMode && { password: "" }),
        },
        ids,
        loading,
        onSubmit,
    });

    return (
        <form id={id} noValidate onSubmit={submit}>
            <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                <Controller
                    name="name"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.name} label="Name" error={fieldState.error}>
                            <Input
                                {...fieldControlProps(ids.name, fieldState)}
                                name={field.name}
                                value={field.value}
                                onChange={field.onChange}
                                onBlur={field.onBlur}
                                placeholder="Enter name"
                                autoComplete="off"
                                autoFocus={!isEditMode}
                            />
                        </FormField>
                    )}
                />

                <Controller
                    name="email"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.email} label="Email" error={fieldState.error}>
                            <Input
                                {...fieldControlProps(ids.email, fieldState)}
                                type="email"
                                name={field.name}
                                value={field.value}
                                onChange={field.onChange}
                                onBlur={field.onBlur}
                                placeholder="Enter an email"
                                autoComplete="off"
                            />
                        </FormField>
                    )}
                />

                <Controller
                    name="role_id"
                    control={control}
                    render={({ field, fieldState }) => (
                        <FormField id={ids.role_id} label="Role" error={fieldState.error}>
                            <Select
                                name={field.name}
                                value={field.value}
                                onValueChange={field.onChange}
                                onOpenChange={(open) => !open && field.onBlur()}
                            >
                                <SelectTrigger {...fieldControlProps(ids.role_id, fieldState)} className="w-full">
                                    <SelectValue placeholder="Select a roles" />
                                </SelectTrigger>
                                <SelectContent position="popper">
                                    {roles.map((role) => (
                                        <SelectItem key={role.value} value={toFieldValue(role.value)}>
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                    )}
                />

                {!isEditMode && (
                    <Controller
                        name="password"
                        control={control}
                        render={({ field, fieldState }) => (
                            <FormField id={ids.password} label="Password" error={fieldState.error}>
                                <PasswordInput field={field} fieldState={fieldState} id={ids.password} />
                            </FormField>
                        )}
                    />
                )}
            </fieldset>
        </form>
    );
}
