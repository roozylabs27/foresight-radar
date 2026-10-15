import { ChartColumnIcon, ChartLineIcon } from "lucide-react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { Skeleton } from "@/Components/ui/skeleton";
import { cn } from "@/lib/utils";

const AXIS = { fill: "var(--muted-foreground)", fontSize: 11 };
const TOOLTIP = {
    contentStyle: {
        background: "var(--popover)",
        border: "1px solid var(--border)",
        borderRadius: 8,
        color: "var(--popover-foreground)",
        fontSize: 12,
    },
    labelStyle: { color: "var(--popover-foreground)", fontWeight: 600 },
    cursor: { fill: "var(--muted)", opacity: 0.6 },
};
const LEGEND = { wrapperStyle: { fontSize: 12, color: "var(--muted-foreground)" } };

const FREQUENCY_SERIES = [
    { key: "decided_count", name: "Decided Plan (O)", color: "var(--success)" },
    { key: "monitoring_count", name: "Monitoring (∆)", color: "var(--warning)" },
];

const TREND_SERIES = [
    { key: "total", name: "Total Forces", color: "var(--primary)", width: 3 },
    { key: "decided_plan", name: "Decided Plan", color: "var(--success)", width: 2 },
    { key: "monitoring", name: "Monitoring", color: "var(--warning)", width: 2 },
];

/** The chart's numbers as a table for screen readers; the SVG is decorative. */
function DataTableSr({ caption, labelHeader, labelKey, series, rows }) {
    return (
        <table className="sr-only">
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
                {rows.map((row) => (
                    <tr key={row[labelKey]}>
                        <th scope="row">{row[labelKey]}</th>
                        {series.map((item) => (
                            <td key={item.key}>{row[item.key] ?? 0}</td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
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
            <div aria-hidden="true" className="h-[260px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 8, right: 12, bottom: 4, left: -16 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis dataKey="period" tick={AXIS} stroke="var(--border)" />
                        <YAxis allowDecimals={false} tick={AXIS} stroke="var(--border)" />
                        <Tooltip {...TOOLTIP} cursor={{ stroke: "var(--border)" }} />
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

/** Dimension frequency (stacked bars) and monthly trend (lines) from the reasoning data. */
export default function EdaCharts({ loading = false, edaFrequency = [], edaTrend = [] }) {
    if (loading) {
        return (
            <div className="grid gap-4 lg:grid-cols-2">
                <Skeleton className="h-80 w-full" />
                <Skeleton className="h-80 w-full" />
            </div>
        );
    }

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel
                icon={<ChartColumnIcon className="text-primary" />}
                title="Distribusi Frekuensi per Dimensi STEEP"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Kepadatan Sektor</span>}
                description="Konsentrasi driving forces aktif di setiap dimensi lingkungan makro dan mezzo."
            >
                {edaFrequency.length > 0 ? (
                    <FrequencyChart data={edaFrequency} />
                ) : (
                    <p className="m-0 py-10 text-center text-sm text-muted-foreground">Belum ada data distribusi dimensi.</p>
                )}
            </Panel>

            <Panel
                icon={<ChartLineIcon className="text-success" />}
                title="Tren Pertumbuhan Driving Forces Bulanan"
                badge={<span className={statusBadge()}>Dinamika Waktu</span>}
                description="Evolusi jumlah driving force dan pergeseran status aksi sepanjang waktu."
            >
                {edaTrend.length > 0 ? (
                    <TrendChart data={edaTrend} />
                ) : (
                    <p className="m-0 py-10 text-center text-sm text-muted-foreground">
                        Belum ada data tren bulanan untuk periode ini.
                    </p>
                )}
            </Panel>
        </div>
    );
}
