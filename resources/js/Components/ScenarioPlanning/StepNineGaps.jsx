import { AlertCircleIcon } from "lucide-react";

/**
 * PI-09: what still keeps step 9 open (from EarlyWarningIndicatorService::stepNineGaps, the same rule
 * the stepper uses). Shows nothing once step 9 is done.
 */
export default function StepNineGaps({ gaps = [] }) {
    if (gaps.length === 0) return null;

    return (
        <div role="status" className="flex items-start gap-2 rounded-md bg-warning-soft p-3 text-sm text-warning">
            <AlertCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
            <div className="flex flex-col gap-1">
                <p className="m-0 font-medium">Langkah 9 belum selesai:</p>
                <ul className="m-0 list-disc pl-4">
                    {gaps.map((gap) => (
                        <li key={gap}>{gap}</li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
