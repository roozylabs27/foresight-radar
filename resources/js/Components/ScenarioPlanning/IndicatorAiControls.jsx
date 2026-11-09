import SuggestionControl from "@/Components/ScenarioPlanning/SuggestionControl";

/**
 * Step 9 AI assists (roadmap R2.6): propose indicators, and match new accepted signals to the
 * existing indicators as observations awaiting confirmation.
 */
export default function IndicatorAiControls({ indicators, canManage, busy, validationError, indicatorStatus, indicatorError, matchStatus, matchError, onSuggest, onMatch }) {
    return (
        <div className="flex flex-col gap-2">
            <SuggestionControl
                status={indicatorStatus}
                error={indicatorError}
                validationError={validationError}
                canManage={canManage}
                busy={busy}
                onSuggest={onSuggest}
                label="Minta usulan indikator AI"
                pendingLabel="AI sedang menyusun usulan indikator…"
                reloadOnly={["indicators", "indicatorStatus", "indicatorError"]}
            />
            {indicators.length > 0 && (
                <SuggestionControl
                    status={matchStatus}
                    error={matchError}
                    canManage={canManage}
                    busy={busy}
                    onSuggest={onMatch}
                    label="Cocokkan sinyal baru dengan AI"
                    pendingLabel="AI sedang mencocokkan sinyal dengan indikator…"
                    reloadOnly={["indicators", "relevance", "matchStatus", "matchError"]}
                />
            )}
        </div>
    );
}
