import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";

/**
 * Which scenario the observations point to (roadmap R2.3): per quadrant, how many directional
 * observations match its expected direction and how many go against it.
 */
export default function RelevancePanel({ relevance }) {
    if (!relevance) return null;
    const most = Math.max(1, ...relevance.quadrants.map((row) => row.supporting + row.contradicting));

    return (
        <Panel
            title="Skenario yang menguat"
            description={relevance.observations === 0
                ? "Belum ada observasi dengan arah perubahan. Catat observasi di indikator untuk melihat skenario mana yang mulai terjadi."
                : `Dari ${relevance.observations} observasi berarah pada indikator aktif. Sesuai = arah observasi sama dengan arah yang diharapkan di skenario itu.`}
        >
            <ul className="m-0 grid list-none gap-3 p-0 sm:grid-cols-2 lg:grid-cols-4">
                {relevance.quadrants.map((row) => {
                    const leading = relevance.leading === row.position;
                    return (
                        <li key={row.position} className={cn("flex flex-col gap-2 rounded-lg border p-3", leading ? "border-primary bg-primary/5" : "border-border")}>
                            <div className="flex items-center justify-between gap-2">
                                <span className="min-w-0 truncate text-sm font-semibold text-foreground">{row.title ?? row.position}</span>
                                {leading && <span className={statusBadge({ tone: "approved" })}>Paling sesuai</span>}
                            </div>
                            <div className="flex flex-col gap-1 text-xs">
                                <div className="flex items-center gap-2">
                                    <span className="w-20 shrink-0 text-muted-foreground">Sesuai</span>
                                    <span className="h-2 rounded-full bg-success" style={{ width: `${(row.supporting / most) * 100}%`, minWidth: row.supporting ? 4 : 0 }} aria-hidden="true" />
                                    <span className="text-foreground">{row.supporting}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-20 shrink-0 text-muted-foreground">Berlawanan</span>
                                    <span className="h-2 rounded-full bg-danger" style={{ width: `${(row.contradicting / most) * 100}%`, minWidth: row.contradicting ? 4 : 0 }} aria-hidden="true" />
                                    <span className="text-foreground">{row.contradicting}</span>
                                </div>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </Panel>
    );
}
