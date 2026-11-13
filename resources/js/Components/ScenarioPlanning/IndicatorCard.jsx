import { CheckIcon, PencilIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import HintTooltip from "@/Components/HintTooltip";
import ExternalFetchControl from "@/Components/ScenarioPlanning/ExternalFetchControl";
import IndicatorObservations from "@/Components/ScenarioPlanning/IndicatorObservations";
import StaleTag from "@/Components/ScenarioPlanning/StaleTag";
import ThresholdEvents from "@/Components/ScenarioPlanning/ThresholdEvents";
import ProvenanceBadge from "@/Components/ProvenanceBadge";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { INDICATOR_DIRECTIONS, INDICATOR_FREQUENCIES, INDICATOR_SOURCES } from "@/domain/scenarioPlanning";

/** One early warning indicator: how it is monitored, its expected direction per quadrant and its observations. */
export default function IndicatorCard({ indicator, quadrants, canManage, canRecord, busy, onEdit, onRemove, onAccept, onRecord, onRemoveObservation, onConfirmObservation, onFetch }) {
    const metric = [indicator.metric, indicator.unit && `(${indicator.unit})`].filter(Boolean).join(" ");
    const baseline = indicator.baseline_value != null ? `${indicator.baseline_value}${indicator.unit ? ` ${indicator.unit}` : ""}${indicator.baseline_date ? ` per ${indicator.baseline_date}` : ""}` : null;

    return (
        <article className={indicator.status === "retired" ? "flex flex-col gap-3 rounded-lg border border-dashed border-border p-4 opacity-70" : "flex flex-col gap-3 rounded-lg border border-border p-4"}>
            <header className="flex flex-wrap items-center gap-2">
                <h3 className="m-0 text-sm font-semibold text-foreground">{indicator.name}</h3>
                <span className={statusBadge({ tone: indicator.source_type === "manual" ? "neutral" : "approved" })}>{INDICATOR_SOURCES[indicator.source_type]}</span>
                {indicator.review_status === "proposed" && <ProvenanceBadge origin={indicator.origin} review="proposed" />}
                <StaleTag reason={indicator.stale_reason} />
                {indicator.status === "retired" && <span className={statusBadge({ tone: "closed" })}>Tidak dipantau lagi</span>}
                {!indicator.discriminates && Object.keys(indicator.directions).length > 0 && (
                    <HintTooltip content="Arahnya sama di semua kuadran yang dinilai, jadi indikator ini belum membedakan skenario.">
                        <button type="button" className={statusBadge({ tone: "pending" })}>
                            Belum membedakan skenario
                        </button>
                    </HintTooltip>
                )}
            </header>
            {indicator.description && <p className="m-0 text-sm text-foreground">{indicator.description}</p>}

            <dl className="m-0 grid grid-cols-2 gap-2 sm:grid-cols-4">
                {quadrants.map((quadrant) => {
                    const row = indicator.directions[quadrant.position];
                    const direction = INDICATOR_DIRECTIONS[row?.direction];
                    return (
                        <div key={quadrant.position} className="flex min-w-0 flex-col gap-1 rounded-md bg-muted/40 p-2">
                            <dt className="truncate text-[11px] text-muted-foreground">{quadrant.title}</dt>
                            <dd className="m-0 text-sm font-medium text-foreground">{direction ? `${direction.short} ${direction.label}` : <span className="text-xs font-normal text-muted-foreground">Belum diisi</span>}</dd>
                            {row?.note && <dd className="m-0 text-[11px] text-muted-foreground">{row.note}</dd>}
                        </div>
                    );
                })}
            </dl>

            <p className="m-0 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
                {metric && <span>Metrik: {metric}</span>}
                {baseline && <span>Awal: {baseline}</span>}
                <span>{INDICATOR_FREQUENCIES[indicator.frequency]}</span>
                {indicator.threshold_text && <span>Ambang: {indicator.threshold_text}{indicator.unit ? ` ${indicator.unit}` : ""}</span>}
                {indicator.threshold_rule && <span>{indicator.threshold_text ? "Catatan ambang" : "Ambang"}: {indicator.threshold_rule}</span>}
                {indicator.uncertainty && <span>CU: {indicator.uncertainty.name}</span>}
                {indicator.force && <span>DF: {indicator.force.keyword}</span>}
                {indicator.owner && <span>PJ: {indicator.owner.name}</span>}
            </p>

            <ThresholdEvents events={indicator.threshold_events ?? []} />

            {indicator.source_type === "external" && <ExternalFetchControl indicator={indicator} canManage={canManage} busy={busy} onFetch={onFetch} />}

            <IndicatorObservations indicator={indicator} canRecord={canRecord} canManage={canManage} busy={busy} onRecord={onRecord} onRemove={onRemoveObservation} onConfirm={onConfirmObservation} />

            {canManage && (
                <div className="flex flex-wrap gap-2">
                    {indicator.review_status === "proposed" && indicator.origin === "ai" && (
                        <Button size="sm" disabled={busy} onClick={onAccept}>
                            <CheckIcon aria-hidden="true" />
                            Terima
                        </Button>
                    )}
                    <Button size="sm" variant="outline" onClick={onEdit}>
                        <PencilIcon aria-hidden="true" />
                        {indicator.review_status === "proposed" ? "Tinjau" : "Ubah"}
                    </Button>
                    <ConfirmDialog title="Hapus indikator ini?" description={indicator.name} confirmText="Hapus" onConfirm={onRemove}>
                        <Button size="sm" variant="ghost" className="text-destructive">
                            <Trash2Icon aria-hidden="true" />
                            Hapus
                        </Button>
                    </ConfirmDialog>
                </div>
            )}
        </article>
    );
}
