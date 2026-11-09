import { AlertCircleIcon } from "lucide-react";

import Panel from "@/Components/Panel";

/**
 * Distinctness check for step 5 (roadmap R2.5): conditions in different quadrants that share most
 * of their words. Futures that say the same thing do not help the team tell them apart.
 */
export default function OverlapWarnings({ overlaps }) {
    return (
        <Panel title={`Kondisi yang mirip (${overlaps.length})`} description="Kondisi di kuadran berbeda yang sebagian besar katanya sama. Bedakan atau pindahkan ke elemen predetermined.">
            {overlaps.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground">Tidak ada kondisi yang mirip antar kuadran.</p>
            ) : (
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                    {overlaps.map((pair) => (
                        <li key={`${pair.a.id}-${pair.b.id}`} className="flex items-start gap-2 rounded-md bg-warning-soft p-2 text-xs text-warning">
                            <AlertCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                            <div className="flex min-w-0 flex-col gap-0.5">
                                <span>
                                    <span className="font-semibold">{pair.a.position}:</span> {pair.a.text}
                                </span>
                                <span>
                                    <span className="font-semibold">{pair.b.position}:</span> {pair.b.text}
                                </span>
                                <span className="opacity-80">{Math.round(pair.similarity * 100)}% kata sama</span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
