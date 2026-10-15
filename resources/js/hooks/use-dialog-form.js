import { useEffect, useRef } from "react";
import { useForm } from "react-hook-form";

import { zodResolver } from "@/lib/zod-resolver";

/** Form values are strings (Select, ToggleGroup); the API sends numeric ids or null. */
export const toFieldValue = (value) => (value === null || value === undefined ? "" : String(value));

// The ui primitives can't take refs on React 18, so controls are focused by id
// instead of RHF's shouldFocusError. A radio group focuses its checked option.
function focusControl(id) {
    const element = document.getElementById(id);
    if (element?.getAttribute("role") === "radiogroup") {
        (element.querySelector('[aria-checked="true"]') ?? element.querySelector('[role="radio"]'))?.focus();
    } else {
        element?.focus();
    }
}

/**
 * react-hook-form setup for a form inside <FormDialog>: zod validation once a
 * field is touched, focus on the first invalid field, and Laravel 422 errors
 * shown under their fields.
 *
 * `ids` maps field names to control ids, in display order. `onSubmit(values)`
 * may resolve to the 422 `errors` bag. Spread `submit` on the <form onSubmit>.
 */
export function useDialogForm({ schema, defaultValues, ids, loading, onSubmit }) {
    const form = useForm({ resolver: zodResolver(schema), mode: "onTouched", defaultValues });
    const fields = Object.keys(ids);

    const focusFirstError = (errors) => {
        const name = fields.find((field) => errors[field]);
        if (name) focusControl(ids[name]);
    };

    // Server errors arrive while the fieldset is still disabled; focus once it re-enables.
    const pendingFocus = useRef(null);
    useEffect(() => {
        if (loading || !pendingFocus.current) return;
        focusFirstError(pendingFocus.current);
        pendingFocus.current = null;
    });

    const submit = form.handleSubmit(async (values) => {
        const serverErrors = await onSubmit(values);
        if (!serverErrors) return;

        const messages = {};
        for (const [name, value] of Object.entries(serverErrors)) {
            if (!fields.includes(name)) continue;
            messages[name] = { type: "server", message: [].concat(value)[0] };
            form.setError(name, messages[name]);
        }
        pendingFocus.current = messages;
    }, focusFirstError);

    return { ...form, submit };
}
