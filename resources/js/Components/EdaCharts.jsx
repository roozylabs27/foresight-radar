import {
    ChartColumnIcon,
    ChartLineIcon,
    ChartNoAxesColumnIcon,
    ChartScatterIcon,
    ChartCandlestickIcon,
    HourglassIcon,
} from "lucide-react";
import dayjs from "dayjs";
import {
    Bar,
    BarChart,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip,
    XAxis,
    YAxis,
    ZAxis,
} from "recharts";

import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { Skeleton } from "@/Components/ui/skeleton";
import { cn } from "@/lib/utils";

const AXIS = { fill: "var(--muted-foreground)", fontSize: 11 };
const TOOLTIP_BOX = {
    background: "var(--popover)",
    border: "1px solid var(--border)",
    borderRadius: 8,
    color: "var(--popover-foreground)",
    fontSize: 12,
};
const TOOLTIP = {
    contentStyle: TOOLTIP_BOX,
    labelStyle: { color: "var(--popover-foreground)", fontWeight: 600 },
    cursor: { fill: "var(--muted)", opacity: 0.6 },
};
const LEGEND = { wrapperStyle: { fontSize: 12, color: "var(--muted-foreground)" } };
const SCORES = Array.from({ length: 10 }, (_, index) => index + 1);
const decimal = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 });

const FREQUENCY_SERIES = [
    { key: "decided_count", name: "Decided Plan (O)", color: "var(--success)" },
    { key: "monitoring_count", name: "Monitoring (∆)", color: "var(--warning)" },
];

const TREND_SERIES = [
    { key: "total", name: "Total Forces", color: "var(--primary)", width: 3 },
    { key: "decided_plan", name: "Decided Plan", color: "var(--success)", width: 2 },
    { key: "monitoring", name: "Monitoring", color: "var(--warning)", width: 2 },
];

const DISTRIBUTION_SERIES = [
    { key: "impact", name: "Impact", color: "var(--chart-1)" },
    { key: "uncertainty", name: "Uncertainty", color: "var(--chart-2)" },
];

const SPREAD_SERIES = [
    { key: "count", name: "Jumlah" },
    { key: "min", name: "Min" },
    { key: "q1", name: "Q1" },
    { key: "median", name: "Median" },
    { key: "q3", name: "Q3" },
    { key: "max", name: "Max" },
    { key: "mean", name: "Rata-rata" },
];

/**
 * The chart's numbers as a table for screen readers; the SVG is decorative.
 * The wrapper is hidden rather than the table, which would size itself to its
 * content and widen the page.
 */
