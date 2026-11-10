import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, MilestoneIcon, PlusIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import MilestoneDialog from "@/Components/ScenarioPlanning/MilestoneDialog";
import MilestoneList from "@/Components/ScenarioPlanning/MilestoneList";
import PreferredFuture from "@/Components/ScenarioPlanning/PreferredFuture";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { useBackcasting } from "@/hooks/use-backcasting";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/**
 * Roadmap R4.1 (deck slides 2 and 26): choose the preferred future among the four scenarios, then
 * plan back from the target year to now so that early actions start before the trend is clear.
 */
export default function Backcasting({ auth, title, project, steps, frameworkApproved, quadrants, preferred, milestones, owners, responses, indicators, canManage = false, errors = {} }) {
    const actions = useBackcasting(project.id);
    const [target, setTarget] = useState(null);

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <MilestoneIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Masa depan pilihan & backcasting</h1>
                    {preferred && <span className={statusBadge({ tone: "approved" })}>{preferred.title}</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-2 lg:px-6 lg:py-5">
                <Panel title="Masa depan pilihan" description="Dari empat skenario, mana yang ingin diupayakan organisasi? Skenario lain tetap dipantau lewat early warning signal.">
                    {errors.backcasting && <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.backcasting}</p>}
                    {!frameworkApproved ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui kerangka skenario (langkah 4) dulu sebelum memilih masa depan pilihan.</p>
                    ) : (
                        <PreferredFuture quadrants={quadrants} preferred={preferred} canManage={canManage} busy={actions.busy} onSave={actions.prefer} onClear={actions.clear} />
                    )}
                    <Link href={route("scenario-planning.show", project.id)} className="inline-flex items-center gap-1 self-start text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeftIcon aria-hidden="true" className="size-4" />
                        Ringkasan latihan
                    </Link>
                </Panel>

                <Panel
                    title={`Jalur mundur ${project.target_year} → sekarang`}
                    description="Backcasting: mulai dari kondisi masa depan pilihan, lalu tentukan apa yang harus tercapai setiap tahun sebelumnya."
                    badge={canManage && preferred && (
                        <Button size="sm" onClick={() => setTarget({ milestone: null })}>
                            <PlusIcon aria-hidden="true" />
                            Milestone baru
                        </Button>
                    )}
                >
                    {!preferred ? (
                        <p className="m-0 text-sm text-muted-foreground">Pilih masa depan pilihan dulu untuk menyusun milestone.</p>
                    ) : null}
                    <MilestoneList milestones={milestones} targetYear={project.target_year} canManage={canManage} busy={actions.busy} onEdit={(milestone) => setTarget({ milestone })} onRemove={actions.remove} />
                </Panel>
            </main>

            <MilestoneDialog
                target={target}
                targetYear={project.target_year}
                owners={owners}
                responses={responses}
                indicators={indicators}
                loading={actions.busy}
                onOpenChange={(open) => !open && setTarget(null)}
                onSave={(values) => actions.save(values, target?.milestone?.id)}
            />
        </AuthenticatedLayout>
    );
}
