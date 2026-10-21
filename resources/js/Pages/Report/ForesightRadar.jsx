import { Head } from "@inertiajs/react";
import { RadarIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { filterParam, useAnalysisFilters } from "@/Components/AnalysisFilters";
import Radar from "@/Components/Radar";
import { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";

export default function ForesightRadar({ auth, dimensions = [], title }) {
    const filters = useAnalysisFilters(dimensions);

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <RadarIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        360° Strategic Horizon
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar filters={filters} dimensions={dimensions} meta="Horizon 1 / 2 / 3" />
                <Radar
                    date={{ date: filters.period }}
                    selectData={{ dimension: filterParam(filters.dimension) ?? null }}
                />
            </main>
        </AuthenticatedLayout>
    );
}
