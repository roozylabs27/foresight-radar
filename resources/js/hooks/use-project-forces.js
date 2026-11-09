import { useState } from "react";
import { router } from "@inertiajs/react";

/**
 * Inertia actions for an exercise's driving force list (roadmap R0.2). Each
 * resolves once the visit finishes; the server answers with a flash message
 * and, on a validation error, the `errors` prop.
 */
export function useProjectForces(projectId) {
    const [busy, setBusy] = useState(false);

    const visit = (method, url, data = {}) =>
        new Promise((resolve) => {
            setBusy(true);
            router.visit(url, {
                method,
                data,
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setBusy(false);
                    resolve();
                },
            });
        });

    return {
        busy,
        add: (forceIds) => visit("post", route("scenario-planning.forces.store", projectId), { forces: forceIds }),
        update: (linkId, data) => visit("put", route("scenario-planning.forces.update", [projectId, linkId]), data),
        remove: (linkId) => visit("delete", route("scenario-planning.forces.destroy", [projectId, linkId])),
        confirm: () => visit("post", route("scenario-planning.forces.confirm", projectId)),
        suggest: () => visit("post", route("scenario-planning.forces.suggest", projectId)),
        acceptCandidate: (candidateId) => visit("post", route("scenario-planning.forces.candidates.accept", [projectId, candidateId])),
        rejectCandidate: (candidateId) => visit("delete", route("scenario-planning.forces.candidates.reject", [projectId, candidateId])),
    };
}
