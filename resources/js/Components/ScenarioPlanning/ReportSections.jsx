import { CONDITION_KINDS, INDICATOR_DIRECTIONS, RESPONSE_FITS, ROBUSTNESS } from "@/domain/scenarioPlanning";
import { cn } from "@/lib/utils";

/**
 * Sections of the printable exercise report (roadmap R3.5), one per step of the deck (slides
 * 15–25). Plain markup and print-friendly classes: sections avoid page breaks inside.
 */
const date = (iso) => (iso ? new Date(iso).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" }) : "–");

export function Section({ number, title, children, className }) {
    return (
        <section className={cn("flex flex-col gap-3 border-t border-border pt-5 print:break-inside-avoid-page", className)}>
            <h2 className="m-0 text-lg font-bold text-foreground">
                {number && <span className="mr-2 text-muted-foreground">{number}.</span>}
                {title}
            </h2>
            {children}
        </section>
    );
}

export function Empty({ children }) {
    return <p className="m-0 text-sm text-muted-foreground italic">{children}</p>;
}

function Table({ head, rows }) {
    return (
        <table className="w-full border-collapse text-left text-sm">
            <thead>
                <tr>{head.map((cell, index) => <th key={index} scope="col" className="border-b border-border p-2 text-xs font-semibold text-muted-foreground">{cell}</th>)}</tr>
            </thead>
            <tbody>
                {rows.map((row, index) => (
                    <tr key={index} className="border-b border-border/60 align-top print:break-inside-avoid">
                        {row.map((cell, cellIndex) => <td key={cellIndex} className="p-2 text-foreground">{cell}</td>)}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export function GateList({ gates }) {
    return (
        <ol className="m-0 grid list-none gap-1 p-0 text-sm sm:grid-cols-2">
            {gates.map((gate) => (
                <li key={gate.gate} className={cn("flex gap-2", !gate.passed && "text-muted-foreground")}>
                    <span className="w-6 font-semibold">{gate.gate}</span>
                    <span>{gate.label}: {gate.passed ? `${gate.by ?? "–"}, ${date(gate.at)}` : "belum"}</span>
                </li>
            ))}
        </ol>
    );
}

export function ForceTable({ forces }) {
    if (forces.length === 0) return <Empty>Belum ada driving force yang disertakan.</Empty>;
    return (
        <Table
            head={["Driving force", "Dimensi", "Dampak", "Ketidakpastian", "Catatan"]}
            rows={forces.map((force) => [force.keyword, force.dimension ?? "–", force.impact ?? "–", force.uncertainty ?? "–", force.predetermined ? "Elemen pasti (predetermined)" : ""])}
        />
    );
}

export function UncertaintyList({ uncertainties }) {
    if (uncertainties.length === 0) return <Empty>Belum ada critical uncertainty yang disetujui.</Empty>;
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {uncertainties.map((row) => (
                <div key={row.axis} className="flex flex-col gap-1 rounded-md border border-border p-3 text-sm print:break-inside-avoid">
                    <span className="text-xs font-semibold text-muted-foreground uppercase">Sumbu {row.axis}</span>
                    <span className="font-semibold text-foreground">{row.name}</span>
                    <span className="text-foreground">{row.low} ↔ {row.high}</span>
                    {row.description && <span className="text-muted-foreground">{row.description}</span>}
                    {row.forces.length > 0 && <span className="text-xs text-muted-foreground">Driving force: {row.forces.join(", ")}</span>}
                </div>
            ))}
        </div>
    );
}

function Bullets({ items, empty = "–" }) {
    if (items.length === 0) return <span className="text-muted-foreground">{empty}</span>;
    return <ul className="m-0 flex list-disc flex-col gap-0.5 pl-4">{items.map((item, index) => <li key={index}>{item}</li>)}</ul>;
}

export function QuadrantPages({ quadrants }) {
    return quadrants.map((quadrant) => (
        <article key={quadrant.position} className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm print:break-inside-avoid-page">
            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{quadrant.position} · {quadrant.poles.join(" × ")}</span>
            <h3 className="m-0 text-base font-bold text-foreground">{quadrant.title ?? quadrant.position}</h3>
            {quadrant.summary && <p className="m-0 text-muted-foreground">{quadrant.summary}</p>}
            <h4 className="m-0 mt-1 text-xs font-semibold text-foreground">Kondisi</h4>
            <Bullets items={quadrant.conditions.map((row) => `${CONDITION_KINDS[row.kind]?.label ?? row.kind}: ${row.text}`)} empty="Belum ada kondisi yang diterima." />
            <h4 className="m-0 mt-1 text-xs font-semibold text-foreground">Narasi</h4>
            {quadrant.narrative ? <p className="m-0 leading-relaxed whitespace-pre-line text-foreground">{quadrant.narrative}</p> : <Empty>Narasi belum disetujui.</Empty>}
            <div className="mt-1 grid gap-3 sm:grid-cols-2">
                <div><h4 className="m-0 mb-1 text-xs font-semibold text-foreground">Peluang</h4><Bullets items={quadrant.opportunities} /></div>
                <div><h4 className="m-0 mb-1 text-xs font-semibold text-foreground">Tantangan</h4><Bullets items={quadrant.challenges} /></div>
            </div>
        </article>
    ));
}

export function EvidenceList({ evidence }) {
    if (evidence.length === 0) return null;
    return (
        <ol className="m-0 flex list-none flex-col gap-0.5 p-0 text-xs text-muted-foreground">
            {evidence.map((row) => <li key={row.ref}><span className="font-semibold">[{row.ref}]</span> {row.title}{row.source && ` · ${row.source}`}{row.url && ` · ${row.url}`}</li>)}
        </ol>
    );
}

export function ResponseTable({ responses, quadrants }) {
    if (responses.length === 0) return <Empty>Belum ada respons strategis.</Empty>;
    return (
        <Table
            head={["Respons", "Pemilik", ...quadrants.map((q) => q.title ?? q.position), "Ketahanan"]}
            rows={responses.map((row) => [
                <><span className="font-medium">{row.title}</span>{row.status === "proposed" && <span className="text-muted-foreground"> (usulan)</span>}{row.description && <span className="block text-xs text-muted-foreground">{row.description}</span>}</>,
                row.owner ?? "–",
                ...quadrants.map((q) => RESPONSE_FITS[row.fit[q.position]]?.label ?? "–"),
                ROBUSTNESS[row.robustness]?.label ?? "–",
            ])}
        />
    );
}

export function IndicatorTable({ indicators, quadrants }) {
    if (indicators.length === 0) return <Empty>Belum ada indikator aktif.</Empty>;
    return (
        <Table
            head={["Indikator", "Ambang", ...quadrants.map((q) => q.title ?? q.position)]}
            rows={indicators.map((row) => [
                <><span className="font-medium">{row.name}</span>{row.metric && <span className="block text-xs text-muted-foreground">{row.metric}</span>}</>,
                row.threshold_rule ?? "–",
                ...quadrants.map((q) => { const d = INDICATOR_DIRECTIONS[row.directions[q.position]]; return d ? `${d.short} ${d.label}` : "–"; }),
            ])}
        />
    );
}

export function DecisionTable({ decisions }) {
    if (decisions.length === 0) return <Empty>Belum ada keputusan tercatat.</Empty>;
    return <Table head={["Tanggal", "Gate", "Keputusan", "Oleh"]} rows={decisions.map((row) => [date(row.at), row.gate, row.subject ? `${row.label} · ${row.subject}` : row.label, row.actor ?? "–"])} />;
}

export { date };
