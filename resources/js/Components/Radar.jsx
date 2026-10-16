import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import qs from "qs";
import { toast } from "sonner";
import { InfoIcon, MaximizeIcon, MinimizeIcon } from "lucide-react";

import { currentMonth } from "@/Components/AnalysisFilters";
import DataTable from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import { priorityBadge } from "@/Components/PriorityBadge";
import RadarScope, { PRIORITY_FILLS, priorityFill } from "@/Components/RadarScope";
import RadarSignalSheet from "@/Components/RadarSignalSheet";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Skeleton } from "@/Components/ui/skeleton";
import { computeAntiCollisionPoints, HORIZON_BANDS } from "@/lib/radarLayout";
import { cn } from "@/lib/utils";

const BAND_SWATCHES = { 1: "var(--horizon-short)", 2: "var(--horizon-mid)", 3: "var(--horizon-long)" };
const PRIORITY_LABELS = { high: "High Priority", medium: "Medium Priority", low: "Low Priority" };

function Card({ title, actions, className, children }) {
    return (
        <section
            aria-label={typeof title === "string" ? title : undefined}
            className={cn("flex min-w-0 flex-col gap-3 rounded-lg border border-solid border-border bg-card p-4", className)}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">{title}</div>
                {actions}
            </div>
            {children}
        </section>
    );
}

