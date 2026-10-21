import { useId, useState } from "react";
import { Head } from "@inertiajs/react";
import { ListIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AnalysisFilterBar, { FilterSelect, filterParam, useAnalysisFilters } from "@/Components/AnalysisFilters";
import OverallStatus from "@/Components/OverallStatus";
import { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";

const ALL_EXTRA = { time_horizon: "all", priority: "all", status_action: "all" };

export default function RegisteredList({ auth, dimensions = [], title, priorities = [], status_actions = [], time_horizons = [] }) {
    const listHeadingId = useId();

    const filters = useAnalysisFilters(dimensions);
    const [extra, setExtra] = useState(ALL_EXTRA);
    const setExtraField = (field) => (value) => setExtra((prev) => ({ ...prev, [field]: value }));

    const isFiltered = filters.isFiltered || Object.values(extra).some((value) => value !== "all");

    const handleResetFilter = () => {
        filters.reset();
        setExtra(ALL_EXTRA);
    };

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ListIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Register &amp; Audit Log
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <AnalysisFilterBar
                    filters={filters}
                    dimensions={dimensions}
                    isFiltered={isFiltered}
                    onReset={handleResetFilter}
                >
                    <FilterSelect
                        label="Horizon"
                        allLabel="Semua Horizon"
                        value={extra.time_horizon}
                        onChange={setExtraField("time_horizon")}
                        options={time_horizons}
                        className="sm:w-[160px]"
                    />
                    <FilterSelect
                        label="Prioritas"
                        allLabel="Semua Prioritas"
                        value={extra.priority}
                        onChange={setExtraField("priority")}
                        options={priorities}
                        className="sm:w-[160px]"
                    />
                    <FilterSelect
                        label="Status"
                        allLabel="Semua Status"
                        value={extra.status_action}
                        onChange={setExtraField("status_action")}
                        options={status_actions}
                        className="sm:w-[160px]"
                    />
                </AnalysisFilterBar>

                <section aria-labelledby={listHeadingId} className="flex flex-col gap-3">
                    <h2 id={listHeadingId} className="m-0 text-base font-semibold text-foreground">
                        Status per Dimensi
                    </h2>
                    <OverallStatus
                        permissions={auth?.permissions ?? []}
                        date={{ date: filters.period }}
                        selectedData={{
                            dimension: filterParam(filters.dimension),
                            time_horizon: filterParam(extra.time_horizon),
                            priority: filterParam(extra.priority),
                            status_action: filterParam(extra.status_action),
                        }}
                    />
                </section>
            </main>
        </AuthenticatedLayout>
    );
}
