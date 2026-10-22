import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import { AlertCircleIcon, ArrowRightIcon, GitForkIcon, RotateCcwIcon, SlidersHorizontalIcon, SparklesIcon } from "lucide-react";
import { toast } from "sonner";

import Panel from "@/Components/Panel";
import { Button } from "@/Components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Skeleton } from "@/Components/ui/skeleton";
import { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";
import { Factor, Gauge } from "@/Components/ExecutiveOverview";

const READINESS_TONES = {
    green: "border-emerald-500/20 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300",
    blue: "border-sky-500/20 bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300",
    amber: "border-amber-500/20 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300",
    red: "border-rose-500/20 bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300",
};

export default function ScenarioSimulation({ loading, reasoningData, params }) {
    const [overrides, setOverrides] = useState({});
    const [propagate, setPropagate] = useState(false);
    const [simulationResult, setSimulationResult] = useState(null);
    const [simulating, setSimulating] = useState(false);

    // Forces available from reasoningData contributions
    const forces = useMemo(() => {
        if (!reasoningData?.explanation?.resilience?.contributions) return [];
        const seen = new Set();
        const list = [];
        for (const item of reasoningData.explanation.resilience.contributions) {
            if (item.id && !seen.has(item.id)) {
                seen.add(item.id);
                list.push(item);
            }
        }
        return list;
    }, [reasoningData]);

    const activeOverrideCount = Object.keys(overrides).length;

    // Reset all overrides
    const handleResetAll = () => {
        setOverrides({});
        setSimulationResult(null);
    };

    const handleUpdateOverride = (forceId, field, value) => {
        setOverrides((prev) => {
            const current = { ...(prev[forceId] || {}) };
            current[field] = value;
            return {
                ...prev,
                [forceId]: current,
            };
        });
    };

    const handleResetForce = (forceId) => {
        setOverrides((prev) => {
            const next = { ...prev };
            delete next[forceId];
            return next;
        });
    };

    // Debounced POST to /dashboard/simulate
    useEffect(() => {
        if (activeOverrideCount === 0) {
            setSimulationResult(null);
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setSimulating(true);
            const payload = {
                date: params?.date ?? null,
                dimension: params?.dimension ?? null,
                propagate,
                overrides: Object.entries(overrides).map(([forceId, changes]) => ({
                    force: forceId,
                    ...changes,
                })),
            };

            axios
                .post("/dashboard/simulate", payload, { signal: controller.signal })
                .then((res) => {
                    setSimulationResult(res.data);
                    setSimulating(false);
                })
                .catch((err) => {
                    if (axios.isCancel(err)) return;
                    setSimulating(false);
                    toast.error("Gagal menjalankan simulasi", {
                        description: err.response?.data?.message || err.message,
                    });
                });
        }, 400);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [overrides, params, propagate]);

    if (loading) {
        return (
            <div className="flex flex-col gap-4">
                <Skeleton className="h-28 w-full" />
                <Skeleton className="h-64 w-full" />
            </div>
        );
    }

    const baseline = simulationResult?.baseline || {
        growth_index: reasoningData?.growth_index ?? 50,
        resilience_index: reasoningData?.resilience_index ?? 50,
        readiness_status: reasoningData?.readiness_status ?? "Defensive Prepared",
        readiness_badge: reasoningData?.readiness_badge ?? "Defensive",
        readiness_color: reasoningData?.readiness_color ?? "blue",
    };

    const displaySim = simulationResult?.simulation || baseline;
    const deltas = simulationResult?.deltas || { resilience: 0, growth: 0 };

    return (
        <div className="flex flex-col gap-4">
            {/* Simulation Control & Warning Banner */}
            <div className={cn(
                "flex flex-col gap-3 rounded-lg border p-3.5 text-sm sm:flex-row sm:items-center",
                propagate
                    ? "border-indigo-500/30 bg-indigo-50/60 text-indigo-950 dark:bg-indigo-950/30 dark:text-indigo-200"
                    : "border-amber-500/20 bg-amber-50/50 text-amber-900 dark:bg-amber-950/20 dark:text-amber-200"
            )}>
                {propagate ? (
                    <GitForkIcon className="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                ) : (
                    <AlertCircleIcon className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                )}
                <div className="flex-1">
                    <span className="font-semibold">
                        {propagate ? "Simulasi Sistemik (Propagasi Aktif):" : "Simulasi What-if (Efek Langsung):"}
                    </span>{" "}
                    {propagate
                        ? "Perubahan status dan nilai merambat ke driving force yang terhubung melalui matriks cross-impact."
                        : "Simulasi tidak menyimpan perubahan ke database. Efek perambatan lintas-force belum dihitung."}
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant={propagate ? "default" : "outline"}
                        size="sm"
                        onClick={() => setPropagate((prev) => !prev)}
                        className={cn(
                            "h-8 gap-1.5 text-xs",
                            propagate && "bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                        )}
                    >
                        <GitForkIcon className="size-3.5" />
                        {propagate ? "Propagasi Aktif" : "Aktifkan Propagasi"}
                    </Button>
                    {activeOverrideCount > 0 && (
                        <Button variant="ghost" size="sm" onClick={handleResetAll} className="h-8 gap-1.5 text-xs">
                            <RotateCcwIcon className="size-3.5" />
                            Reset Semua ({activeOverrideCount})
                        </Button>
                    )}
                </div>
            </div>

            {/* Comparison Metrics Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {/* Resilience Gauge */}
                <Panel
                    icon={<SparklesIcon className="size-4 text-emerald-500" />}
                    title="Resilience Index (Simulasi)"
                    badge={
                        deltas.resilience !== 0 ? (
                            <span className={cn(
                                "rounded px-1.5 py-0.5 text-xs font-semibold",
                                deltas.resilience > 0 ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" : "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                            )}>
                                {deltas.resilience > 0 ? `+${deltas.resilience}` : deltas.resilience}
                            </span>
                        ) : null
                    }
                    description={`Baseline: ${baseline.resilience_index} → Simulasi: ${displaySim.resilience_index}`}
                >
                    <div className="flex flex-col items-center">
                        <Gauge value={displaySim.resilience_index} label="Resilience" color="#10b981" />
                    </div>
                </Panel>

                {/* Growth Gauge */}
                <Panel
                    icon={<SparklesIcon className="size-4 text-sky-500" />}
                    title="Growth Index (Simulasi)"
                    badge={
                        deltas.growth !== 0 ? (
                            <span className={cn(
                                "rounded px-1.5 py-0.5 text-xs font-semibold",
                                deltas.growth > 0 ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" : "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                            )}>
                                {deltas.growth > 0 ? `+${deltas.growth}` : deltas.growth}
                            </span>
                        ) : null
                    }
                    description={`Baseline: ${baseline.growth_index} → Simulasi: ${displaySim.growth_index}`}
                >
                    <div className="flex flex-col items-center">
                        <Gauge value={displaySim.growth_index} label="Growth" color="#0284c7" />
                    </div>
                </Panel>

                {/* Posture Transition */}
                <Panel
                    icon={<SlidersHorizontalIcon className="size-4 text-primary" />}
                    title="Strategic Posture"
                    description="Perubahan kuadran postur strategis"
                >
                    <div className="flex flex-col items-center justify-center gap-2 py-4">
                        <span className={cn(statusBadge(), READINESS_TONES[displaySim.readiness_color] ?? READINESS_TONES.blue, "text-sm")}>
                            {displaySim.readiness_status}
                        </span>
                        {simulationResult?.posture_changed && (
                            <span className="text-xs text-muted-foreground">
                                Berubah dari: {baseline.readiness_status}
                            </span>
                        )}
                    </div>
                </Panel>
            </div>

            {/* Cross-Impact Propagation Ripple Trace Panel */}
            {propagate && simulationResult?.propagations && (
                <Panel
                    icon={<GitForkIcon className="size-4 text-indigo-500" />}
                    title={`Efek Riak Lintas-Force (${simulationResult.propagations.length} Relasi Terpengaruh)`}
                    description="Perambatan dampak ke driving force terkait yang dipicu oleh perubahan status/skor melalui matriks cross-impact"
                >
                    {simulationResult.propagations.length === 0 ? (
                        <div className="py-4 text-center text-xs text-muted-foreground">
                            Tidak ada relasi cross-impact aktif yang terhubung dengan driving force yang disesuaikan saat ini.
                        </div>
                    ) : (
                        <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                            {simulationResult.propagations.map((p, idx) => (
                                <div key={idx} className="flex flex-col gap-1.5 rounded-md border border-border/60 bg-muted/30 p-2.5 text-xs">
                                    <div className="flex items-center justify-between font-medium">
                                        <span className="truncate text-foreground/90">{p.source_keyword}</span>
                                        <span className={cn(
                                            "rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold",
                                            p.score > 0
                                                ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                                : "bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                                        )}>
                                            {p.score > 0 ? `+${p.score}` : p.score}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-muted-foreground">
                                        <ArrowRightIcon className="size-3 shrink-0" />
                                        <span className="truncate font-semibold text-foreground/80">{p.target_keyword}</span>
                                    </div>
                                    <div className="mt-1 flex items-center gap-2 border-t border-border/40 pt-1.5 text-[11px] text-muted-foreground">
                                        <span>
                                            Δ Impact:{" "}
                                            <strong className={p.induced_delta_impact > 0 ? "text-emerald-600" : (p.induced_delta_impact < 0 ? "text-rose-600" : "")}>
                                                {p.induced_delta_impact > 0 ? `+${p.induced_delta_impact}` : p.induced_delta_impact}
                                            </strong>
                                        </span>
                                        <span>•</span>
                                        <span>
                                            Δ Uncertainty:{" "}
                                            <strong className={p.induced_delta_uncertainty < 0 ? "text-emerald-600" : (p.induced_delta_uncertainty > 0 ? "text-rose-600" : "")}>
                                                {p.induced_delta_uncertainty > 0 ? `+${p.induced_delta_uncertainty}` : p.induced_delta_uncertainty}
                                            </strong>
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>
            )}

            {/* Forces Controls List */}
            <Panel
                title="Daftar Driving Force untuk Simulasi"
                description="Ubah Impact, Uncertainty, atau Status Action untuk melihat dampaknya pada indeks kepemimpinan"
            >
                <div className="flex flex-col divide-y divide-border">
                    {forces.map((force) => {
                        const override = overrides[force.id] || {};
                        const isOverridden = Boolean(overrides[force.id]);
                        const currentImpact = override.impact ?? force.impact;
                        const currentUncertainty = override.uncertainty ?? force.uncertainty;
                        const currentStatus = override.status_action ?? (force.status_action?.toLowerCase().includes("decided") ? "decided_plan" : "monitoring");

                        return (
                            <div key={force.id} className="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-foreground">{force.keyword}</span>
                                        <span className="text-xs text-muted-foreground">({force.dimension})</span>
                                        {isOverridden && (
                                            <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                                                Disesuaikan
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>Horizon: {force.time_horizon}</span>
                                        <span>•</span>
                                        <span>Bobot: {force.weight}</span>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    {/* Action Toggle */}
                                    <Select
                                        value={currentStatus}
                                        onValueChange={(val) => handleUpdateOverride(force.id, "status_action", val)}
                                    >
                                        <SelectTrigger className="h-8 w-32 text-xs" aria-label={`Status aksi ${force.keyword}`}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="monitoring">Monitoring</SelectItem>
                                            <SelectItem value="decided_plan">Decided Plan</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    {/* Impact Select */}
                                    <div className="flex items-center gap-1">
                                        <span className="text-xs text-muted-foreground">Impact:</span>
                                        <Select
                                            value={String(currentImpact)}
                                            onValueChange={(val) => handleUpdateOverride(force.id, "impact", Number(val))}
                                        >
                                            <SelectTrigger className="h-8 w-16 text-xs" aria-label={`Impact ${force.keyword}`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((num) => (
                                                    <SelectItem key={num} value={String(num)}>
                                                        {num}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* Uncertainty Select */}
                                    <div className="flex items-center gap-1">
                                        <span className="text-xs text-muted-foreground">Uncertainty:</span>
                                        <Select
                                            value={String(currentUncertainty)}
                                            onValueChange={(val) => handleUpdateOverride(force.id, "uncertainty", Number(val))}
                                        >
                                            <SelectTrigger className="h-8 w-16 text-xs" aria-label={`Uncertainty ${force.keyword}`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((num) => (
                                                    <SelectItem key={num} value={String(num)}>
                                                        {num}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* Reset Button */}
                                    {isOverridden && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-muted-foreground hover:text-foreground"
                                            onClick={() => handleResetForce(force.id)}
                                            title="Reset ke baseline"
                                            aria-label={`Reset ${force.keyword} ke baseline`}
                                        >
                                            <RotateCcwIcon className="size-3.5" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </Panel>
        </div>
    );
}