function Legends() {
    return (
        <div className="flex flex-col items-center gap-2 text-xs text-muted-foreground">
            <ul className="m-0 flex list-none flex-wrap justify-center gap-x-4 gap-y-1 p-0">
                {HORIZON_BANDS.map((band) => (
                    <li key={band.horizon} className="flex items-center gap-1.5">
                        <span
                            aria-hidden="true"
                            className="size-3 rounded-full border border-solid border-border"
                            style={{ backgroundColor: BAND_SWATCHES[band.horizon] }}
                        />
                        {band.label}
                    </li>
                ))}
            </ul>
            <ul className="m-0 flex list-none flex-wrap justify-center gap-x-4 gap-y-1 p-0">
                {Object.entries(PRIORITY_FILLS).map(([key, fill]) => (
                    <li key={key} className="flex items-center gap-1.5">
                        <span aria-hidden="true" className="size-2.5 rounded-full" style={{ backgroundColor: fill }} />
                        {PRIORITY_LABELS[key]}
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Foresight Radar: the signal list beside the 360° radar, a detail Sheet per
 * item and a fullscreen mode. `date` is `{ date: [start, end] }` (defaults to
 * this month) and `selectData` may hold a `dimension` id. `setLoading` mirrors
 * the loading state for a parent with its own indicator.
 */
export default function Radar({ date, selectData, setLoading }) {
    const [items, setItems] = useState([]);
    const [loading, setLocalLoading] = useState(true);
    const [activeNo, setActiveNo] = useState(null);
    const [selected, setSelected] = useState(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [fullscreen, setFullscreen] = useState(false);

    const dimension = selectData?.dimension ?? null;
    const queryKey = qs.stringify({ date: date?.date ?? currentMonth(), dimension: dimension ?? undefined });

    useEffect(() => {
        const controller = new AbortController();
        setLocalLoading(true);
        axios
            .get(`${route("visualization.foresight-radar.get-data")}?${queryKey}`, { signal: controller.signal })
            .then((response) => {
                const rows = Array.isArray(response.data) ? response.data : (response.data?.data ?? []);
                setItems(rows.map((row, index) => ({ ...row, no: index + 1 })));
                setLocalLoading(false);
            })
            .catch((error) => {
                if (axios.isCancel(error)) return;
                toast.error("Gagal memuat data radar.", { description: error.response?.data?.message ?? error.message });
                setLocalLoading(false);
            });
        return () => controller.abort();
    }, [queryKey]);

    useEffect(() => {
        setLoading?.(loading);
    }, [loading, setLoading]);

    // Escape leaves fullscreen, unless it is closing the detail sheet.
    useEffect(() => {
        if (!fullscreen) return undefined;
        const handleKeyDown = (event) => {
            if (event.key === "Escape" && !sheetOpen) setFullscreen(false);
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [fullscreen, sheetOpen]);

    const points = useMemo(() => computeAntiCollisionPoints(items), [items]);

    const openDetail = (item) => {
        setSelected(item);
        setSheetOpen(true);
    };

    const columns = useMemo(
        () => [
            {
                accessorKey: "no",
                header: "No",
                meta: { align: "center", width: "56px" },
                cell: ({ row }) => (
                    <span
                        className="inline-flex size-6 items-center justify-center rounded-full text-[11px] font-bold text-primary-foreground tabular-nums"
                        style={{ backgroundColor: priorityFill(row.original.priority) }}
                    >
                        {row.original.no}
                    </span>
                ),
            },
            {
                accessorKey: "keyword",
                header: "Signal of Changes",
                cell: ({ row }) => (
                    <div className="flex flex-col gap-1">
                        <span className="text-[13px] font-semibold text-foreground">{row.original.keyword}</span>
                        <div className="flex flex-wrap gap-1">
                            <DimensionChip name={row.original.dimension} />
                            {row.original.status_action && (
                                <span className={cn(statusBadge(), "bg-accent font-mono text-primary")}>
                                    {row.original.status_action}
                                </span>
                            )}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: "priority",
                header: "Priority",
                meta: { align: "center" },
                // Short label: the list column is narrow beside the radar.
                cell: ({ getValue }) => {
                    const priority = String(getValue() ?? "").toLowerCase();
                    return (
                        <span className={priorityBadge({ priority: priority in PRIORITY_LABELS ? priority : "unknown" })}>
                            {getValue() || "-"}
                        </span>
                    );
                },
            },
            {
                id: "detail",
                header: () => <span className="sr-only">Detail</span>,
                meta: { align: "center", width: "48px" },
                cell: ({ row }) => (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Detail & provenance ${row.original.keyword}`}
                        onClick={(event) => {
                            event.stopPropagation();
                            openDetail(row.original);
                        }}
                    >
                        <InfoIcon aria-hidden="true" className="text-primary" />
                    </Button>
                ),
            },
        ],
        []
    );

    // Fullscreen is the design system's presentation mode: a night ground in either
    // theme. The scoped `dark` class switches every token inside to its dark value.
    return (
        <div
            className={cn(
                fullscreen && "dark fixed inset-0 z-40 overflow-y-auto bg-night p-4 text-foreground lg:p-6"
            )}
        >
            <div className="grid gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <Card
                    title={
                        <>
                            <h2 className="m-0 text-sm font-semibold text-foreground">Signal of Changes</h2>
                            <span className={cn(statusBadge(), "bg-primary text-primary-foreground tabular-nums")}>
                                {items.length}
                            </span>
                        </>
                    }
                >
                    <DataTable
                        columns={columns}
                        data={items}
                        loading={loading}
                        getRowId={(row) => String(row.no)}
                        onRowClick={openDetail}
                        onRowHover={(row) => setActiveNo(row?.no ?? null)}
                        rowClassName={(row) => row.no === activeNo && "bg-accent"}
                        caption="Daftar signal of changes"
                        emptyMessage="No signals found for the selected period or dimension"
                        className={cn("overflow-y-auto", fullscreen ? "max-h-[calc(100vh-140px)]" : "max-h-[560px]")}
                    />
                </Card>

                <Card
                    title={
                        <>
                            <h2 className="m-0 text-sm font-semibold text-foreground">Radar Visualization</h2>
                            {dimension && (
                                <span className={cn(statusBadge(), "bg-accent text-primary")}>Filtered by Dimension</span>
                            )}
                        </>
                    }
                    actions={
                        <Button variant="ghost" size="sm" onClick={() => setFullscreen((value) => !value)}>
                            {fullscreen ? <MinimizeIcon aria-hidden="true" /> : <MaximizeIcon aria-hidden="true" />}
                            {fullscreen ? "Kecilkan" : "Layar Penuh"}
                        </Button>
                    }
                >
                    {loading ? (
                        <Skeleton className="mx-auto aspect-[16/15] w-full max-w-[640px]" />
                    ) : items.length === 0 ? (
                        <p className="m-0 py-28 text-center text-sm text-muted-foreground">
                            No radar signals available to display
                        </p>
                    ) : (
                        <RadarScope
                            points={points}
                            activeNo={activeNo}
                            onActivate={setActiveNo}
                            onSelect={openDetail}
                            style={fullscreen ? { maxWidth: "min(100%, calc((100vh - 220px) * 1.07))" } : undefined}
                        />
                    )}
                    <Legends />
                </Card>
            </div>

            {/* The sheet renders in a portal, outside the fullscreen container, so it needs its own `dark`. */}
            <RadarSignalSheet
                item={selected}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                className={fullscreen ? "dark" : undefined}
            />
        </div>
    );
}
