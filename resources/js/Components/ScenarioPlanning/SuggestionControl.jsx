import { useEffect } from "react";
import { router } from "@inertiajs/react";
import { AlertCircleIcon, BotIcon, LoaderCircleIcon } from "lucide-react";

import { Button } from "@/Components/ui/button";

const PENDING = ["queued", "processing"];

/**
 * Asks AI for score suggestions on step 3 (roadmap R2.4) and shows the job state. While the job
 * runs the page reloads its forces every few seconds, so suggestions appear without a refresh.
 */
export default function SuggestionControl({ status, error, canManage, disabled, busy, onSuggest }) {
    const pending = PENDING.includes(status);

    useEffect(() => {
        if (!pending) return undefined;
        const timer = setInterval(() => router.reload({ only: ["forces", "suggestionStatus", "suggestionError"] }), 5000);
        return () => clearInterval(timer);
    }, [pending]);

    return (
        <div className="flex flex-col gap-2">
            {canManage && (
                <Button size="sm" variant="outline" className="self-start" disabled={disabled || pending || busy} onClick={onSuggest}>
                    {pending ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <BotIcon aria-hidden="true" />}
                    {pending ? "AI sedang menyiapkan saran…" : "Minta saran skor AI"}
                </Button>
            )}
            {!canManage && pending && <p className="m-0 text-xs text-muted-foreground">AI sedang menyiapkan saran skor…</p>}
            {status === "failed" && error && (
                <p role="alert" className="m-0 flex items-start gap-1.5 rounded-md bg-danger-soft p-2 text-xs text-danger">
                    <AlertCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    {error}
                </p>
            )}
        </div>
    );
}
