import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Open/close and submit state for the scenario planning project dialog.
 * `submit(values)` posts (create) or puts (edit) through Inertia and resolves
 * to Laravel's 422 errors for useDialogForm, or undefined on success.
 */
export function useProjectDialog({ method, url }) {
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);

    const submit = (values) => {
        setSaving(true);
        return inertiaVisit(method, url, values, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setSaving(false),
        });
    };

    return { open, setOpen, saving, submit };
}
