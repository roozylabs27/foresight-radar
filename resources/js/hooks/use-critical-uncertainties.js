import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for an exercise's critical uncertainties (roadmap R0.4).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useCriticalUncertainties(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, uncertaintyId) => route(`scenario-planning.uncertainties.${name}`, uncertaintyId ? [projectId, uncertaintyId] : projectId);

    return {
        busy,
        save: (values, uncertaintyId) => (uncertaintyId ? visit("put", url("update", uncertaintyId), values) : visit("post", url("store"), values)),
        remove: (uncertaintyId) => visit("delete", url("destroy", uncertaintyId)),
        approve: (uncertaintyId, axis, acknowledge) => visit("post", url("approve", uncertaintyId), { axis, acknowledge_dependency: acknowledge }),
        withdraw: (uncertaintyId) => visit("post", url("withdraw", uncertaintyId)),
    };
}
