import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import qs from "qs";
import { toast } from "sonner";
import {
    CartesianGrid,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

import { currentMonth } from "@/Components/AnalysisFilters";
import DataTable from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import { Skeleton } from "@/Components/ui/skeleton";
import { statusBadge } from "@/Components/StatusBadge";

const SCALE = Array.from({ length: 10 }, (_, index) => index + 1);

// Bubble colours follow the priority tokens; the label uses primary-foreground,
// which contrasts with these fills in both themes.
const PRIORITIES = {
    high: { label: "High Priority", fill: "var(--danger)" },
    medium: { label: "Medium Priority", fill: "var(--warning)" },
    low: { label: "Low Priority", fill: "var(--success)" },
};

// Quadrant of the matrix, as the original chart coloured it: both scores at 6+
// is high, one of them is medium, neither is low.
function quadrant(impact, uncertainty) {
    const highImpact = impact >= 6;
    const highUncertainty = uncertainty >= 6;
    if (highImpact && highUncertainty) return "high";
    return highImpact || highUncertainty ? "medium" : "low";
}

/** One bubble per matrix cell, holding the row numbers of every item in it. */
function groupByCell(items) {
    const cells = new Map();
    items.forEach((item) => {
        const key = `${item.impact_analysis}-${item.uncertainty_analysis}`;
        if (!cells.has(key)) {
            cells.set(key, {
                x: item.impact_analysis,
                y: item.uncertainty_analysis,
                priority: quadrant(item.impact_analysis, item.uncertainty_analysis),
                items: [],
            });
        }
        cells.get(key).items.push(item);
    });
    return [...cells.values()];
}

function Bubble({ cx, cy, payload }) {
    const numbers = payload.items.map((item) => item.no).join(",");
    const radius = Math.min(26, 16 + numbers.length * 1.5);

    return (
        <g>
            <circle cx={cx} cy={cy} r={radius} fill={PRIORITIES[payload.priority].fill} />
            <text
                x={cx}
                y={cy}
                textAnchor="middle"
                dominantBaseline="central"
                fontSize={11}
                fontWeight={600}
                fill="var(--primary-foreground)"
            >
                {numbers}
            </text>
        </g>
    );
}

function BubbleTooltip({ active, payload }) {
    const cell = payload?.[0]?.payload;
    if (!active || !cell) return null;

    return (
        <div className="max-w-64 rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <p className="m-0 mb-1 font-semibold">
                Impact {cell.x} · Uncertainty {cell.y}
            </p>
            <ul className="m-0 list-none p-0">
                {cell.items.map((item) => (
                    <li key={item.no}>
                        {item.no}. {item.keyword}
                    </li>
                ))}
            </ul>
        </div>
    );
}

const columns = [
    {
        accessorKey: "no",
        header: "No",
        meta: { align: "center", width: "56px", className: "tabular-nums text-muted-foreground" },
    },
    {
        accessorKey: "keyword",
        header: "Keyword",
        meta: { className: "font-medium text-foreground" },
    },
    {
        accessorKey: "impact_analysis",
        header: "Impact",
        meta: { align: "center", className: "tabular-nums" },
    },
    {
        accessorKey: "uncertainty_analysis",
        header: "Uncertainty",
        meta: { align: "center", className: "tabular-nums" },
    },
];

/**
 * Uncertainty × impact matrix of approved driving forces, with the numbered
 * item list beside it. `date` is `{ date: [start, end] }` (defaults to this
 * month) and `selectData` may hold a `dimension` id. `setLoading` mirrors the
 * loading state for a parent with its own indicator.
 */
export default function PrioritizingChart({ date, selectData, dimensions = [], setLoading }) {
    const [items, setItems] = useState([]);
    const [loading, setLocalLoading] = useState(true);

    const dimension = selectData?.dimension ?? null;
    const range = date?.date ?? currentMonth();
    const queryKey = qs.stringify({ date: range, dimension: dimension ?? undefined });

    useEffect(() => {
        const controller = new AbortController();
        setLocalLoading(true);
        axios
            .get(`${route("visualization.prioritizing.get-data")}?${queryKey}`, { signal: controller.signal })
            .then((response) => {
                const rows = Array.isArray(response.data) ? response.data : (response.data?.data ?? []);
                setItems(rows.map((row, index) => ({ ...row, no: index + 1 })));
                setLocalLoading(false);
            })
            .catch((error) => {
                if (axios.isCancel(error)) return;
                toast.error("Gagal memuat data prioritas.", {
                    description: error.response?.data?.message ?? error.message,
                });
                setLocalLoading(false);
            });
        return () => controller.abort();
    }, [queryKey]);

    useEffect(() => {
        setLoading?.(loading);
    }, [loading, setLoading]);

    const cells = useMemo(() => groupByCell(items), [items]);
    const scope = dimensions.find((item) => String(item.value) === String(dimension))?.label;

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <section aria-label="Daftar item prioritas" className="flex min-w-0 flex-col gap-2">
                <div className="flex items-center gap-2">
                    {scope ? <DimensionChip name={scope} /> : <span className={statusBadge()}>OVERALL</span>}
                    <span className="text-xs text-muted-foreground">{items.length} item</span>
                </div>
                <DataTable
                    columns={columns}
                    data={items}
                    loading={loading}
                    getRowId={(row) => String(row.no)}
                    caption="Daftar item pada matriks prioritas"
                    className="max-h-[480px] overflow-y-auto"
                />
            </section>

            <section
                aria-label="Matrix of Uncertainty and Impact Analysis"
                className="flex min-w-0 flex-col gap-2 rounded-lg border border-solid border-border bg-card p-4"
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="m-0 text-sm font-semibold text-foreground">Matrix of Uncertainty and Impact Analysis</h3>
                    <ul className="m-0 flex list-none flex-wrap gap-3 p-0 text-xs text-muted-foreground">
                        {Object.entries(PRIORITIES).map(([key, priority]) => (
                            <li key={key} className="flex items-center gap-1.5">
                                <span aria-hidden="true" className="size-2.5 rounded-full" style={{ backgroundColor: priority.fill }} />
                                {priority.label}
                            </li>
                        ))}
                    </ul>
                </div>

                {loading ? (
                    <Skeleton className="h-[440px] w-full" />
                ) : (
                    // The item list beside the chart carries the same data for screen readers.
                    <div aria-hidden="true" className="h-[440px] w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <ScatterChart margin={{ top: 12, right: 16, bottom: 28, left: 12 }}>
                                <CartesianGrid stroke="var(--border)" />
                                <XAxis
                                    type="number"
                                    dataKey="x"
                                    domain={[0.5, 10.5]}
                                    ticks={SCALE}
                                    tick={{ fill: "var(--muted-foreground)", fontSize: 12 }}
                                    stroke="var(--border)"
                                    label={{
                                        value: "IMPACT ANALYSIS",
                                        position: "insideBottom",
                                        offset: -16,
                                        fill: "var(--muted-foreground)",
                                        fontSize: 12,
                                    }}
                                />
                                <YAxis
                                    type="number"
                                    dataKey="y"
                                    domain={[0.5, 10.5]}
                                    ticks={SCALE}
                                    tick={{ fill: "var(--muted-foreground)", fontSize: 12 }}
                                    stroke="var(--border)"
                                    label={{
                                        value: "UNCERTAINTY ANALYSIS",
                                        angle: -90,
                                        position: "insideLeft",
                                        offset: 4,
                                        fill: "var(--muted-foreground)",
                                        fontSize: 12,
                                        style: { textAnchor: "middle" },
                                    }}
                                />
                                <Tooltip content={<BubbleTooltip />} cursor={false} isAnimationActive={false} />
                                <Scatter data={cells} shape={<Bubble />} isAnimationActive={false} />
                            </ScatterChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </section>
        </div>
    );
}
