import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 3 of an exercise (roadmap R1.1). `save` resolves to
 * Laravel's 422 errors or undefined; `confirm` agrees the scores (gate G3).
 */
export function useProjectAssessment(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) => {
        setBusy(true);
        return inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return {
        busy,
        save: (linkId, values) => visit("put", route("scenario-planning.assessment.update", [projectId, linkId]), values),
        confirm: () => visit("post", route("scenario-planning.assessment.confirm", projectId)),
    };
}
