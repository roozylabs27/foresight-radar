import { InfoIcon, TriangleAlertIcon } from "lucide-react";

import HintTooltip from "@/Components/HintTooltip";
import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { INDICATOR_DIRECTIONS } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

const READINGS = {
    strengthening: { label: "Menguat", tone: "approved" },
    weakening: { label: "Melemah", tone: "rejected" },
    unclear: { label: "Belum jelas", tone: "pending" },
};
const VERDICTS = { supporting: "sesuai", contradicting: "berlawanan", unclear: "belum jelas" };
const FORMULA =
    "Tiap indikator aktif dihitung sekali, dari observasi berarah terbarunya dalam 12 bulan terakhir. Arah sama dengan harapan kuadran = sesuai; naik lawan turun = berlawanan; stabil lawan bergerak = belum jelas. Harapan kualitatif tidak dihitung.";

const direction = (key) => (INDICATOR_DIRECTIONS[key] ? `${INDICATOR_DIRECTIONS[key].short} ${INDICATOR_DIRECTIONS[key].label.toLowerCase()}` : key);

/**
 * Which scenario the recent evidence points to, and why (audit U5). A transparent count per
 * quadrant, explicitly not a probability; the formula is in docs/methodology/scenario-relevance.md.
 */
export default function RelevancePanel({ relevance }) {
    if (!relevance) return null;

    return (
        <Panel
            title="Skenario yang menguat"
            badge={
                <HintTooltip content={FORMULA}>
                    <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                        <InfoIcon aria-hidden="true" className="size-3.5" />
                        Hitungan, bukan probabilitas
                    </span>
                </HintTooltip>
            }
            description={relevance.counted === 0
                ? `Belum ada indikator dengan observasi berarah sejak ${relevance.since}. Catat observasi untuk melihat skenario mana yang mulai terjadi.`
                : `Dari ${relevance.counted} indikator aktif, masing-masing dengan observasi berarah terbarunya sejak ${relevance.since}.${relevance.outdated ? ` ${relevance.outdated} indikator tidak dihitung karena observasi terakhirnya lebih dari ${relevance.window_months} bulan lalu.` : ""}`}
        >
            <ul className="m-0 grid list-none gap-3 p-0 sm:grid-cols-2 lg:grid-cols-4">
                {relevance.quadrants.map((row) => {
                    const leading = relevance.leading === row.position;
                    const reading = READINGS[row.reading] ?? READINGS.unclear;
                    return (
                        <li key={row.position} className={cn("flex flex-col gap-2 rounded-lg border p-3", leading ? "border-primary bg-primary/5" : "border-border")}>
                            <div className="flex items-center justify-between gap-2">
                                <span className="min-w-0 truncate text-sm font-semibold text-foreground">{row.title ?? row.position}</span>
                                <span className={statusBadge({ tone: reading.tone })}>{leading ? "Paling sesuai" : reading.label}</span>
                            </div>
                            <p className="m-0 text-xs text-muted-foreground">
                                {row.supporting} sesuai · {row.contradicting} berlawanan · {row.unclear} belum jelas
                            </p>
                            {row.indicators.length > 0 && (
                                <details className="text-xs">
                                    <summary className="cursor-pointer text-primary">Alasan ({row.indicators.length} indikator)</summary>
                                    <ul className="m-0 mt-1.5 flex list-none flex-col gap-1.5 p-0">
                                        {row.indicators.map((item) => (
                                            <li key={item.name} className="flex flex-col">
                                                <span className="font-medium text-foreground">
                                                    {item.name}
                                                    {item.crossed_threshold && <TriangleAlertIcon aria-label="melewati ambang" className="ml-1 inline size-3 text-danger" />}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {direction(item.observed)} ({item.observed_at}); skenario ini mengharapkan {direction(item.expected)}: {VERDICTS[item.verdict]}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </li>
                    );
                })}
            </ul>
        </Panel>
    );
}
