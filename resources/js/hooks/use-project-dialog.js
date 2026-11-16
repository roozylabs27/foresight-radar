import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Open/close and submit state for the scenario planning project dialog.
 * `submit(values)` posts (create) or puts (edit) through Inertia and resolves
 * to Laravel's 422 errors for useDialogForm, or undefined on success.
 * `dirty` / `setDirty` carry the form's unsaved-input state, so FormDialog asks before
 * discarding it (found missing by the browser E2E, 2026-11-14).
 */
export function useProjectDialog({ method, url }) {
    const [open, setOpenState] = useState(false);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    const setOpen = (next) => {
        setOpenState(next);
        if (!next) setDirty(false);
    };

    const submit = (values) => {
        setSaving(true);
        return inertiaVisit(method, url, values, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setSaving(false),
        });
    };

    return { open, setOpen, saving, submit, dirty, setDirty };
}
