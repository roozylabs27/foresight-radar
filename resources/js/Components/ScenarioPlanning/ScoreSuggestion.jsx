import { BotIcon, CheckIcon, XIcon } from "lucide-react";

import ProvenanceBadge from "@/Components/ProvenanceBadge";
import { Button } from "@/Components/ui/button";

/** The prompt's signal labels ([S1]) mean nothing after the call; the cited titles are listed instead. */
const withoutRefs = (text) => text.replace(/\s*\[S\d+\]/g, "");

/**
 * A pending AI score suggestion for one force (roadmap R2.4): scores, rationale and the signals it
 * cites. The coordinator uses it as the consensus or discards it; nothing applies by itself.
 */
export default function ScoreSuggestion({ suggestion, canManage, busy, onAccept, onReject }) {
    return (
        <div className="flex flex-col gap-1.5 rounded-md border border-dashed border-primary/40 bg-primary/5 p-2.5 text-xs">
            <div className="flex flex-wrap items-center gap-2">
                <BotIcon aria-hidden="true" className="size-4 text-primary" />
                <span className="font-semibold text-foreground">
                    Saran AI: impact {suggestion.impact} · uncertainty {suggestion.uncertainty}
                </span>
                <ProvenanceBadge origin="ai" review="proposed" />
            </div>
            {suggestion.impact_rationale && <p className="m-0 text-muted-foreground">Impact: {withoutRefs(suggestion.impact_rationale)}</p>}
            {suggestion.uncertainty_rationale && <p className="m-0 text-muted-foreground">Uncertainty: {withoutRefs(suggestion.uncertainty_rationale)}</p>}
            <p className="m-0 text-muted-foreground">
                {suggestion.signals.length > 0 ? <>Sinyal: {suggestion.signals.map((signal) => signal.title).join("; ")}</> : "Tanpa sinyal pendukung; berdasarkan deskripsi saja."}
            </p>
            {canManage && (
                <div className="flex gap-2">
                    <Button size="sm" disabled={busy} onClick={onAccept}>
                        <CheckIcon aria-hidden="true" />
                        Pakai sebagai kesepakatan
                    </Button>
                    <Button size="sm" variant="ghost" disabled={busy} onClick={onReject}>
                        <XIcon aria-hidden="true" />
                        Buang
                    </Button>
                </div>
            )}
        </div>
    );
}
