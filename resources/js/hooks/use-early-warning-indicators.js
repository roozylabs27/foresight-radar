import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 9 early warning indicators (roadmap R1.9) and their observations (R2.3).
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
        accept: (indicatorId) => visit("post", url("accept", indicatorId)),
        suggest: () => visit("post", url("suggest")),
        match: () => visit("post", url("match")),
        confirmObservation: (indicatorId, observationId) => visit("post", route("scenario-planning.indicators.observations.confirm", [projectId, indicatorId, observationId])),
        record: (indicatorId, values) => visit("post", route("scenario-planning.indicators.observations.store", [projectId, indicatorId]), values),
        removeObservation: (indicatorId, observationId) => visit("delete", route("scenario-planning.indicators.observations.destroy", [projectId, indicatorId, observationId])),
    };
}
