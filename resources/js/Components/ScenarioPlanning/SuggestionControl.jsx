import { router } from "@inertiajs/react";
import { AlertCircleIcon, BotIcon, LoaderCircleIcon } from "lucide-react";

import { Button } from "@/Components/ui/button";
import { useAiStatusStream } from "@/hooks/use-ai-status-stream";

const PENDING = ["queued", "processing"];

/**
 * Starts an AI assist job (roadmap R2.4: score suggestions on step 3, candidate forces on step 2)
 * and shows its state. While the job runs the page follows it through Server-Sent Events and reloads
 * `reloadOnly` when the job's status changes, so the proposals appear without a refresh (polling every
 * few seconds only when the stream is unavailable).
 */
export default function SuggestionControl({ status, error, validationError, canManage, disabled, busy, onSuggest, label = "Minta saran skor AI", pendingLabel = "AI sedang menyiapkan saran…", reloadOnly = ["forces", "suggestionStatus", "suggestionError"] }) {
    const pending = PENDING.includes(status);

    useAiStatusStream({ active: pending, onChange: () => router.reload({ only: reloadOnly }) });

    return (
        <div className="flex flex-col gap-2">
            {canManage && (
                <Button size="sm" variant="outline" className="self-start" disabled={disabled || pending || busy} onClick={onSuggest}>
                    {pending ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <BotIcon aria-hidden="true" />}
                    {pending ? pendingLabel : label}
                </Button>
            )}
            {!canManage && pending && <p className="m-0 text-xs text-muted-foreground">{pendingLabel}</p>}
            {/* PRD-14: a busy provider can take up to the AI timeout (4 minutes) to answer or fail. */}
            {pending && <p className="m-0 text-xs text-muted-foreground">Bisa sampai 4 menit. Halaman ini boleh ditinggal; hasilnya tetap tersimpan.</p>}
            {(validationError || (status === "failed" && error)) && (
                <p role="alert" className="m-0 flex items-start gap-1.5 rounded-md bg-danger-soft p-2 text-xs text-danger">
                    <AlertCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    {validationError || error}
                </p>
            )}
        </div>
    );
}
