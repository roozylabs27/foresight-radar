import { useState } from "react";
import { AlertCircleIcon, CheckIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import { Button } from "@/Components/ui/button";
import { inertiaVisit } from "@/lib/inertia-submit";

/**
 * Upstream changes that may have outdated the framework and its scenarios (roadmap R1.10).
 * "Keep" records who reviewed them and clears the flags; nothing is changed automatically.
 */
export default function StaleBanner({ project, canManage }) {
    const [busy, setBusy] = useState(false);
    const stale = project.stale;
    if (!stale) return null;

    const keep = () => {
        setBusy(true);
        return inertiaVisit("post", route("scenario-planning.stale.acknowledge", project.id), {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return (
        <div role="status" className="flex flex-col gap-2 rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning">
            <p className="m-0 flex items-center gap-2 font-semibold">
                <AlertCircleIcon aria-hidden="true" className="size-4" />
                Ada perubahan di langkah sebelumnya. Tinjau kerangka dan skenario yang terdampak.
            </p>
            <ul className="m-0 flex list-disc flex-col gap-0.5 pl-6 text-xs">
                {stale.reasons.map((reason) => (
                    <li key={reason}>{reason}</li>
                ))}
            </ul>
            {stale.quadrants.length > 0 && (
                <p className="m-0 text-xs">Kuadran terdampak: {stale.quadrants.map((quadrant) => quadrant.title).join(", ")}</p>
            )}
            {canManage && (
                <ConfirmDialog
                    title="Pertahankan apa adanya?"
                    description="Tandai bahwa tim sudah meninjau perubahan ini dan tetap memakai kerangka serta skenario yang ada."
                    confirmText="Pertahankan"
                    onConfirm={keep}
                >
                    <Button size="sm" variant="outline" className="self-start" disabled={busy}>
                        <CheckIcon aria-hidden="true" />
                        Sudah ditinjau, pertahankan
                    </Button>
                </ConfirmDialog>
            )}
        </div>
    );
}
