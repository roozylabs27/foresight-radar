import { useMemo } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, ArrowRightIcon, ScrollTextIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import QuadrantCard from "@/Components/Scenario/QuadrantCard";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import NarrativeEvidence from "@/Components/ScenarioPlanning/NarrativeEvidence";
import NarrativeGenerateBar from "@/Components/ScenarioPlanning/NarrativeGenerateBar";
import NarrativeRevisions from "@/Components/ScenarioPlanning/NarrativeRevisions";
import StaleBanner from "@/Components/ScenarioPlanning/StaleBanner";
import { statusBadge } from "@/Components/StatusBadge";
import { useExerciseNarratives } from "@/hooks/use-exercise-narratives";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Grid order: axis B high on top, axis A high on the right (same as the framework). */
const LAYOUT = ["LH", "HH", "LL", "HL"];

/** Step 6: the four scenario narratives of this exercise, their review (G6) and their history. */
export default function Narratives({ auth, title, project, steps, framework, revisions = {}, canManage, llm }) {
    const actions = useExerciseNarratives(project.id, framework);
    const ready = framework?.framework_status === "approved";
    const editable = canManage && ready;
    const quadrants = framework?.quadrants ?? [];
    const approved = quadrants.filter((quadrant) => quadrant.review_status === "approved").length;
    const byPosition = Object.fromEntries(quadrants.map((quadrant) => [quadrant.position, quadrant]));
    const evidence = useMemo(() => Object.fromEntries((framework?.evidence ?? []).map((item) => [item.ref, item])), [framework?.evidence]);

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} current={6} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ScrollTextIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 6 · Narratives Imagination</h1>
                    {ready && <span className={statusBadge({ tone: approved === 4 ? "approved" : "pending" })}>{approved} dari 4 narasi disetujui (G6)</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <Panel
                    title={framework?.name ?? "Narasi skenario"}
                    description="Tiap kuadran butuh narasi yang ditinjau dan disetujui tim. Narasi AI hanya boleh mengutip sinyal pada daftar bukti; ubah teksnya sebelum dipakai."
                >
                    <StaleBanner project={project} canManage={canManage} />
                    {!ready ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui kerangka skenario (langkah 4) dulu sebelum menulis narasi.</p>
                    ) : (
                        <>
                            {editable && <NarrativeGenerateBar framework={framework} llm={llm} busy={actions.busy} pending={actions.pending} onGenerate={actions.generate} />}
                            <section aria-label="Matriks skenario 2×2" className="flex flex-col gap-2">
                                <span className="text-[11px] text-muted-foreground">↑ {framework.axis_b.keyword}: {framework.axis_b.high}</span>
                                <div className="grid gap-3 md:grid-cols-2">
                                    {LAYOUT.map((position) => byPosition[position] && (
                                        <QuadrantCard
                                            key={position}
                                            quadrant={byPosition[position]}
                                            evidence={evidence}
                                            canManage={editable && !actions.pending}
                                            titleLocked
                                            onSave={actions.save(byPosition[position])}
                                            onReview={actions.review(byPosition[position])}
                                        />
                                    ))}
                                </div>
                                <div className="flex flex-wrap justify-between gap-2 text-[11px] text-muted-foreground">
                                    <span>↓ {framework.axis_b.keyword}: {framework.axis_b.low}</span>
                                    <span>{framework.axis_a.keyword}: {framework.axis_a.low} ← → {framework.axis_a.high}</span>
                                </div>
                            </section>
                        </>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link href={route("scenario-planning.conditions.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            Kondisi tiap skenario
                        </Link>
                        {ready && (
                            <Link href={route("scenario-planning.implications.index", project.id)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                                Peluang dan tantangan
                                <ArrowRightIcon aria-hidden="true" className="size-4" />
                            </Link>
                        )}
                    </div>
                </Panel>
                {ready && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel title={`Bukti (${framework.evidence?.length ?? 0} sinyal)`} description="Label S yang dikutip narasi.">
                            <NarrativeEvidence evidence={framework.evidence} />
                        </Panel>
                        <Panel title="Riwayat narasi" description="Teks yang diganti oleh suntingan atau pembuatan ulang, terbaru lebih dulu.">
                            <NarrativeRevisions quadrants={quadrants} revisions={revisions} />
                        </Panel>
                    </div>
                )}
            </main>
        </AuthenticatedLayout>
    );
}
