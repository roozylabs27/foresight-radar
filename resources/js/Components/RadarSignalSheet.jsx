import { LinkIcon, ZapIcon } from "lucide-react";

import DetailList, { DetailItem } from "@/Components/DetailList";
import DimensionChip from "@/Components/DimensionChip";
import PriorityBadge from "@/Components/PriorityBadge";
import { statusBadge } from "@/Components/StatusBadge";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/Components/ui/sheet";
import { useReturnFocus } from "@/hooks/use-return-focus";
import { horizonLabel } from "@/lib/radarLayout";
import { cn } from "@/lib/utils";

function ExternalLink({ href, children }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
        >
            {children}
            <LinkIcon aria-hidden="true" />
        </a>
    );
}

function SourceSignal({ signal }) {
    return (
        <article className="flex flex-col gap-2 rounded-lg border border-solid border-border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <h4 className="m-0 text-sm font-semibold text-foreground">{signal.title}</h4>
                <span className={cn(statusBadge(), "bg-accent text-primary")}>Source Origin</span>
            </div>
            {signal.summary && <p className="m-0 line-clamp-2 text-[13px] text-muted-foreground">{signal.summary}</p>}
            {signal.evidence_quote && (
                <blockquote className="m-0 border-0 border-l-[3px] border-solid border-primary bg-muted/60 px-3 py-1.5 text-xs text-foreground italic">
                    “{signal.evidence_quote}”
                </blockquote>
            )}
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <span>
                    Sumber: <b className="font-semibold text-foreground">{signal.source_title || "Unknown"}</b>
                </span>
                {signal.source_url && <ExternalLink href={signal.source_url}>Buka Artikel</ExternalLink>}
            </div>
        </article>
    );
}

/** Detail and provenance of one radar item (replaces the antd Drawer). */
export default function RadarSignalSheet({ item, open, onOpenChange, className }) {
    const returnFocus = useReturnFocus(open);
    const supporting = item?.supporting_signals ?? [];
    const action = item?.status_action
        ? `${item.status_action}${item.status_action_name ? ` (${item.status_action_name})` : ""}`
        : null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                onCloseAutoFocus={returnFocus}
                className={cn("w-full gap-0 sm:max-w-[560px]", className)}
            >
                <SheetHeader className="gap-2 border-b pr-12">
                    <SheetTitle className="text-base">
                        {item ? `#${item.no} ${item.keyword}` : "Signal Details"}
                    </SheetTitle>
                    <SheetDescription className="sr-only">Detail dan asal sinyal driving force.</SheetDescription>
                    {item && (
                        <div className="flex flex-wrap items-center gap-1.5">
                            <PriorityBadge priority={item.priority} />
                            <DimensionChip name={item.dimension} />
                            {action && <span className={statusBadge()}>Action: {action}</span>}
                            <span className={cn(statusBadge(), "bg-accent text-primary")}>
                                {item.time_horizon || horizonLabel(item.horizon)}
                            </span>
                        </div>
                    )}
                </SheetHeader>

                {item && (
                    <div className="flex flex-1 flex-col gap-6 overflow-y-auto p-4">
                        <section className="flex flex-col gap-1">
                            <h3 className="m-0 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Description
                            </h3>
                            <p className="m-0 text-[13px] leading-relaxed text-foreground">
                                {item.description || "No description provided."}
                            </p>
                        </section>

                        <section className="flex flex-col gap-2">
                            <h3 className="m-0 text-sm font-semibold text-foreground">Governance &amp; Urgency Evaluation</h3>
                            <DetailList className="grid-cols-2">
                                <DetailItem label="PIC">{item.pic || "-"}</DetailItem>
                                <DetailItem label="Approved At">{item.approved_at || "-"}</DetailItem>
                                <DetailItem label="Impact Score">
                                    <span className="tabular-nums">{item.impact_analysis ?? "-"} / 10</span>
                                </DetailItem>
                                <DetailItem label="Uncertainty Score">
                                    <span className="tabular-nums">{item.uncertainty_analysis ?? "-"} / 10</span>
                                </DetailItem>
                                <DetailItem label="Status of Action" className="col-span-2">
                                    {action ?? "Not assigned"}
                                </DetailItem>
                                <DetailItem label="Action Justification" className="col-span-2">
                                    {item.reason || "None recorded"}
                                </DetailItem>
                            </DetailList>
                        </section>

                        <section className="flex flex-col gap-3">
                            <h3 className="m-0 flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                <ZapIcon aria-hidden="true" className="text-primary" />
                                Signal Provenance ({item.signals_count || 0})
                            </h3>

                            {item.source_signal && <SourceSignal signal={item.source_signal} />}

                            {supporting.length > 0 && (
                                <div className="flex flex-col gap-2">
                                    <h4 className="m-0 text-xs font-semibold text-muted-foreground">Supporting Evidence</h4>
                                    <ul className="m-0 flex list-none flex-col divide-y rounded-lg border border-solid border-border p-0">
                                        {supporting.map((signal) => (
                                            <li key={signal.id} className="flex flex-col gap-1 px-3 py-2">
                                                <span className="text-xs font-semibold text-foreground">{signal.title}</span>
                                                {signal.summary && (
                                                    <span className="text-xs text-muted-foreground">{signal.summary}</span>
                                                )}
                                                {signal.source_url && (
                                                    <span className="text-xs">
                                                        <ExternalLink href={signal.source_url}>{signal.source_title || "Link"}</ExternalLink>
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {!item.source_signal && supporting.length === 0 && (
                                <p className="m-0 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                                    Driving force ini diinput langsung oleh analis tanpa tautan sinyal mentah.
                                </p>
                            )}
                        </section>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
