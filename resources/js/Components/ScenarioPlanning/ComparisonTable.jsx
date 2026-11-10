import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { CONDITION_KINDS, CONSISTENCY_STATUSES, INDICATOR_DIRECTIONS, RESPONSE_FITS, ROBUSTNESS } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

const REVIEW = { draft: { label: "Draf", tone: "pending" }, reviewed: { label: "Ditinjau", tone: "pending" }, approved: { label: "Disetujui", tone: "approved" } };

function List({ items, empty = "–" }) {
    if (items.length === 0) return <span className="text-muted-foreground">{empty}</span>;
    return (
        <ul className="m-0 flex list-disc flex-col gap-1 pl-4">
            {items.map((item, index) => <li key={index}>{item}</li>)}
        </ul>
    );
}

function Row({ label, hint, quadrants, render }) {
    return (
        <tr className="border-t border-border align-top">
            <th scope="row" className="sticky left-0 z-10 w-40 bg-card p-3 text-left text-xs font-semibold text-foreground">
                {label}
                {hint && <span className="mt-0.5 block font-normal text-muted-foreground">{hint}</span>}
            </th>
            {quadrants.map((quadrant) => (
                <td key={quadrant.position} className="min-w-52 p-3 text-xs text-foreground">{render(quadrant)}</td>
            ))}
        </tr>
    );
}

/** Consistency review of one quadrant: rule warnings, the team's verdict, and the review action. */
function Consistency({ quadrant, canReview, onReview }) {
    const { status, note, reviewed_by: reviewer, needs_review: needsReview, checks } = quadrant.consistency;
    const verdict = CONSISTENCY_STATUSES[status];

    return (
        <div className="flex flex-col gap-1.5">
            {verdict ? (
                <span className={statusBadge({ tone: verdict.tone })}>{verdict.label}</span>
            ) : (
                <span className={cn(statusBadge(), needsReview && "bg-warning-soft text-warning")}>{needsReview ? "Perlu tinjau ulang" : "Belum ditinjau"}</span>
            )}
            {verdict && reviewer && <span className="text-muted-foreground">oleh {reviewer}</span>}
            {note && <span className="text-muted-foreground">“{note}”</span>}
            {checks.length > 0 && (
                <ul className="m-0 flex list-disc flex-col gap-0.5 pl-4 text-warning">
                    {checks.map((row, index) => <li key={index}>{row.message}</li>)}
                </ul>
            )}
            {canReview && (
                <Button size="sm" variant="outline" className="self-start" onClick={() => onReview(quadrant)}>
                    Tinjau
                </Button>
            )}
        </div>
    );
}

/**
 * The four futures side by side (roadmap R2.8): one column per quadrant; rows for the summary,
 * accepted conditions, opportunities, challenges, response fit, indicator directions and the
 * consistency review.
 */
export default function ComparisonTable({ quadrants, responses, indicators, canReview = false, onReview }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full border-collapse bg-card">
                <caption className="sr-only">Perbandingan empat skenario</caption>
                <thead>
                    <tr>
                        <td className="sticky left-0 z-10 w-40 bg-card p-3" />
                        {quadrants.map((quadrant) => (
                            <th key={quadrant.position} scope="col" className="min-w-52 p-3 text-left align-top">
                                <span className="block text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{quadrant.poles.join(" × ")}</span>
                                <span className="block text-sm font-bold text-foreground">{quadrant.title ?? quadrant.position}</span>
                                <span className="mt-1 flex flex-wrap gap-1">
                                    {quadrant.review_status && <span className={statusBadge({ tone: REVIEW[quadrant.review_status]?.tone })}>Narasi {REVIEW[quadrant.review_status]?.label.toLowerCase()}</span>}
                                    {quadrant.stale && <span className={cn(statusBadge(), "bg-warning-soft text-warning")}>Perlu ditinjau</span>}
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <Row label="Ringkasan" quadrants={quadrants} render={(q) => q.summary || <span className="text-muted-foreground">–</span>} />
                    <Row
                        label="Kondisi"
                        hint="Hanya yang diterima"
                        quadrants={quadrants}
                        render={(q) => <List items={q.conditions.map((c) => <><span className="text-muted-foreground">{CONDITION_KINDS[c.kind]?.label ?? c.kind}: </span>{c.text}</>)} />}
                    />
                    <Row label="Peluang" quadrants={quadrants} render={(q) => <List items={q.opportunities} />} />
                    <Row label="Tantangan" quadrants={quadrants} render={(q) => <List items={q.challenges} />} />
                    <Row label="Konsistensi" hint="Cek aturan dan tinjauan tim" quadrants={quadrants} render={(q) => <Consistency quadrant={q} canReview={canReview} onReview={onReview} />} />
                    {responses.map((response) => (
                        <Row
                            key={response.id}
                            label={response.title}
                            hint={["Respons", response.robustness && ROBUSTNESS[response.robustness]?.label, response.status === "proposed" && "usulan"].filter(Boolean).join(" · ")}
                            quadrants={quadrants}
                            render={(q) => {
                                const fit = RESPONSE_FITS[response.fit[q.position]];
                                return fit ? <span className={statusBadge({ tone: fit.tone })}>{fit.short} {fit.label}</span> : <span className="text-muted-foreground">Belum dinilai</span>;
                            }}
                        />
                    ))}
                    {indicators.map((indicator) => (
                        <Row
                            key={indicator.id}
                            label={indicator.name}
                            hint={indicator.discriminates ? "Indikator · membedakan skenario" : "Indikator · belum membedakan"}
                            quadrants={quadrants}
                            render={(q) => {
                                const direction = INDICATOR_DIRECTIONS[indicator.directions[q.position]];
                                return direction ? <span>{direction.short} {direction.label}</span> : <span className="text-muted-foreground">–</span>;
                            }}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}
