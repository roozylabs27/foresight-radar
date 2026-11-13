import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Inertia actions for step 3 of an exercise (roadmap R1.1). `save` resolves to
 * Laravel's 422 errors or undefined; `saveMine` / `withdrawMine` handle the signed-in
 * participant's own score (R2.1); `keepLibrary` keeps a copied library score (PI-02); `confirm` agrees the scores (gate G3).
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
        saveMine: (linkId, values) => visit("put", route("scenario-planning.assessment.mine", [projectId, linkId]), values),
        withdrawMine: (linkId) => visit("delete", route("scenario-planning.assessment.withdraw", [projectId, linkId])),
        suggest: () => visit("post", route("scenario-planning.assessment.suggest", projectId)),
        acceptSuggestion: (linkId) => visit("post", route("scenario-planning.assessment.suggestion.accept", [projectId, linkId])),
        rejectSuggestion: (linkId) => visit("delete", route("scenario-planning.assessment.suggestion.reject", [projectId, linkId])),
        keepLibrary: (linkId) => visit("post", route("scenario-planning.assessment.library", [projectId, linkId])),
        confirm: (rationale) => visit("post", route("scenario-planning.assessment.confirm", projectId), { rationale }),
    };
}
