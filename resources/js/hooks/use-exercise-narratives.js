import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";

import { watchAiStatus } from "@/lib/ai-status-stream";
import { isPending, pollUntil } from "@/lib/llm";

const PROPS = ["framework", "revisions", "steps", "project"];

function errorText(error) {
    const data = error.response?.data;
    if (data?.errors && typeof data.errors === "object") return Object.values(data.errors).flat().join(" ");
    return data?.message ?? error.message;
}

/**
 * Step 6 actions inside the exercise (audit U3): generate, edit and review the four narratives.
 * The endpoints answer JSON; after each change the page props are reloaded. While a generation is
 * queued or running it is followed through Server-Sent Events (polling when the stream is unavailable).
 */
export function useExerciseNarratives(projectId, framework) {
    const [busy, setBusy] = useState(false);
    const url = (name, id) => route(`scenario-planning.narratives.${name}`, id ? [projectId, id] : projectId);
    const refresh = () => router.reload({ only: PROPS });
    const pending = isPending(framework?.generation?.status);

    const call = async (request, failure) => {
        setBusy(true);
        try {
            const response = await request();
            if (response.status !== 202) toast.success(response.data.message);
            refresh();
            return true;
        } catch (error) {
            toast.error(failure, { description: errorText(error) });
            return false;
        } finally {
            setBusy(false);
        }
    };

    useEffect(() => {
        if (!pending) return undefined;
        const controller = new AbortController();
        const load = () => axios.get(route("scenario-planning.narratives.state", projectId), { signal: controller.signal }).then((response) => response.data);
        const finish = (data) => {
            if (controller.signal.aborted) return;
            const result = data?.generation;
            const duration = 12000;
            if (!data) toast.info("Narasi masih dalam antrean.", { description: "Buka lagi halaman ini nanti.", duration });
            else if (result?.status === "failed") toast.error("Narasi tidak dibuat.", { description: result.error, duration });
            else if (result?.notice) toast.warning(result.message, { description: result.notice, duration });
            else toast.success(result?.message ?? "Narasi dibuat.", { duration });
            router.reload({ only: PROPS });
        };
        const failed = (error) => {
            if (!controller.signal.aborted) toast.error("Status narasi tidak dapat dimuat.", { description: errorText(error) });
        };

        // Server-Sent Events say when the generation is no longer pending; then the result is loaded once.
        // Without a usable stream the state is polled as before.
        let settled = false;
        const close = watchAiStatus(
            projectId,
            (statuses) => {
                if (settled || isPending(statuses.narrative)) return;
                settled = true;
                load().then(finish).catch(failed);
            },
            () => {
                if (settled) return;
                pollUntil(load, (data) => !isPending(data.generation?.status), { signal: controller.signal }).then(finish).catch(failed);
            },
        );
        return () => {
            close();
            controller.abort();
        };
    }, [projectId, pending]);

    return {
        busy: busy || pending,
        pending,
        generate: (overwrite = false) => call(() => axios.post(url("generate"), { overwrite_edited: overwrite }), "Narasi tidak dibuat."),
        save: (quadrant) => (values) => call(() => axios.put(url("quadrant", quadrant.id), values), "Kuadran tidak tersimpan."),
        review: (quadrant) => (status) => call(() => axios.post(url("review", quadrant.id), { status }), "Status tinjauan tidak tersimpan."),
    };
}