function DataTableSr({ caption, labelHeader, labelKey, series, rows }) {
    return (
        <div className="sr-only">
            <table>
                <caption>{caption}</caption>
                <thead>
                    <tr>
                        <th scope="col">{labelHeader}</th>
                        {series.map((item) => (
                            <th key={item.key} scope="col">
                                {item.name}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={`${row[labelKey]}-${index}`}>
                            <th scope="row">{row[labelKey]}</th>
                            {series.map((item) => (
                                <td key={item.key}>{row[item.key] ?? "–"}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Empty({ children }) {
    return <p className="m-0 py-10 text-center text-sm text-muted-foreground">{children}</p>;
}

function TooltipCard({ title, rows }) {
    return (
        <div style={TOOLTIP_BOX} className="flex max-w-64 flex-col gap-1 px-3 py-2 shadow-sm">
            <span className="font-semibold">{title}</span>
            {rows.map(([label, value]) => (
                <span key={label} className="flex justify-between gap-4 tabular-nums">
                    <span className="text-muted-foreground">{label}</span>
                    <span>{value}</span>
                </span>
            ))}
        </div>
    );
}

function FrequencyChart({ data }) {
    const rotate = data.length > 6;

    return (
        <>
            <div aria-hidden="true" className="h-[290px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 8, right: 8, bottom: 4, left: -16 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis
                            dataKey="name"
                            interval={0}
                            tick={AXIS}
                            angle={rotate ? -25 : 0}
                            textAnchor={rotate ? "end" : "middle"}
                            // Angled labels need room above the legend.
                            height={rotate ? 64 : 30}
                            stroke="var(--border)"
                        />
                        <YAxis allowDecimals={false} tick={AXIS} stroke="var(--border)" />
                        <Tooltip {...TOOLTIP} />
                        <Legend {...LEGEND} verticalAlign="bottom" />
                        {FREQUENCY_SERIES.map((item, index) => (
                            <Bar
                                key={item.key}
                                dataKey={item.key}
                                name={item.name}
                                stackId="total"
                                fill={item.color}
                                radius={index === FREQUENCY_SERIES.length - 1 ? [3, 3, 0, 0] : 0}
                                isAnimationActive={false}
                            />
                        ))}
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <DataTableSr
                caption="Distribusi frekuensi per dimensi"
                labelHeader="Dimensi"
                labelKey="name"
                series={FREQUENCY_SERIES}
                rows={data}
            />
        </>
    );
}

function TrendChart({ data }) {
    return (
        <>
            <div aria-hidden="true" className="h-[290px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 8, right: 12, bottom: 4, left: -16 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis
                            dataKey="period"
                            tick={AXIS}
                            stroke="var(--border)"
                            tickFormatter={(period) => dayjs(`${period}-01`).format("MMM YY")}
                            minTickGap={12}
                        />
                        <YAxis allowDecimals={false} tick={AXIS} stroke="var(--border)" />
                        <Tooltip
                            {...TOOLTIP}
                            cursor={{ stroke: "var(--border)" }}
                            labelFormatter={(period) => dayjs(`${period}-01`).format("MMMM YYYY")}
                        />
                        <Legend {...LEGEND} verticalAlign="bottom" />
                        {TREND_SERIES.map((item) => (
                            <Line
                                key={item.key}
                                type="monotone"
                                dataKey={item.key}
                                name={item.name}
                                stroke={item.color}
                                strokeWidth={item.width}
                                dot={{ r: 3, fill: item.color }}
                                isAnimationActive={false}
                            />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            </div>
            <DataTableSr
                caption="Tren driving forces bulanan"
                labelHeader="Periode"
                labelKey="period"
                series={TREND_SERIES}
                rows={data}
            />
        </>
    );
}

/** Histogram of impact and uncertainty scores, side by side per score. */
function DistributionChart({ bins }) {
    return (
        <>
            <div aria-hidden="true" className="h-[260px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={bins} barGap={2} margin={{ top: 8, right: 8, bottom: 4, left: -16 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis dataKey="score" tick={AXIS} stroke="var(--border)" />
                        <YAxis allowDecimals={false} tick={AXIS} stroke="var(--border)" />
                        <Tooltip {...TOOLTIP} labelFormatter={(score) => `Skor ${score}`} />
                        <Legend {...LEGEND} verticalAlign="bottom" />
                        {DISTRIBUTION_SERIES.map((item) => (
                            <Bar
                                key={item.key}
                                dataKey={item.key}
                                name={item.name}
                                fill={item.color}
                                radius={[3, 3, 0, 0]}
                                maxBarSize={18}
                                isAnimationActive={false}
                            />
                        ))}
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <DataTableSr
                caption="Jumlah driving force per skor impact dan uncertainty"
                labelHeader="Skor"
                labelKey="score"
                series={DISTRIBUTION_SERIES}
                rows={bins}
            />
        </>
    );
}

function SummaryLine({ label, summary }) {
    if (summary?.median == null) return null;

    return (
        <span>
            {label}: median {decimal.format(summary.median)}, rata-rata {decimal.format(summary.mean)} (rentang{" "}
            {summary.min}–{summary.max})
        </span>
    );
}

/** Forces sharing one impact × uncertainty cell become one dot sized by count. */
function groupPoints(points) {
    const cells = new Map();
    points.forEach((point) => {
        const key = `${point.impact}-${point.uncertainty}`;
        if (!cells.has(key)) cells.set(key, { impact: point.impact, uncertainty: point.uncertainty, forces: [] });
        cells.get(key).forces.push(point.keyword);
    });
    return [...cells.values()].map((cell) => ({ ...cell, count: cell.forces.length }));
}

function RelationshipTooltip({ active, payload }) {
    const cell = active && payload?.[0]?.payload;
    if (!cell) return null;

    return (
        <TooltipCard
            title={`Impact ${cell.impact} · Uncertainty ${cell.uncertainty}`}
            rows={cell.forces.map((keyword, index) => [`${index + 1}.`, keyword])}
        />
    );
}

function RelationshipChart({ points }) {
    const cells = groupPoints(points);

    return (
        <>
            <div aria-hidden="true" className="h-[260px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <ScatterChart margin={{ top: 8, right: 12, bottom: 12, left: -16 }}>
                        <CartesianGrid stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis
                            type="number"
                            dataKey="impact"
                            name="Impact"
                            domain={[0.5, 10.5]}
                            ticks={SCORES}
                            tick={AXIS}
                            stroke="var(--border)"
                            label={{ value: "Impact", position: "insideBottom", offset: -8, ...AXIS }}
                        />
                        <YAxis
                            type="number"
                            dataKey="uncertainty"
                            name="Uncertainty"
                            domain={[0.5, 10.5]}
                            ticks={SCORES}
                            tick={AXIS}
                            stroke="var(--border)"
                        />
                        <ZAxis type="number" dataKey="count" range={[70, 420]} />
                        <Tooltip content={<RelationshipTooltip />} cursor={{ strokeDasharray: "3 3", stroke: "var(--border)" }} />
                        <Scatter
                            data={cells}
                            fill="var(--chart-1)"
                            fillOpacity={0.8}
                            stroke="var(--card)"
                            strokeWidth={2}
                            isAnimationActive={false}
                        />
                    </ScatterChart>
                </ResponsiveContainer>
            </div>
            <DataTableSr
                caption="Impact dan uncertainty tiap driving force"
                labelHeader="Driving force"
                labelKey="keyword"
                series={[
                    { key: "impact", name: "Impact" },
                    { key: "uncertainty", name: "Uncertainty" },
                ]}
                rows={points}
            />
        </>
    );
}

/** Stacked segments: an invisible base up to min, then min–Q1, Q1–median, median–Q3, Q3–max. */
function spreadSegments(rows) {
    return rows
        .filter((row) => row.median != null)
        .map((row) => ({
            ...row,
            base: row.min,
            lowerWhisker: row.q1 - row.min,
            lowerBox: row.median - row.q1,
            upperBox: row.q3 - row.median,
            upperWhisker: row.max - row.q3,
        }));
}

function SpreadTooltip({ active, payload }) {
    const row = active && payload?.[0]?.payload;
    if (!row) return null;

    return (
        <TooltipCard
            title={row.name}
            rows={[
                ["Jumlah force", row.count],
                ["Min – Max", `${row.min} – ${row.max}`],
                ["Q1 – Q3", `${decimal.format(row.q1)} – ${decimal.format(row.q3)}`],
                ["Median", decimal.format(row.median)],
                ["Rata-rata", decimal.format(row.mean)],
            ]}
        />
    );
}

/** Impact spread per group: light bar = full range, dark bar = Q1–Q3, dot = median. */
function SpreadChart({ rows, caption, labelHeader }) {
    const data = spreadSegments(rows);
    const rotate = data.length > 4;
    const segment = { stackId: "spread", isAnimationActive: false, stroke: "var(--card)", strokeWidth: 1 };

    return (
        <>
            <div aria-hidden="true" className="h-[260px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart data={data} margin={{ top: 8, right: 8, bottom: 4, left: -16 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis
                            dataKey="name"
                            interval={0}
                            tick={AXIS}
                            angle={rotate ? -25 : 0}
                            textAnchor={rotate ? "end" : "middle"}
                            height={rotate ? 56 : 30}
                            stroke="var(--border)"
                        />
                        <YAxis domain={[0, 10]} ticks={[0, 2, 4, 6, 8, 10]} tick={AXIS} stroke="var(--border)" />
                        <Tooltip content={<SpreadTooltip />} cursor={{ fill: "var(--muted)", opacity: 0.6 }} />
                        <Bar dataKey="base" fill="transparent" stackId="spread" stroke="none" isAnimationActive={false} maxBarSize={28} />
                        <Bar dataKey="lowerWhisker" name="Min – Q1" fill="var(--chart-1)" fillOpacity={0.3} maxBarSize={28} {...segment} />
                        <Bar dataKey="lowerBox" name="Q1 – Median" fill="var(--chart-1)" maxBarSize={28} {...segment} />
                        <Bar dataKey="upperBox" name="Median – Q3" fill="var(--chart-1)" maxBarSize={28} {...segment} />
                        <Bar dataKey="upperWhisker" name="Q3 – Max" fill="var(--chart-1)" fillOpacity={0.3} maxBarSize={28} {...segment} />
                        <Scatter dataKey="median" name="Median" fill="var(--foreground)" stroke="var(--card)" strokeWidth={2} isAnimationActive={false} />
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
            <div className="flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span aria-hidden="true" className="h-2.5 w-3 rounded-sm bg-chart-1/30" />
                    Rentang min – max
                </span>
                <span className="flex items-center gap-1.5">
                    <span aria-hidden="true" className="h-2.5 w-3 rounded-sm bg-chart-1" />
                    Q1 – Q3
                </span>
                <span className="flex items-center gap-1.5">
                    <span aria-hidden="true" className="size-2.5 rounded-full bg-foreground" />
                    Median
                </span>
            </div>
            <DataTableSr caption={caption} labelHeader={labelHeader} labelKey="name" series={SPREAD_SERIES} rows={rows} />
        </>
    );
}

function correlationText(relationship) {
    if (relationship?.pearson_r == null) {
        return "Korelasi belum bisa dihitung (butuh minimal dua force dengan skor yang bervariasi).";
    }
    return `Korelasi Pearson r = ${decimal.format(relationship.pearson_r)} (${relationship.strength}, n = ${relationship.n}).`;
}

/**
 * The four EDA analyses from the reasoning data: frequency per dimension and
 * over time, distribution of scores, impact × uncertainty relationship, and
 * impact compared across dimensions and horizons.
 */
export default function EdaCharts({
    loading = false,
    edaFrequency = [],
    edaTrend = [],
    edaTrendRange = null,
    edaDistribution = null,
    edaRelationship = null,
    edaComparison = null,
}) {
    if (loading) {
        return (
            <div className="grid gap-4 lg:grid-cols-2">
                {Array.from({ length: 6 }, (_, index) => (
                    <Skeleton key={index} className="h-80 w-full" />
                ))}
            </div>
        );
    }

    const bins = edaDistribution?.bins ?? [];
    const hasScores = bins.some((bin) => bin.impact > 0 || bin.uncertainty > 0);
    const points = edaRelationship?.points ?? [];
    const byDimension = edaComparison?.by_dimension ?? [];
    const byHorizon = edaComparison?.by_horizon ?? [];
    const trendLabel = edaTrendRange
        ? `${dayjs(edaTrendRange[0]).format("MMM YYYY")} – ${dayjs(edaTrendRange[1]).format("MMM YYYY")}`
        : null;

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel
                icon={<ChartColumnIcon className="text-primary" />}
                title="Distribusi Frekuensi per Dimensi Makro & Mezzo"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Frekuensi</span>}
                description="Jumlah driving force aktif di tiap dimensi makro (Economy, Ecology, Regulation, Technology) dan mezzo (Substitute, Supplier, Customer, Competitor)."
            >
                {edaFrequency.length > 0 ? (
                    <FrequencyChart data={edaFrequency} />
                ) : (
                    <Empty>Belum ada data distribusi dimensi.</Empty>
                )}
            </Panel>

            <Panel
                icon={<ChartLineIcon className="text-success" />}
                title="Tren Pertumbuhan Driving Forces Bulanan"
                badge={<span className={statusBadge()}>{trendLabel ?? "Dinamika Waktu"}</span>}
                description="Jumlah driving force per bulan dan pergeseran status aksinya, minimal 12 bulan terakhir hingga akhir periode."
            >
                {edaTrend.some((row) => row.total > 0) ? (
                    <TrendChart data={edaTrend} />
                ) : (
                    <Empty>Belum ada data tren bulanan untuk periode ini.</Empty>
                )}
            </Panel>

            <Panel
                icon={<ChartNoAxesColumnIcon className="text-primary" />}
                title="Distribusi Skor Impact & Uncertainty"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Distribusi</span>}
                description="Berapa driving force yang mendapat tiap skor 1–10."
            >
                {hasScores ? (
                    <>
                        <DistributionChart bins={bins} />
                        <p className="m-0 flex flex-col gap-0.5 text-xs text-muted-foreground tabular-nums">
                            <SummaryLine label="Impact" summary={edaDistribution.impact} />
                            <SummaryLine label="Uncertainty" summary={edaDistribution.uncertainty} />
                        </p>
                    </>
                ) : (
                    <Empty>Belum ada skor impact atau uncertainty pada periode ini.</Empty>
                )}
            </Panel>

            <Panel
                icon={<ChartScatterIcon className="text-primary" />}
                title="Hubungan Impact × Uncertainty"
                badge={
                    <span className={cn(statusBadge(), "bg-accent text-primary tabular-nums")}>
                        r = {edaRelationship?.pearson_r == null ? "–" : decimal.format(edaRelationship.pearson_r)}
                    </span>
                }
                description="Setiap titik adalah satu kombinasi skor; makin besar titiknya, makin banyak force di sana."
            >
                {points.length > 0 ? (
                    <>
                        <RelationshipChart points={points} />
                        <p className="m-0 text-xs text-muted-foreground tabular-nums">{correlationText(edaRelationship)}</p>
                    </>
                ) : (
                    <Empty>Belum ada force dengan skor impact dan uncertainty lengkap.</Empty>
                )}
            </Panel>

            <Panel
                icon={<ChartCandlestickIcon className="text-primary" />}
                title="Perbandingan Impact per Dimensi"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Perbandingan</span>}
                description="Sebaran skor impact di tiap dimensi yang punya driving force aktif."
            >
                {byDimension.length > 0 ? (
                    <SpreadChart rows={byDimension} caption="Sebaran impact per dimensi" labelHeader="Dimensi" />
                ) : (
                    <Empty>Belum ada data impact per dimensi.</Empty>
                )}
            </Panel>

            <Panel
                icon={<HourglassIcon className="text-primary" />}
                title="Perbandingan Impact per Time Horizon"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Perbandingan</span>}
                description="Sebaran skor impact untuk Short, Mid, dan Long Term."
            >
                {byHorizon.length > 0 ? (
                    <SpreadChart rows={byHorizon} caption="Sebaran impact per time horizon" labelHeader="Time horizon" />
                ) : (
                    <Empty>Belum ada data impact per time horizon.</Empty>
                )}
            </Panel>
        </div>
    );
}
