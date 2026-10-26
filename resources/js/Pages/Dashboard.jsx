import { lazy, Suspense, useEffect, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import qs from "qs";
import { toast } from "sonner";
import { GaugeIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { filterParam, useAnalysisFilters } from "@/Components/AnalysisFilters";
import EdaCharts from "@/Components/EdaCharts";
import ExecutiveOverview from "@/Components/ExecutiveOverview";
import HistoryCharts from "@/Components/HistoryCharts";
import ScenarioSimulation from "@/Components/ScenarioSimulation";
import { statusBadge } from "@/Components/StatusBadge";
import { Skeleton } from "@/Components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { cn } from "@/lib/utils";

const OverallStatus = lazy(() => import("@/Components/OverallStatus"));
const PrioritizingChart = lazy(() => import("@/Components/PrioritizingChart"));
const Radar = lazy(() => import("@/Components/Radar"));

const TABS = [
    { value: "overview", label: "Overview" },
    { value: "simulation", label: "Simulasi What-if" },
    { value: "prioritizing", label: "Prioritizing" },
    { value: "radar", label: "Foresight Radar" },
    { value: "registered", label: "Registered List" },
];

/** Executive reasoning metrics (/dashboard/reasoning-data) for the current filters. */
function useReasoningData(params) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const queryKey = qs.stringify(params);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get(`/dashboard/reasoning-data?${queryKey}`, { signal: controller.signal })
            .then((response) => {
                setData(response.data);
                setLoading(false);
            })
            .catch((error) => {
                if (axios.isCancel(error)) return;
                toast.error("Gagal memuat Overview.", {
                    description: error.response?.data?.message ?? error.message,
                });
                setLoading(false);
            });
        return () => controller.abort();
    }, [queryKey]);

    return { data, loading };
}

export default function Dashboard({ auth, dimensions = [] }) {
    const filters = useAnalysisFilters(dimensions);
    const [tab, setTab] = useState("overview");

    const dimension = filterParam(filters.dimension) ?? null;
    const date = { date: filters.period };
    const selectData = { dimension };
    const reasoning = useReasoningData(filters.params);

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <GaugeIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Dashboard</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Overview
                    </span>
                </div>
            }
        >
            <Head title="Dashboard" />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar filters={filters} dimensions={dimensions} />

                {/* Inactive tabs unmount, so only the open view fetches its data. */}
                <Tabs value={tab} onValueChange={setTab} className="gap-4">
                    <TabsList className="h-auto w-full flex-wrap justify-start sm:w-fit">
                        {TABS.map((item) => (
                            <TabsTrigger key={item.value} value={item.value} className="px-3 py-1.5">
                                {item.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="overview" className="flex flex-col gap-4">
                        <ExecutiveOverview loading={reasoning.loading} reasoningData={reasoning.data} />
                        <EdaCharts
                            loading={reasoning.loading}
                            edaFrequency={reasoning.data?.eda_frequency ?? []}
                            edaTrend={reasoning.data?.eda_trend ?? []}
                            edaTrendRange={reasoning.data?.eda_trend_range ?? null}
                            edaDistribution={reasoning.data?.eda_distribution ?? null}
                            edaRelationship={reasoning.data?.eda_relationship ?? null}
                            edaComparison={reasoning.data?.eda_comparison ?? null}
                        />
                        <HistoryCharts
                            loading={reasoning.loading}
                            historyTrend={reasoning.data?.history_trend ?? null}
                            transitionVelocity={reasoning.data?.transition_velocity ?? null}
                        />
                    </TabsContent>
                    <TabsContent value="simulation">
                        <ScenarioSimulation loading={reasoning.loading} reasoningData={reasoning.data} params={filters.params} />
                    </TabsContent>
                    <TabsContent value="prioritizing">
                        <Suspense fallback={<Skeleton className="h-[420px] w-full rounded-xl" />}>
                            <PrioritizingChart date={date} selectData={selectData} dimensions={dimensions} />
                        </Suspense>
                    </TabsContent>
                    <TabsContent value="radar">
                        <Suspense fallback={<Skeleton className="h-[420px] w-full rounded-xl" />}>
                            <Radar date={date} selectData={selectData} />
                        </Suspense>
                    </TabsContent>
                    <TabsContent value="registered">
                        <Suspense fallback={<Skeleton className="h-[420px] w-full rounded-xl" />}>
                            <OverallStatus date={date} selectedData={selectData} permissions={auth?.permissions ?? []} />
                        </Suspense>
                    </TabsContent>
                </Tabs>
            </main>
        </AuthenticatedLayout>
    );
}
