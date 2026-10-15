import { useId, useState } from "react";
import dayjs from "dayjs";
import { RotateCwIcon } from "lucide-react";

import DateRangePicker from "@/Components/DateRangePicker";
import FilterBar, { FilterField } from "@/Components/FilterBar";
import { Button } from "@/Components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { cn } from "@/lib/utils";

const ALL = "all";

export function currentMonth() {
    return [dayjs().startOf("month").format("YYYY-MM-DD"), dayjs().endOf("month").format("YYYY-MM-DD")];
}

/**
 * Period + dimension filters shared by the analysis tables. `params` is the
 * query the `*.fetch-data` endpoints read (`date[]`, `dimension`).
 */
export function useAnalysisFilters(dimensions = []) {
    const [period, setPeriod] = useState(currentMonth);
    const [dimension, setDimension] = useState(ALL);

    return {
        period,
        setPeriod,
        dimension,
        setDimension,
        activeDimension: dimensions.find((item) => String(item.value) === dimension) ?? null,
        params: { date: period, dimension: filterParam(dimension) },
        isFiltered: dimension !== ALL || period.join() !== currentMonth().join(),
        reset: () => {
            setPeriod(currentMonth());
            setDimension(ALL);
        },
    };
}

/** Query value for a FilterSelect: the "all" choice sends nothing. */
export function filterParam(value) {
    return value === ALL ? undefined : value;
}

/**
 * Labelled Select for a FilterBar with an "all" first choice (`allLabel`).
 * `value` is "all" or an option value as a string; options are `{ value, label }`.
 */
export function FilterSelect({ label, allLabel, value, onChange, options = [], className = "sm:w-[210px]" }) {
    const id = useId();

    return (
        <FilterField label={label} htmlFor={id} className="w-full sm:w-auto">
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className={cn("w-full border-input bg-card", className)}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent position="popper">
                    <SelectItem value={ALL}>{allLabel}</SelectItem>
                    {options.map((item) => (
                        <SelectItem key={item.value} value={String(item.value)}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FilterField>
    );
}

/**
 * FilterBar with Periode, Dimensi and a Reset Filter button. Pass `isFiltered`
 * and `onReset` when the page has filters of its own (e.g. a search box);
 * extra fields (FilterSelect) go in `children`.
 */
export default function AnalysisFilterBar({
    filters,
    dimensions = [],
    isFiltered = filters.isFiltered,
    onReset = filters.reset,
    meta,
    children,
}) {
    return (
        <FilterBar
            label="Filter analisis"
            meta={meta}
            actions={
                isFiltered && (
                    <Button variant="ghost" size="sm" onClick={onReset} className="text-destructive hover:text-destructive">
                        <RotateCwIcon aria-hidden="true" />
                        Reset Filter
                    </Button>
                )
            }
        >
            <FilterField label="Periode" className="w-full sm:w-auto">
                <DateRangePicker value={filters.period} onChange={filters.setPeriod} className="w-full sm:w-[260px]" />
            </FilterField>
            <FilterSelect
                label="Dimensi"
                allLabel="Semua Dimensi"
                value={filters.dimension}
                onChange={filters.setDimension}
                options={dimensions}
            />
            {children}
        </FilterBar>
    );
}
