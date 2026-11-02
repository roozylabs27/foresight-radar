import { Link } from "@inertiajs/react";
import { ArrowRightIcon, CircleCheckIcon, FlagIcon } from "lucide-react";

/** The overview's "what now": the next step to work on and the last gate passed (roadmap R1.11). */
export default function NextActionCard({ nextAction, lastGate }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5 rounded-lg border border-primary/40 bg-accent/30 p-3">
                <span className="flex items-center gap-1.5 text-xs font-semibold text-primary">
                    <FlagIcon aria-hidden="true" className="size-3.5" />
                    Langkah berikutnya
                </span>
                {nextAction ? (
                    <>
                        <p className="m-0 text-sm font-medium text-foreground">
                            {nextAction.number}. {nextAction.title}
                        </p>
                        <p className="m-0 text-xs text-muted-foreground">{nextAction.text}</p>
                        <Link href={nextAction.href} className="inline-flex items-center gap-1 self-start text-sm font-medium text-primary hover:underline">
                            Buka langkah
                            <ArrowRightIcon aria-hidden="true" className="size-4" />
                        </Link>
                    </>
                ) : (
                    <p className="m-0 text-sm text-foreground">Semua langkah yang terbuka sudah selesai.</p>
                )}
            </div>
            <div className="flex flex-col gap-1.5 rounded-lg border border-border p-3">
                <span className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                    <CircleCheckIcon aria-hidden="true" className="size-3.5" />
                    Gerbang terakhir
                </span>
                {lastGate ? (
                    <>
                        <p className="m-0 text-sm font-medium text-foreground">
                            {lastGate.gate} · {lastGate.label}
                        </p>
                        <p className="m-0 text-xs text-muted-foreground">
                            {[lastGate.by && `Oleh ${lastGate.by}`, lastGate.at && new Date(lastGate.at).toLocaleDateString("id-ID", { dateStyle: "medium" })].filter(Boolean).join(" · ")}
                        </p>
                    </>
                ) : (
                    <p className="m-0 text-sm text-muted-foreground">Belum ada gerbang yang dilewati.</p>
                )}
            </div>
        </div>
    );
}
