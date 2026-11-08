import { useCallback, useState } from "react";

import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * One comment thread on an exercise artefact (roadmap R2.2). `load` fetches the thread as JSON;
 * `add`, `resolve` and `remove` are Inertia visits (so the page's open-comment counts refresh)
 * followed by a reload of the thread. `add` resolves to Laravel's 422 errors or undefined.
 */
export function useComments(projectId, type, id) {
    const [thread, setThread] = useState({ comments: [], can_comment: false });
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const response = await axios.get(route("scenario-planning.comments.index", [projectId, { type, id }]));
            setThread(response.data);
        } finally {
            setLoading(false);
        }
    }, [projectId, type, id]);

    const visit = async (method, url, data = {}) => {
        setBusy(true);
        const errors = await inertiaVisit(method, url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
        if (!errors) await load();
        return errors;
    };

    return {
        thread,
        loading,
        busy,
        load,
        add: (body) => visit("post", route("scenario-planning.comments.store", projectId), { type, id, body }),
        resolve: (commentId) => visit("post", route("scenario-planning.comments.resolve", [projectId, commentId])),
        remove: (commentId) => visit("delete", route("scenario-planning.comments.destroy", [projectId, commentId])),
    };
}
