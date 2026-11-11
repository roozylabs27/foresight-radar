import { Head } from "@inertiajs/react";
import { RadarIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { filterParam, useAnalysisFilters } from "@/Components/AnalysisFilters";
import Radar from "@/Components/Radar";
import { statusBadge } from "@/Components/StatusBadge";
import { FIRM } from "@/domain/reportScope";
import { cn } from "@/lib/utils";

/** Foresight Radar of the whole firm (default) or of one Key Focal Issue (`scopes`, `scope`). */
export default function ForesightRadar({ auth, dimensions = [], title, scopes = [], scope = FIRM }) {
    const filters = useAnalysisFilters(dimensions, { scopes, scope });

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
                    <span className={cn(statusBadge(), "hidden truncate sm:inline-flex")}>{filters.scopeLabel}</span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar filters={filters} dimensions={dimensions} meta="Horizon 1 / 2 / 3" />
                <Radar
                    dimensions={dimensions}
                    date={{ date: filters.period }}
                    selectData={{ dimension: filterParam(filters.dimension) ?? null, scope: filters.params.scope ?? null }}
                />
            </main>
        </AuthenticatedLayout>
    );
}
