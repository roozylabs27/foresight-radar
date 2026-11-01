import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 9 early warning indicators (roadmap R1.9).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useEarlyWarningIndicators(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, indicatorId) => route(`scenario-planning.indicators.${name}`, indicatorId ? [projectId, indicatorId] : projectId);

    return {
        busy,
        save: (values, indicatorId) => (indicatorId ? visit("put", url("update", indicatorId), values) : visit("post", url("store"), values)),
        remove: (indicatorId) => visit("delete", url("destroy", indicatorId)),
    };
}
