import { useId, useState } from "react";
import dayjs from "dayjs";
import { HistoryIcon, TimerIcon } from "lucide-react";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
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
};
const LEGEND = { wrapperStyle: { fontSize: 12, color: "var(--muted-foreground)" } };
const ALL = "__all__";
const MAX_BARS = 10;

const SCORE_SERIES = [
    { key: "avg_impact", name: "Rata-rata impact", color: "var(--chart-1)" },
    { key: "avg_uncertainty", name: "Rata-rata uncertainty", color: "var(--chart-2)" },
];

const decimal = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 });
const signed = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2, signDisplay: "exceptZero" });
const monthLabel = (period) => dayjs(`${period}-01`).format("MMM YY");

function Empty({ children }) {
    return <p className="m-0 py-8 text-center text-sm text-muted-foreground">{children}</p>;
}

function Stat({ label, value, hint }) {
    return (
        <div className="flex min-w-0 flex-col gap-0.5 rounded-md bg-muted/50 px-3 py-2">
            <span className="text-[11px] text-muted-foreground">{label}</span>
            <span className="text-base font-bold whitespace-nowrap text-foreground tabular-nums">{value}</span>
            {hint && <span className="text-[11px] text-muted-foreground">{hint}</span>}
        </div>
    );
}

function ScoreTrend({ trend }) {
    const selectId = useId();
    const [series, setSeries] = useState(ALL);
    const selected = series === ALL ? trend.overall : trend.dimensions.find((item) => item.name === series) ?? trend.overall;
    const changes = [
        ["impact", selected?.impact_change],
        ["uncertainty", selected?.uncertainty_change],
    ].filter(([, value]) => value != null);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <label htmlFor={selectId} className="text-xs text-muted-foreground">
                    Dimensi
                </label>
                <Select value={series} onValueChange={setSeries}>
                    <SelectTrigger id={selectId} size="sm" className="w-[180px] bg-card">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        <SelectItem value={ALL}>Semua dimensi</SelectItem>
                        {trend.dimensions.map((item) => (
                            <SelectItem key={item.name} value={item.name}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div aria-hidden="true" className="h-[240px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={selected.points} margin={{ top: 8, right: 12, bottom: 4, left: -16 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis dataKey="period" tick={AXIS} stroke="var(--border)" tickFormatter={monthLabel} minTickGap={12} />
                        <YAxis domain={[0, 10]} ticks={[0, 2, 4, 6, 8, 10]} tick={AXIS} stroke="var(--border)" />
                        <Tooltip
                            {...TOOLTIP}
                            cursor={{ stroke: "var(--border)" }}
                            labelFormatter={(period) => dayjs(`${period}-01`).format("MMMM YYYY")}
                            formatter={(value, name) => [value == null ? "–" : decimal.format(value), name]}
                        />
                        <Legend {...LEGEND} verticalAlign="bottom" />
                        {SCORE_SERIES.map((item) => (
                            <Line
                                key={item.key}
                                type="monotone"
                                dataKey={item.key}
                                name={item.name}
                                stroke={item.color}
                                strokeWidth={2}
                                dot={{ r: 3, fill: item.color }}
                                isAnimationActive={false}
                            />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            </div>
            <div className="sr-only">
            <table>
                <caption>Rata-rata skor {selected.name} per bulan dari riwayat rating</caption>
                <thead>
                    <tr>
                        <th scope="col">Periode</th>
                        <th scope="col">Force</th>
                        {SCORE_SERIES.map((item) => (
                            <th key={item.key} scope="col">
                                {item.name}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {selected.points.map((point) => (
                        <tr key={point.period}>
                            <th scope="row">{point.period}</th>
                            <td>{point.n}</td>
                            {SCORE_SERIES.map((item) => (
                                <td key={item.key}>{point[item.key] ?? "–"}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
            </div>
            <p className="m-0 text-xs text-muted-foreground tabular-nums">
                {changes.length > 0
                    ? `Perubahan dari bulan pertama ke terakhir: ${changes
                          .map(([label, value]) => `${label} ${signed.format(value) || "0"}`)
                          .join(", ")}.`
                    : "Belum ada dua bulan berisi data untuk dimensi ini."}
            </p>
        </div>
    );
}

function VelocityChart({ transitions }) {
    const data = transitions.slice(0, MAX_BARS);

    return (
        <>
            <div aria-hidden="true" className="w-full" style={{ height: 48 + data.length * 30 }}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} layout="vertical" margin={{ top: 4, right: 16, bottom: 4, left: 8 }}>
                        <CartesianGrid horizontal={false} stroke="var(--border)" strokeDasharray="4 4" />
                        <XAxis type="number" tick={AXIS} stroke="var(--border)" allowDecimals={false} />
                        <YAxis
                            type="category"
                            dataKey="keyword"
                            tick={AXIS}
                            stroke="var(--border)"
                            width={120}
                            tickFormatter={(value) => (value.length > 18 ? `${value.slice(0, 17)}…` : value)}
                        />
                        <Tooltip
                            {...TOOLTIP}
                            cursor={{ fill: "var(--muted)", opacity: 0.6 }}
                            formatter={(value) => [`${decimal.format(value)} hari`, "Monitoring → Decided Plan"]}
                        />
                        <Bar dataKey="days" fill="var(--chart-1)" radius={[0, 3, 3, 0]} maxBarSize={18} isAnimationActive={false} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <div className="sr-only">
            <table>
                <caption>Lama force di Monitoring sebelum menjadi Decided Plan</caption>
                <thead>
                    <tr>
                        <th scope="col">Driving force</th>
                        <th scope="col">Monitoring sejak</th>
                        <th scope="col">Decided Plan</th>
                        <th scope="col">Hari</th>
                    </tr>
                </thead>
                <tbody>
                    {transitions.map((item) => (
                        <tr key={item.id}>
                            <th scope="row">{item.keyword}</th>
                            <td>{item.monitoring_since}</td>
                            <td>{item.decided_at}</td>
                            <td>{item.days}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            </div>
        </>
    );
}

function days(value) {
    return value == null ? "–" : `${decimal.format(value)} hari`;
}

/**
 * Phase 2 charts from the rating history: how average scores move over time
 * and how fast forces go from Monitoring to Decided Plan. Both say so plainly
 * when the history is still too short.
 */
export default function HistoryCharts({ loading = false, historyTrend = null, transitionVelocity = null }) {
    if (loading) {
        return (
            <div className="grid gap-4 lg:grid-cols-2">
                <Skeleton className="h-80 w-full" />
                <Skeleton className="h-80 w-full" />
            </div>
        );
    }

    const velocity = transitionVelocity ?? { transitions: [], still_monitoring: [] };
    const waiting = velocity.still_monitoring ?? [];

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel
                icon={<HistoryIcon className="text-primary" />}
                title="Tren Skor dari Riwayat Rating"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Validasi Empiris</span>}
                description="Rata-rata impact dan uncertainty di akhir tiap bulan, memakai skor terakhir yang tercatat untuk setiap force."
            >
                {historyTrend?.enough ? (
                    <ScoreTrend trend={historyTrend} />
                ) : (
                    <Empty>
                        {historyTrend?.since
                            ? `Riwayat rating baru tercatat sejak ${dayjs(historyTrend.since).format("D MMM YYYY")}: ${historyTrend.months_with_data} bulan berisi data dan ${historyTrend.updates} perubahan skor. Tren baru bermakna setelah ada minimal 2 bulan data dan perubahan skor.`
                            : "Belum ada riwayat rating untuk filter ini."}
                    </Empty>
                )}
            </Panel>

            <Panel
                icon={<TimerIcon className="text-primary" />}
                title="Kecepatan Monitoring → Decided Plan"
                badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Validasi Empiris</span>}
                description="Berapa hari sebuah force berada di Monitoring sebelum diputuskan menjadi Decided Plan, dari keputusan status aksi dan riwayat rating."
            >
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <Stat label="Perpindahan" value={velocity.count ?? 0} />
                    <Stat label="Median" value={days(velocity.median_days)} />
                    <Stat label="Rata-rata" value={days(velocity.mean_days)} />
                    <Stat
                        label="Masih Monitoring"
                        value={waiting.length}
                        hint={waiting.length > 0 ? `median ${days(velocity.still_monitoring_median_days)}` : null}
                    />
                </div>
                {velocity.transitions.length > 0 ? (
                    <>
                        <VelocityChart transitions={velocity.transitions} />
                        {velocity.transitions.length > MAX_BARS && (
                            <p className="m-0 text-xs text-muted-foreground">
                                Menampilkan {MAX_BARS} perpindahan terlama dari {velocity.transitions.length}.
                            </p>
                        )}
                    </>
                ) : (
                    <Empty>Belum ada force yang berpindah dari Monitoring ke Decided Plan pada periode ini.</Empty>
                )}
            </Panel>
        </div>
    );
}
