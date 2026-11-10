import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for the preferred future and its backcasting milestones (roadmap R4.1).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useBackcasting(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const milestoneUrl = (name, milestoneId) => route(`scenario-planning.backcasting.milestones.${name}`, milestoneId ? [projectId, milestoneId] : projectId);

    return {
        busy,
        prefer: (values) => visit("post", route("scenario-planning.backcasting.preferred", projectId), values),
        clear: () => visit("delete", route("scenario-planning.backcasting.preferred.clear", projectId)),
        save: (values, milestoneId) => (milestoneId ? visit("put", milestoneUrl("update", milestoneId), values) : visit("post", milestoneUrl("store"), values)),
        remove: (milestoneId) => visit("delete", milestoneUrl("destroy", milestoneId)),
    };
}
