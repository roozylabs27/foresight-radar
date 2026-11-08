import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for the participants of an exercise (roadmap R2.2).
 * Each resolves to Laravel's 422 errors, or undefined on success.
 */
export function useProjectParticipants(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const url = (name, participantId) => route(`scenario-planning.participants.${name}`, participantId ? [projectId, participantId] : projectId);

    return {
        busy,
        add: (values) => visit("post", url("store"), values),
        update: (participantId, values) => visit("put", url("update", participantId), values),
        remove: (participantId) => visit("delete", url("destroy", participantId)),
    };
}
