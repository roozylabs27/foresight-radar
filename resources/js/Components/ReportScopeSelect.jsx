import { useId } from "react";

import { FilterField } from "@/Components/FilterBar";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { FIRM, scopeHint } from "@/domain/reportScope";

/**
 * Which data a firm report shows: the whole firm (default) or one Key Focal Issue. `scopes` are the
 * exercises `{ id, name, target_year, scores_confirmed }`; `value` is "firm" or an exercise id.
 */
export default function ReportScopeSelect({ value, onChange, scopes = [] }) {
    const id = useId();

    return (
        <FilterField label="Cakupan" htmlFor={id} className="w-full sm:w-auto">
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full border-input bg-card sm:w-[260px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent position="popper">
                    <SelectItem value={FIRM}>FIRM · seluruh organisasi</SelectItem>
                    {scopes.map((exercise) => {
                        const hint = scopeHint(exercise);
                        return (
                            <SelectItem key={exercise.id} value={exercise.id}>
                                {exercise.name}
                                {exercise.target_year ? ` (${exercise.target_year})` : ""}
                                {hint && <span className="text-muted-foreground"> · {hint}</span>}
                            </SelectItem>
                        );
                    })}
                </SelectContent>
            </Select>
        </FilterField>
    );
}
