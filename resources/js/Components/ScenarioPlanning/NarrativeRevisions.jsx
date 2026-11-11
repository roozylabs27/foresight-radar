import { HistoryIcon } from "lucide-react";

const REASONS = { edit: "Diganti oleh suntingan", regenerate: "Diganti oleh pembuatan ulang" };
const ORIGINS = { ai: "AI", template: "Kerangka tanpa AI", human: "Tim" };

/**
 * Earlier versions of each quadrant narrative, newest first (audit U3). Each entry is the text
 * as it was before an edit or a regeneration replaced it.
 */
export default function NarrativeRevisions({ quadrants, revisions }) {
    const groups = quadrants.filter((quadrant) => revisions[quadrant.id]?.length);
    if (groups.length === 0) {
        return <p className="m-0 text-sm text-muted-foreground">Belum ada versi lama. Versi sebelumnya disimpan otomatis setiap kali narasi disunting atau dibuat ulang.</p>;
    }

    return (
        <div className="flex flex-col gap-3">
            {groups.map((quadrant) => (
                <details key={quadrant.id} className="rounded-md border border-border p-3">
                    <summary className="flex cursor-pointer items-center gap-2 text-sm font-medium text-foreground">
                        <HistoryIcon aria-hidden="true" className="size-4 text-muted-foreground" />
                        {quadrant.position} · {quadrant.title}
                        <span className="text-xs font-normal text-muted-foreground">({revisions[quadrant.id].length} versi lama)</span>
                    </summary>
                    <ol className="m-0 mt-3 flex list-none flex-col gap-3 p-0">
                        {revisions[quadrant.id].map((revision) => (
                            <li key={revision.id} className="flex flex-col gap-1 border-l-2 border-border pl-3">
                                <span className="text-xs text-muted-foreground">
                                    {REASONS[revision.reason] ?? revision.reason}
                                    {revision.user ? ` · ${revision.user}` : ""}
                                    {revision.created_at ? ` · ${new Date(revision.created_at).toLocaleString("id-ID")}` : ""}
                                    {" · "}asal: {ORIGINS[revision.origin] ?? revision.origin ?? "-"}, status: {revision.review_status ?? "-"}
                                </span>
                                <p className="m-0 whitespace-pre-line text-sm text-foreground">{revision.narrative}</p>
                            </li>
                        ))}
                    </ol>
                </details>
            ))}
        </div>
    );
}
