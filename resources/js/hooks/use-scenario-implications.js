import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 7 opportunities and challenges (roadmap R1.7).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useScenarioImplications(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, id) => route(`scenario-planning.implications.${name}`, [projectId, id]);

    return {
        busy,
        save: (values, { quadrantId, itemId }) => (itemId ? visit("put", url("update", itemId), values) : visit("post", url("store", quadrantId), values)),
        remove: (itemId) => visit("delete", url("destroy", itemId)),
        accept: (itemId) => visit("post", url("accept", itemId)),
        suggest: () => visit("post", route("scenario-planning.implications.suggest", projectId)),
    };
}
