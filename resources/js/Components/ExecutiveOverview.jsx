import { useState } from "react";
import { CompassIcon, GitBranchIcon, RocketIcon, ShieldCheckIcon, SirenIcon } from "lucide-react";

import DimensionChip from "@/Components/DimensionChip";
import Panel from "@/Components/Panel";
import PriorityBadge from "@/Components/PriorityBadge";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Skeleton } from "@/Components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { cn } from "@/lib/utils";

// ReasoningEngineService sends antd colour names for the readiness posture.
const READINESS_TONES = {
    success: { border: "border-l-success", badge: "bg-success-soft text-success", icon: "text-success" },
    processing: { border: "border-l-primary", badge: "bg-accent text-primary", icon: "text-primary" },
    warning: { border: "border-l-warning", badge: "bg-warning-soft text-warning", icon: "text-warning" },
    error: { border: "border-l-danger", badge: "bg-danger-soft text-danger", icon: "text-danger" },
};

/** Half-circle gauge from 0 to 100; the filled arc shows the score. */
export function Gauge({ value, label, color }) {
    const score = Math.max(0, Math.min(100, Number(value) || 0));
    const arc = "M 20 100 A 80 80 0 0 1 180 100";

    return (
        <svg
            viewBox="0 0 200 128"
            role="img"
            aria-label={`${label}: ${score.toFixed(1)} dari 100`}
            className="mx-auto block h-auto w-full max-w-[280px]"
        >
            <path d={arc} fill="none" stroke="var(--muted)" strokeWidth={14} strokeLinecap="round" />
            <path
                d={arc}
                fill="none"
                stroke={color}
                strokeWidth={14}
                strokeLinecap="round"
                pathLength={100}
                strokeDasharray={`${score} 100`}
            />
            {[0, 25, 50, 75, 100].map((tick) => {
                const a = Math.PI * (1 - tick / 100);
                return (
                    <line
                        key={tick}
                        x1={100 + 66 * Math.cos(a)}
                        y1={100 - 66 * Math.sin(a)}
                        x2={100 + 71 * Math.cos(a)}
                        y2={100 - 71 * Math.sin(a)}
                        stroke="var(--input)"
                        strokeWidth={1.5}
                    />
                );
            })}
            <text x={100} y={92} textAnchor="middle" fontSize={28} fontWeight={700} fill="var(--foreground)">
                {score.toFixed(1)}
            </text>
            <text x={100} y={122} textAnchor="middle" fontSize={11} fontWeight={600} fill="var(--muted-foreground)">
                {label}
            </text>
        </svg>
    );
}

export function GaugePanel({ icon, title, badge, description, value, label, color, scale }) {
    return (
        <Panel icon={icon} title={title} badge={badge} description={description}>
            <Gauge value={value} label={label} color={color} />
            <div className="flex justify-between text-xs text-muted-foreground">
                {scale.map((text) => (
                    <span key={text}>{text}</span>
                ))}
            </div>
        </Panel>
    );
}

function CriticalForce({ force }) {
    const monitoring = force.status_action === "Monitoring";
    const impact = Number(force.impact) || 0;

    return (
        <li className="flex h-full flex-col justify-between gap-3 rounded-lg border border-solid border-border bg-muted/40 p-4">
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-start justify-between gap-1.5">
                    <DimensionChip name={force.dimension || "Dimensi"} />
                    <PriorityBadge priority={force.priority} />
                </div>
                <p className="m-0 text-[13px] leading-snug font-semibold text-foreground">{force.name}</p>
            </div>

            <div className="flex flex-col gap-1.5 border-0 border-t border-dashed border-border pt-3 text-xs">
                <div className="flex justify-between gap-2">
                    <span className="text-muted-foreground">Status Aksi:</span>
                    <span className={cn("font-semibold", monitoring ? "text-warning" : "text-success")}>
                        {force.status_action_code} {force.status_action}
                    </span>
                </div>
                <div className="flex justify-between gap-2 text-muted-foreground tabular-nums">
                    <span>Impact: {force.impact}/10</span>
                    <span>Uncertainty: {force.uncertainty}/10</span>
                </div>
                <div
                    role="progressbar"
                    aria-label={`Impact ${force.name}`}
                    aria-valuemin={0}
                    aria-valuemax={10}
                    aria-valuenow={impact}
                    className="h-1.5 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className={cn("h-full rounded-full", impact >= 7 ? "bg-danger" : "bg-primary")}
                        style={{ width: `${Math.min(100, impact * 10)}%` }}
                    />
                </div>
            </div>
        </li>
    );
}

const TOP_FACTORS = 5;

