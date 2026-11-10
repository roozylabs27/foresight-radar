import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, ColumnsIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ComparisonTable from "@/Components/ScenarioPlanning/ComparisonTable";
import ConsistencyDialog from "@/Components/ScenarioPlanning/ConsistencyDialog";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import { statusBadge } from "@/Components/StatusBadge";
import { useConsistencyReview } from "@/hooks/use-consistency-review";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Roadmap R2.8: the four futures of an exercise side by side, with a consistency review per quadrant. */
export default function Compare({ auth, title, project, steps, framework, quadrants, responses, indicators, canManage = false }) {
    const [reviewing, setReviewing] = useState(null);
    const review = useConsistencyReview(project.id);

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ColumnsIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Bandingkan skenario</h1>
                    {framework && <span className={statusBadge({ tone: framework.approved ? "approved" : "pending" })}>{framework.approved ? "Kerangka disetujui" : "Kerangka draf"}</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <Panel
                    title={framework?.name ?? "Perbandingan skenario"}
                    description="Empat masa depan berdampingan. Hanya kondisi, peluang, tantangan, dan indikator yang sudah diterima yang dibandingkan; respons yang ditolak tidak ditampilkan."
                >
                    {!framework ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Buat kerangka skenario (langkah 4) dulu untuk membandingkan skenario.</p>
                    ) : (
                        <>
                            <p className="m-0 text-xs text-muted-foreground">
                                {framework.axes.map((axis) => `${axis.name ?? `Sumbu ${axis.axis}`}: ${axis.low} ↔ ${axis.high}`).join(" · ")}
                            </p>
                            <ComparisonTable quadrants={quadrants} responses={responses} indicators={indicators} canReview={canManage && framework.approved} onReview={setReviewing} />
                        </>
                    )}
                    <Link href={route("scenario-planning.show", project.id)} className="inline-flex items-center gap-1 self-start text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeftIcon aria-hidden="true" className="size-4" />
                        Ringkasan latihan
                    </Link>
                </Panel>
            </main>

            <ConsistencyDialog quadrant={reviewing} loading={review.busy} onOpenChange={(open) => !open && setReviewing(null)} onSave={review.save} />
        </AuthenticatedLayout>
    );
}
