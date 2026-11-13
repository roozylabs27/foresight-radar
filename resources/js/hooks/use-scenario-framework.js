import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for an exercise's scenario framework (step 4, roadmap R0.5).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useScenarioFramework(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, quadrantId) => route(`scenario-planning.framework.${name}`, quadrantId ? [projectId, quadrantId] : projectId);

    return {
        busy,
        create: () => visit("post", url("store")),
        savePoles: (values) => visit("put", url("update"), values),
        saveQuadrant: (quadrantId, values) => visit("put", url("quadrant", quadrantId), values),
        approve: (rationale) => visit("post", url("approve"), { rationale }),
        reopen: (rationale) => visit("post", url("reopen"), { rationale }),
        suggest: () => visit("post", url("suggest")),
        acceptSuggestion: () => visit("post", url("suggestion.accept")),
        rejectSuggestion: () => visit("delete", url("suggestion.reject")),
    };
}
