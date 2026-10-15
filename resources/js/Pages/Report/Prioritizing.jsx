import { Head } from "@inertiajs/react";
import { ChartScatterIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { filterParam, useAnalysisFilters } from "@/Components/AnalysisFilters";
import PrioritizingChart from "@/Components/PrioritizingChart";
import { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";

export default function Prioritizing({ auth, dimensions = [], title }) {
    const filters = useAnalysisFilters(dimensions);

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ChartScatterIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Priority Matrix
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar filters={filters} dimensions={dimensions} />
                <PrioritizingChart
                    date={{ date: filters.period }}
                    selectData={{ dimension: filterParam(filters.dimension) ?? null }}
                    dimensions={dimensions}
                />
            </main>
        </AuthenticatedLayout>
    );
}
