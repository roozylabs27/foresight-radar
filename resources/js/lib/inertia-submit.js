import { router } from "@inertiajs/react";

// Inertia v1 remounts the page after a non-GET visit unless state is preserved,
// which would wipe the react-hook-form values and errors on a validation error.
const DEFAULTS = { preserveState: true };

/**
 * router.post as a promise for useDialogForm's onSubmit. It resolves once the
 * visit finishes; validation errors come back through the page's `errors`
 * prop, which the auth pages show in an alert.
 */
export function inertiaPost(url, data, options = {}) {
    return new Promise((resolve) => {
        router.post(url, data, { ...DEFAULTS, ...options, onFinish: () => resolve(undefined) });
    });
}

/**
 * Any Inertia visit (patch, put, delete...) as a promise that resolves to the
 * validation errors, or undefined on success, so useDialogForm can show them
 * under their fields.
 */
export function inertiaVisit(method, url, data, options = {}) {
    return new Promise((resolve) => {
        let errors;
        router.visit(url, {
            ...DEFAULTS,
            ...options,
            method,
            data,
            onError: (bag) => {
                errors = bag;
                options.onError?.(bag);
            },
            onFinish: () => resolve(errors),
        });
    });
}
