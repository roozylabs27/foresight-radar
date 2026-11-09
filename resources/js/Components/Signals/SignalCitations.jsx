import { useEffect, useState } from "react";
import axios from "axios";

const KIND_LABELS = { fact: "Fakta", analysis: "Analisis", assumption: "Asumsi", interpretation: "Interpretasi" };

/**
 * Scenario quadrants that cite one signal (roadmap R2.7), with the kind of the citing statement.
 * Loaded when the signal detail opens.
 */
export default function SignalCitations({ signal }) {
    const [state, setState] = useState({ loading: true, rows: [], failed: false });

    useEffect(() => {
        const controller = new AbortController();
        setState({ loading: true, rows: [], failed: false });
        axios
            .get(route("signals.citations", signal.uuid), { signal: controller.signal })
            .then((response) => setState({ loading: false, rows: response.data.citations ?? [], failed: false }))
            .catch((error) => !axios.isCancel(error) && setState({ loading: false, rows: [], failed: true }));
        return () => controller.abort();
    }, [signal.uuid]);

    if (state.loading) return <span className="text-muted-foreground">Memuat…</span>;
    if (state.failed) return <span className="text-destructive">Kutipan skenario gagal dimuat.</span>;
    if (state.rows.length === 0) return <span className="text-muted-foreground">Belum dikutip di skenario mana pun.</span>;

    return (
        <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
            {state.rows.map((row, index) => (
                <li key={index} className="flex flex-col gap-0.5">
                    <span>
                        <span className="font-medium">{row.scenario}</span>
                        <span className="text-muted-foreground"> · {row.position}{row.quadrant ? ` ${row.quadrant}` : ""}</span>
                        {row.kind && <span className="text-muted-foreground"> · {KIND_LABELS[row.kind] ?? row.kind}</span>}
                    </span>
                    {row.statement && <span className="text-xs text-muted-foreground">“{row.statement}”</span>}
                </li>
            ))}
        </ul>
    );
}
