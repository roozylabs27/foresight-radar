import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 5 conditions (roadmap R1.5).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useScenarioConditions(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, id) => route(`scenario-planning.conditions.${name}`, [projectId, id]);

    return {
        busy,
        save: (values, { quadrantId, conditionId }) => (conditionId ? visit("put", url("update", conditionId), values) : visit("post", url("store", quadrantId), values)),
        remove: (conditionId) => visit("delete", url("destroy", conditionId)),
        move: (conditionId, direction) => visit("post", url("move", conditionId), { direction }),
        accept: (conditionId) => visit("post", url("accept", conditionId)),
        suggest: () => visit("post", route("scenario-planning.conditions.suggest", projectId)),
    };
}