const signed = new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
    signDisplay: "exceptZero",
});
const decimal = new Intl.NumberFormat("id-ID", { minimumFractionDigits: 1, maximumFractionDigits: 2 });

const EFFECT_TONES = {
    positive: { text: "text-success", bar: "bg-success" },
    negative: { text: "text-danger", bar: "bg-danger" },
    neutral: { text: "text-muted-foreground", bar: "bg-muted-foreground" },
};

/** One rule that fired for a force, with its signed contribution to the index. */
export function Factor({ factor, scale }) {
    const tone = EFFECT_TONES[factor.effect] ?? EFFECT_TONES.neutral;
    const [condition, outcome] = String(factor.rule).split(" → ");
    const width = scale > 0 && factor.contribution !== 0 ? Math.max(2, (Math.abs(factor.contribution) / scale) * 50) : 0;

    return (
        <li className="grid grid-cols-[4.5rem_minmax(0,1fr)] items-start gap-3 border-0 border-b border-solid border-border py-2.5 last:border-b-0">
            <div className="flex flex-col items-end gap-1">
                <span className={cn("text-sm font-bold tabular-nums", tone.text)}>
                    {factor.contribution === 0 ? "0" : signed.format(factor.contribution)}
                </span>
                <span aria-hidden="true" className="relative h-1.5 w-full rounded-full bg-muted">
                    <span
                        className={cn("absolute top-0 h-full rounded-full", tone.bar)}
                        style={
                            factor.contribution < 0
                                ? { right: "50%", width: `${width}%` }
                                : { left: "50%", width: `${width}%` }
                        }
                    />
                </span>
            </div>
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-[13px] font-semibold text-foreground">{factor.keyword}</span>
                <span className="text-xs text-muted-foreground">
                    Jika {condition}
                    {outcome && (
                        <>
                            {" → "}
                            <span className={cn("font-semibold", tone.text)}>{outcome}</span>
                        </>
                    )}
                </span>
                <span className="text-[11px] text-muted-foreground">
                    {[factor.dimension, factor.time_horizon, `bobot ${decimal.format(factor.weight)}`]
                        .filter(Boolean)
                        .join(" · ")}
                </span>
            </div>
        </li>
    );
}

export function FactorList({ name, explanation }) {
    const [showAll, setShowAll] = useState(false);
    const contributions = explanation?.contributions ?? [];
    const index = Number(explanation?.index ?? 50);
    const baseline = Number(explanation?.baseline ?? 50);
    const visible = showAll ? contributions : contributions.slice(0, TOP_FACTORS);
    const scale = Math.max(0, ...contributions.map((factor) => Math.abs(factor.contribution)));
    const delta = index - baseline;

    if (contributions.length === 0) {
        return (
            <p className="m-0 py-6 text-center text-sm text-muted-foreground">
                Belum ada driving force yang dihitung untuk {name}, sehingga skornya tetap di baseline{" "}
                {decimal.format(baseline)}.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            <p className="m-0 text-xs text-muted-foreground tabular-nums">
                {name} {decimal.format(index)} = baseline {decimal.format(baseline)}{" "}
                <span className="font-semibold text-foreground">{delta === 0 ? "± 0" : signed.format(delta)}</span>{" "}
                dari {contributions.length} driving force.
                {contributions.length > TOP_FACTORS && !showAll && ` ${TOP_FACTORS} faktor terbesar:`}
            </p>
            <ul aria-label={`Kontribusi ${name}`} className="m-0 list-none p-0">
                {visible.map((factor, position) => (
                    <Factor key={factor.id ?? position} factor={factor} scale={scale} />
                ))}
            </ul>
            {contributions.length > TOP_FACTORS && (
                <Button variant="ghost" size="sm" className="self-start" onClick={() => setShowAll((value) => !value)}>
                    {showAll ? `Tampilkan ${TOP_FACTORS} teratas` : `Tampilkan semua (${contributions.length})`}
                </Button>
            )}
        </div>
    );
}

/** "Mengapa skor ini": the posture rule and the per-force contributions behind each index. */
function ScoreExplanation({ explanation }) {
    if (!explanation) return null;

    return (
        <Panel
            icon={<GitBranchIcon className="text-primary" />}
            title="Mengapa skor ini?"
            badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Berbasis Aturan</span>}
            description="Setiap driving force memicu satu aturan. Kontribusinya ditambahkan ke baseline 50, ditimbang menurut time horizon."
        >
            <p className="m-0 rounded-md bg-muted/60 px-3 py-2 text-xs font-medium text-foreground">
                {explanation.posture_rule}
            </p>
            <Tabs defaultValue="resilience">
                <TabsList>
                    <TabsTrigger value="resilience">Resilience</TabsTrigger>
                    <TabsTrigger value="growth">Growth</TabsTrigger>
                </TabsList>
                <TabsContent value="resilience">
                    <FactorList name="Resilience" explanation={explanation.resilience} />
                </TabsContent>
                <TabsContent value="growth">
                    <FactorList name="Growth" explanation={explanation.growth} />
                </TabsContent>
            </Tabs>
        </Panel>
    );
}

