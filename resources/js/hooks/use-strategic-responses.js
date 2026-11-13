import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 8 strategic responses (roadmap R1.8).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useStrategicResponses(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, responseId) => route(`scenario-planning.responses.${name}`, responseId ? [projectId, responseId] : projectId);

    return {
        busy,
        save: (values, responseId) => (responseId ? visit("put", url("update", responseId), values) : visit("post", url("store"), values)),
        remove: (responseId) => visit("delete", url("destroy", responseId)),
        approve: (responseId, rationale) => visit("post", url("approve", responseId), { rationale }),
        reject: (responseId) => visit("post", url("reject", responseId)),
        keep: (responseId) => visit("post", url("keep", responseId)),
        suggest: () => visit("post", url("suggest")),
    };
}
