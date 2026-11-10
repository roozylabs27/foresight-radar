import { useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/** Consistency sign-off of a quadrant (roadmap R2.8); resolves to the 422 errors or undefined. */
export function useConsistencyReview(projectId) {
    const [busy, setBusy] = useState(false);

    return {
        busy,
        save: (quadrantId, values) => {
            setBusy(true);
            return inertiaVisit("post", route("scenario-planning.consistency", [projectId, quadrantId]), values, { preserveScroll: true, onFinish: () => setBusy(false) });
        },
    };
}
