import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, PrinterIcon } from "lucide-react";

import ComparisonTable from "@/Components/ScenarioPlanning/ComparisonTable";
import { BackcastingSummary, date, DecisionTable, Empty, EvidenceList, ForceTable, GateList, IndicatorTable, QuadrantPages, ResponseTable, Section, UncertaintyList } from "@/Components/ScenarioPlanning/ReportSections";
import { Button } from "@/Components/ui/button";

/**
 * Roadmap R3.5: printable report of one exercise, following the deck (slides 15–25). The browser's
 * "Save as PDF" gives the file. Until G1–G7 have all passed it is marked as a draft.
 */
export default function Report({ title, project, gates, draft, forces, uncertainties, framework, quadrants, evidence, responses, indicators, backcasting, decisions, generated_at: generatedAt }) {
    return (
        <div className="min-h-svh bg-background text-foreground print:bg-white">
            <Head title={title} />
            <div className="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-border bg-background/95 px-4 py-2 print:hidden">
                <Link href={route("scenario-planning.show", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeftIcon aria-hidden="true" className="size-4" />
                    Kembali ke latihan
                </Link>
                <Button size="sm" onClick={() => window.print()}>
                    <PrinterIcon aria-hidden="true" />
                    Cetak / simpan PDF
                </Button>
            </div>

            <main className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 print:max-w-none print:p-0">
                <header className="flex flex-col gap-2">
                    {draft && (
                        <p role="status" className="m-0 rounded-md border border-warning bg-warning-soft px-3 py-2 text-sm font-semibold text-warning">
                            DRAF — belum semua gate (G1–G7) dilalui. Isi laporan dapat berubah.
                        </p>
                    )}
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Laporan scenario planning · dibuat {date(generatedAt)}</span>
                    <h1 className="m-0 text-2xl font-bold">{project.name}</h1>
                    <p className="m-0 text-sm text-muted-foreground">
                        Tahun target {project.target_year}
                        {project.owner?.name && ` · Pemilik ${project.owner.name}`}
                        {project.owner_unit && ` · ${project.owner_unit}`}
                    </p>
                </header>

                <Section title="Status gate">
                    <GateList gates={gates} />
                </Section>

                <Section number={1} title="Key Focal Issue">
                    <blockquote className="m-0 border-0 text-base font-semibold">{project.focal_question}</blockquote>
                    <dl className="m-0 grid gap-2 text-sm sm:grid-cols-2">
                        {[["Tujuan", project.purpose], ["Cakupan geografis", project.geography], ["Industri", project.industry], ["Latar belakang", project.background], ["Sasaran", project.objectives]].filter(([, value]) => value).map(([label, value]) => (
                            <div key={label}><dt className="text-xs font-semibold text-muted-foreground">{label}</dt><dd className="m-0">{value}</dd></div>
                        ))}
                    </dl>
                </Section>

                <Section number={2} title="Driving Forces & skor">
                    <ForceTable forces={forces} />
                </Section>

                <Section number={3} title="Critical Uncertainties">
                    <UncertaintyList uncertainties={uncertainties} />
                </Section>

                <Section number={4} title={`Scenario Framework${framework ? ` · ${framework.name}` : ""}`}>
                    {!framework ? <Empty>Kerangka skenario belum dibuat.</Empty> : (
                        <>
                            <p className="m-0 text-sm">{framework.axes.map((axis) => `${axis.name ?? `Sumbu ${axis.axis}`}: ${axis.low} ↔ ${axis.high}`).join(" · ")}{!framework.approved && " (belum disetujui)"}</p>
                            <ComparisonTable quadrants={quadrants} responses={[]} indicators={[]} />
                        </>
                    )}
                </Section>

                {framework && (
                    <Section number="5–7" title="Skenario: kondisi, narasi, peluang dan tantangan">
                        <QuadrantPages quadrants={quadrants} />
                        <EvidenceList evidence={evidence} />
                    </Section>
                )}

                <Section number={8} title="Strategic Response">
                    <ResponseTable responses={responses} quadrants={quadrants} />
                </Section>

                <Section number={9} title="Early Warning Signals">
                    <IndicatorTable indicators={indicators} quadrants={quadrants} />
                </Section>

                <Section title="Masa depan pilihan & backcasting">
                    <BackcastingSummary backcasting={backcasting} />
                </Section>

                <Section title="Log keputusan">
                    <DecisionTable decisions={decisions} />
                </Section>
            </main>
        </div>
    );
}
