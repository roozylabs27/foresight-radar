import { useId } from "react";
import { Controller } from "react-hook-form";
import { z } from "zod";

import DetailList, { DetailItem } from "@/Components/DetailList";
import DimensionChip from "@/Components/DimensionChip";
import FormField, { fieldControlProps } from "@/Components/FormField";
import PriorityBadge from "@/Components/PriorityBadge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { toFieldValue, useDialogForm } from "@/hooks/use-dialog-form";

const schema = z.object({
    status_action_id: z.string().min(1, "Please select the status action !"),
    reason: z.string().trim().min(1, "Please input the reason!").max(1000, "The reason may not be greater than 1000 characters."),
});

// StatusActionResource sends each reason as "Status, reason - Y-m-d H:i:s".
function parseReason(text = "") {
    const match = text.match(/^(.*?), ([\s\S]*) - (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})$/);
    return match ? { status: match[1], reason: match[2], date: match[3] } : { status: null, reason: text, date: null };
}

/** Status history, newest first, ending in the entry being recorded. */
function ReasonTimeline({ reasons = [] }) {
    const entries = reasons.map((item) => parseReason(item.children)).reverse();

    return (
        <ol className="m-0 flex list-none flex-col p-0">
            <li className="relative flex gap-3 pb-5">
                <span aria-hidden="true" className="absolute top-4 bottom-0 left-[5px] w-px bg-border" />
                <span aria-hidden="true" className="mt-1 size-[11px] shrink-0 rounded-full border-2 border-solid border-primary bg-card motion-safe:animate-pulse" />
                <span className="text-sm text-muted-foreground italic">Recording...</span>
            </li>
            {entries.map((entry, index) => (
                <li key={index} className="relative flex gap-3 pb-5 last:pb-0">
                    {index < entries.length - 1 && (
                        <span aria-hidden="true" className="absolute top-4 bottom-0 left-[5px] w-px bg-border" />
                    )}
                    <span aria-hidden="true" className="mt-1 size-[11px] shrink-0 rounded-full bg-primary" />
                    <div className="flex min-w-0 flex-col gap-0.5">
                        {entry.status && <span className="text-sm font-semibold text-foreground">{entry.status}</span>}
                        <span className="text-sm break-words text-foreground">{entry.reason}</span>
                        {entry.date && (
                            <time dateTime={entry.date.replace(" ", "T")} className="font-mono text-xs text-muted-foreground">
                                {entry.date}
                            </time>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

/**
 * Records a status action and its reason for one driving force, next to the
 * history of earlier ones. Render it inside <FormDialog formId={id}>.
 * `onSubmit(values)` may resolve to the 422 `errors` bag.
 */
export default function FormStatusAction({ id, initialValues = {}, status_actions = [], loading, onSubmit }) {
    const idPrefix = useId();
    const ids = { status_action_id: `${idPrefix}-status`, reason: `${idPrefix}-reason` };
    const historyHeadingId = `${idPrefix}-history`;

    // A new entry each time: the previous status and reason are not prefilled.
    const { control, submit } = useDialogForm({
        schema,
        defaultValues: { status_action_id: "", reason: "" },
        ids,
        loading,
        onSubmit,
    });

    return (
        <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            <section aria-labelledby={historyHeadingId} className="order-last flex min-w-0 flex-col gap-3 md:order-first">
                <h3 id={historyHeadingId} className="m-0 text-sm font-semibold text-foreground">
                    Riwayat status
                </h3>
                <div className="max-h-[420px] overflow-y-auto rounded-lg border border-solid border-border p-4">
                    <ReasonTimeline reasons={initialValues.reasons} />
                </div>
            </section>

            <form id={id} noValidate onSubmit={submit} className="min-w-0">
                <fieldset disabled={loading} className="m-0 flex min-w-0 flex-col gap-5 border-0 p-0">
                    <DetailList className="sm:grid-cols-2">
                        <DetailItem label="Keyword">
                            <span className="font-medium">{initialValues.keyword || "-"}</span>
                        </DetailItem>
                        <DetailItem label="Dimension">
                            <DimensionChip name={initialValues.dimension} />
                        </DetailItem>
                        <DetailItem label="Description" className="sm:col-span-2">
                            {initialValues.description || "-"}
                        </DetailItem>
                        <DetailItem label="Time Horizon">{initialValues.time_horizon || "-"}</DetailItem>
                        <DetailItem label="Priority">
                            <PriorityBadge priority={initialValues.priority} />
                        </DetailItem>
                        <DetailItem label="Uncertainty Analysis">
                            <span className="tabular-nums">{initialValues.uncertainty_analysis ?? "-"}</span>
                        </DetailItem>
                        <DetailItem label="Impact Analysis">
                            <span className="tabular-nums">{initialValues.impact_analysis ?? "-"}</span>
                        </DetailItem>
                    </DetailList>

                    <Controller
                        name="status_action_id"
                        control={control}
                        render={({ field, fieldState }) => (
                            <FormField id={ids.status_action_id} label="Status Action" error={fieldState.error}>
                                <Select
                                    name={field.name}
                                    value={field.value}
                                    onValueChange={field.onChange}
                                    onOpenChange={(open) => !open && field.onBlur()}
                                >
                                    <SelectTrigger {...fieldControlProps(ids.status_action_id, fieldState)} className="w-full">
                                        <SelectValue placeholder="Select a status action" />
                                    </SelectTrigger>
                                    <SelectContent position="popper">
                                        {status_actions.map((action) => (
                                            <SelectItem key={action.value} value={toFieldValue(action.value)}>
                                                {action.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        )}
                    />

                    <Controller
                        name="reason"
                        control={control}
                        render={({ field, fieldState }) => (
                            <FormField id={ids.reason} label="Reason" error={fieldState.error}>
                                <Textarea
                                    {...fieldControlProps(ids.reason, fieldState)}
                                    name={field.name}
                                    value={field.value}
                                    onChange={field.onChange}
                                    onBlur={field.onBlur}
                                    placeholder="Enter a reason"
                                    rows={3}
                                    maxLength={1000}
                                    className="max-h-40 min-h-20"
                                />
                            </FormField>
                        )}
                    />
                </fieldset>
            </form>
        </div>
    );
}