/**
 * Executive summary from /dashboard/reasoning-data: readiness posture, growth
 * and resilience gauges, why the scores are what they are, and the top
 * critical driving forces.
 */
export default function ExecutiveOverview({ loading = false, reasoningData = null }) {
    if (loading && !reasoningData) {
        return (
            <div className="flex flex-col gap-4">
                <Skeleton className="h-20 w-full" />
                <div className="grid gap-4 lg:grid-cols-2">
                    <Skeleton className="h-64 w-full" />
                    <Skeleton className="h-64 w-full" />
                </div>
            </div>
        );
    }

    if (!reasoningData) {
        return (
            <p className="m-0 py-10 text-center text-sm text-muted-foreground">
                Data reasoning tidak tersedia untuk filter yang dipilih.
            </p>
        );
    }

    const {
        growth_index = 50,
        resilience_index = 50,
        readiness_status = "Balanced",
        readiness_badge = "Moderate",
        readiness_color = "processing",
        readiness_description = "",
        total_active_forces = 0,
        top_critical_forces = [],
        explanation = null,
    } = reasoningData;
    const tone = READINESS_TONES[readiness_color] ?? READINESS_TONES.processing;

    return (
        <div className={cn("flex flex-col gap-4", loading && "opacity-60")} aria-busy={loading || undefined}>
            <section
                aria-label="Postur kesiapan strategis"
                className={cn(
                    "flex gap-3 rounded-lg border border-solid border-border border-l-4 bg-card p-4",
                    tone.border
                )}
            >
                <CompassIcon aria-hidden="true" className={cn("mt-0.5 text-lg", tone.icon)} />
                <div className="flex flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="m-0 text-[15px] font-bold text-foreground">
                            Postur Kesiapan Strategis: {readiness_status}
                        </h3>
                        <span className={cn(statusBadge(), tone.badge)}>{readiness_badge}</span>
                    </div>
                    <p className="m-0 text-[13px] text-muted-foreground">
                        {readiness_description} (Dievaluasi dari {total_active_forces} driving force aktif).
                    </p>
                </div>
            </section>

            <div className="grid gap-4 lg:grid-cols-2">
                <GaugePanel
                    icon={<RocketIcon className="text-primary" />}
                    title="Growth Index"
                    badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Peluang Pasar</span>}
                    description="Peluang yang sudah ditangkap (Decided Plan) dibanding yang belum, pada dimensi Customer, Technology, & Economy."
                    value={growth_index}
                    label="Skor Growth"
                    color="var(--primary)"
                    scale={["0: Stagnant", "50: Baseline", "100: High Growth"]}
                />
                <GaugePanel
                    icon={<ShieldCheckIcon className="text-success" />}
                    title="Resilience Index"
                    badge={<span className={cn(statusBadge(), "bg-success-soft text-success")}>Mitigasi Risiko</span>}
                    description="Mitigasi terencana (Decided Plan) dibanding eksposur force berdampak tinggi yang masih Monitoring."
                    value={resilience_index}
                    label="Skor Resilience"
                    color="var(--success)"
                    scale={["0: Vulnerable", "50: Baseline", "100: Fully Resilient"]}
                />
            </div>

            <ScoreExplanation explanation={explanation} />

            <Panel
                icon={<SirenIcon className="text-danger" />}
                title="Top Driving Forces Kritis (Perhatian BoD)"
                badge={<span className={cn(statusBadge(), "bg-danger-soft text-danger")}>Perlu Mitigasi Segera</span>}
                description="Daftar driving force dengan dampak tertinggi yang masih dalam tahap pemantauan (Monitoring) atau memiliki urgensi Prioritas High."
            >
                {top_critical_forces.length === 0 ? (
                    <p className="m-0 py-6 text-center text-sm text-muted-foreground">
                        Tidak ada driving force berisiko tinggi yang membutuhkan mitigasi darurat saat ini.
                    </p>
                ) : (
                    <ul className="m-0 grid list-none gap-3 p-0 md:grid-cols-3">
                        {top_critical_forces.map((force, index) => (
                            <CriticalForce key={force.id || index} force={force} />
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}
