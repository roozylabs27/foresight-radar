import { Link } from "@inertiajs/react";

import Panel from "@/Components/Panel";
import { SensingBadges } from "@/Components/Signals/SignalClassification";

/**
 * Roadmap R4.2 (deck slide 26, sensing): accepted weak signals and wild cards, shown apart from the
 * indicators because they may point at a scenario before any indicator moves.
 */
export default function SensingPanel({ signals }) {
    return (
        <Panel title="Weak signal & wild card" description="Sinyal diterima yang diklasifikasikan sebagai weak signal (might) atau wild card.">
            {signals.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground">Belum ada. Klasifikasikan sinyal di halaman Sinyal.</p>
            ) : (
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                    {signals.map((signal) => (
                        <li key={signal.id} className="flex flex-col gap-1">
                            <span className="text-xs font-semibold text-foreground">{signal.title}</span>
                            <SensingBadges signal={signal} />
                        </li>
                    ))}
                </ul>
            )}
            <Link href={route("signals.index")} className="self-start text-xs font-medium text-primary hover:underline">
                Buka halaman Sinyal
            </Link>
        </Panel>
    );
}
