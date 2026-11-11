import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for Settings → Dimensi (plan part B2). Each resolves to Laravel's 422 errors,
 * or undefined on success.
 */
export function useDimensionSettings() {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return {
        busy,
        saveDimension: (values, id) => (id ? visit("put", route("settings.dimensions.update", id), values) : visit("post", route("settings.dimensions.store"), values)),
        moveDimension: (id, direction) => visit("post", route("settings.dimensions.move", id), { direction }),
        archiveDimension: (id, archived) => visit(archived ? "post" : "delete", route(archived ? "settings.dimensions.archive" : "settings.dimensions.unarchive", id)),
        deleteDimension: (id) => visit("delete", route("settings.dimensions.destroy", id)),
        saveEnvironment: (values, id) => (id ? visit("put", route("settings.environments.update", id), values) : visit("post", route("settings.environments.store"), values)),
        moveEnvironment: (id, direction) => visit("post", route("settings.environments.move", id), { direction }),
        deleteEnvironment: (id) => visit("delete", route("settings.environments.destroy", id)),
    };
}
