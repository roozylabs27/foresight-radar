import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import { AlertCircleIcon, RotateCcwIcon, SlidersHorizontalIcon, SparklesIcon } from "lucide-react";
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
    }, [overrides, params]);

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
            {/* Simulation Warning Banner */}
            <div className="flex items-center gap-3 rounded-lg border border-amber-500/20 bg-amber-50/50 p-3.5 text-sm text-amber-900 dark:bg-amber-950/20 dark:text-amber-200">
                <AlertCircleIcon className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div className="flex-1">
                    <span className="font-semibold">Simulasi What-if (Hanya Baca):</span> Simulasi tidak menyimpan perubahan ke database. Efek propagasi lintas-force belum dihitung.
                </div>
                {activeOverrideCount > 0 && (
                    <Button variant="ghost" size="sm" onClick={handleResetAll} className="h-8 gap-1.5 text-xs">
                        <RotateCcwIcon className="size-3.5" />
                        Reset Semua ({activeOverrideCount})
                    </Button>
                )}
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
                                        <SelectTrigger className="h-8 w-32 text-xs">
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
                                            <SelectTrigger className="h-8 w-16 text-xs">
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
                                            <SelectTrigger className="h-8 w-16 text-xs">
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
