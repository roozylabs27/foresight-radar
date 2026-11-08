import { usePage } from "@inertiajs/react";
import { PlusIcon, XIcon } from "lucide-react";

import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { INDICATOR_DIRECTIONS } from "@/domain/scenarioPlanning";

/**
 * Latest observations of one indicator (roadmap R2.3). Anyone who may give input records; the
 * recorder or the coordinator removes an observation.
 */
export default function IndicatorObservations({ indicator, canRecord, canManage, busy, onRecord, onRemove }) {
    const { auth } = usePage().props;
    const value = (row) => [row.value_numeric != null && `${row.value_numeric}${indicator.unit ? ` ${indicator.unit}` : ""}`, row.value_text].filter(Boolean).join(" · ");

    return (
        <section aria-label={`Observasi ${indicator.name}`} className="flex flex-col gap-2 border-t border-border pt-3">
            <div className="flex items-center justify-between gap-2">
                <h4 className="m-0 text-xs font-semibold text-foreground">Observasi ({indicator.observation_count})</h4>
                {canRecord && indicator.status === "active" && (
                    <Button size="sm" variant="outline" onClick={onRecord}>
                        <PlusIcon aria-hidden="true" />
                        Catat observasi
                    </Button>
                )}
            </div>
            {indicator.observations.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground">Belum ada observasi.</p>
            ) : (
                <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
                    {indicator.observations.map((row) => {
                        const direction = INDICATOR_DIRECTIONS[row.direction];
                        const removable = canManage || row.recorded_by_id === auth?.user?.id;
                        return (
                            <li key={row.id} className="flex items-start gap-2 text-xs">
                                <span className="w-20 shrink-0 text-muted-foreground">{row.observed_at}</span>
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="flex flex-wrap items-center gap-1.5 text-foreground">
                                        {direction && <span title={direction.label}>{direction.short}</span>}
                                        <span>{value(row) || "–"}</span>
                                        {row.crossed_threshold && <span className={statusBadge({ tone: "rejected" })}>Melewati ambang</span>}
                                    </span>
                                    {(row.note || row.signal) && (
                                        <span className="text-muted-foreground">
                                            {row.note}
                                            {row.note && row.signal && " · "}
                                            {row.signal && <>Sinyal: {row.signal.title}</>}
                                        </span>
                                    )}
                                    <span className="text-muted-foreground">oleh {row.recorded_by ?? "pengguna dihapus"}</span>
                                </div>
                                {removable && (
                                    <Button size="icon" variant="ghost" className="size-7 text-muted-foreground" disabled={busy} onClick={() => onRemove(row.id)} aria-label={`Hapus observasi ${row.observed_at}`}>
                                        <XIcon aria-hidden="true" />
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
