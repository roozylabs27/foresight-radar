import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { DECISION_ACTIONS } from "@/domain/scenarioPlanning";

/** Gate decisions of an exercise, newest first: who approved, rejected or withdrew what (roadmap R2.2). */
export default function DecisionLog({ decisions }) {
    return (
        <Panel title="Riwayat keputusan" description="Setiap persetujuan, penolakan, dan pembatalan gate G1–G7 serta perubahan status latihan, terbaru di atas.">
            {decisions.length === 0 ? (
                <p className="m-0 text-sm text-muted-foreground">Belum ada keputusan gate.</p>
            ) : (
                <ol className="m-0 flex max-h-80 list-none flex-col gap-2 overflow-y-auto p-0">
                    {decisions.map((row, index) => {
                        const action = DECISION_ACTIONS[row.action];
                        return (
                            <li key={index} className="flex items-start gap-2 text-xs">
                                <span className={statusBadge({ tone: action?.tone ?? "neutral" })}>{row.gate}</span>
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="text-foreground">
                                        {action?.label ?? row.action}
                                        {row.subject && <>: {row.subject}</>}
                                    </span>
                                    {row.rationale && <span className="text-foreground/80">Alasan: {row.rationale}</span>}
                                    <span className="text-muted-foreground">
                                        {row.actor ?? "Sistem"} · {new Date(row.at).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" })}
                                    </span>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}
        </Panel>
    );
}
