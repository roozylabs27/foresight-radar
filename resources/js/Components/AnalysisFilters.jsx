import { useId, useState } from "react";
import dayjs from "dayjs";
import { ReloadOutlined } from "@ant-design/icons";

import DateRangePicker from "@/Components/DateRangePicker";
import FilterBar, { FilterField } from "@/Components/FilterBar";
import { Button } from "@/Components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";

const ALL_DIMENSIONS = "all";

export function currentMonth() {
    return [dayjs().startOf("month").format("YYYY-MM-DD"), dayjs().endOf("month").format("YYYY-MM-DD")];
}

/**
 * Period + dimension filters shared by the analysis tables. `params` is the
 * query the `*.fetch-data` endpoints read (`date[]`, `dimension`).
 */
export function useAnalysisFilters(dimensions = []) {
    const [period, setPeriod] = useState(currentMonth);
    const [dimension, setDimension] = useState(ALL_DIMENSIONS);

    return {
        period,
        setPeriod,
        dimension,
        setDimension,
        activeDimension: dimensions.find((item) => String(item.value) === dimension) ?? null,
        params: { date: period, dimension: dimension === ALL_DIMENSIONS ? undefined : dimension },
        isFiltered: dimension !== ALL_DIMENSIONS || period.join() !== currentMonth().join(),
        reset: () => {
            setPeriod(currentMonth());
            setDimension(ALL_DIMENSIONS);
        },
    };
}

/**
 * FilterBar with Periode, Dimensi and a Reset Filter button. Pass `isFiltered`
 * and `onReset` when the page has filters of its own (e.g. a search box).
 */
export default function AnalysisFilterBar({
    filters,
    dimensions = [],
    isFiltered = filters.isFiltered,
    onReset = filters.reset,
    children,
}) {
    const dimensionFieldId = useId();

    return (
        <FilterBar
            label="Filter analisis"
            actions={
                isFiltered && (
                    <Button variant="ghost" size="sm" onClick={onReset} className="text-destructive hover:text-destructive">
                        <ReloadOutlined aria-hidden="true" />
                        Reset Filter
                    </Button>
                )
            }
        >
            <FilterField label="Periode" className="w-full sm:w-auto">
                <DateRangePicker value={filters.period} onChange={filters.setPeriod} className="w-full sm:w-[260px]" />
            </FilterField>
            <FilterField label="Dimensi" htmlFor={dimensionFieldId} className="w-full sm:w-auto">
                <Select value={filters.dimension} onValueChange={filters.setDimension}>
                    <SelectTrigger id={dimensionFieldId} className="h-[38px] w-full border-input bg-card sm:w-[210px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        <SelectItem value={ALL_DIMENSIONS}>Semua Dimensi</SelectItem>
                        {dimensions.map((item) => (
                            <SelectItem key={item.value} value={String(item.value)}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>
            {children}
        </FilterBar>
    );
}
