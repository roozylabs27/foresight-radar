import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, ArrowRightIcon, ListChecksIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import ConditionDialog from "@/Components/ScenarioPlanning/ConditionDialog";
import QuadrantConditions from "@/Components/ScenarioPlanning/QuadrantConditions";
import StaleBanner from "@/Components/ScenarioPlanning/StaleBanner";
import { statusBadge } from "@/Components/StatusBadge";
import { useScenarioConditions } from "@/hooks/use-scenario-conditions";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Grid order: axis B high on top, axis A high on the right (same as the framework). */
const LAYOUT = ["LH", "HH", "LL", "HL"];

/** Step 5: conditions of each future and the predetermined elements (roadmap R1.5). */
export default function Conditions({ auth, title, project, steps, framework, quadrants, predetermined, forces, minimum, canManage, errors = {} }) {
    const actions = useScenarioConditions(project.id);
    const [target, setTarget] = useState(null);
    const ready = framework?.approved;
    const editable = canManage && ready;
    const done = quadrants.filter((quadrant) => quadrant.conditions.length >= minimum).length;
    const byPosition = Object.fromEntries(quadrants.map((quadrant) => [quadrant.position, quadrant]));

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} current={5} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ListChecksIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 5 · Develop Scenarios</h1>
                    {ready && <span className={statusBadge({ tone: done === 4 ? "approved" : "pending" })}>{done} dari 4 kuadran lengkap</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-6 lg:py-5">
                <Panel title={framework?.name ?? "Kondisi skenario"} description={project.focal_question}>
                    <StaleBanner project={project} canManage={canManage} />
                    {(errors.conditions || errors.text) && <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.conditions ?? errors.text}</p>}
                    {!ready ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui kerangka skenario (langkah 4) dulu sebelum menyusun kondisi.</p>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2">
                            {LAYOUT.map((position) => byPosition[position] && (
                                <QuadrantConditions
                                    key={position}
                                    quadrant={byPosition[position]}
                                    axes={framework.axes}
                                    predetermined={predetermined}
                                    minimum={minimum}
                                    canManage={editable}
                                    onAdd={() => setTarget({ quadrant: byPosition[position], condition: null })}
                                    onEdit={(condition) => setTarget({ quadrant: byPosition[position], condition })}
                                    onRemove={actions.remove}
                                    onMove={actions.move}
                                />
                            ))}
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link href={route("scenario-planning.framework.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            Kerangka skenario
                        </Link>
                        {ready && (
                            <Link href={route("scenario-planning.implications.index", project.id)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                                Peluang dan tantangan
                                <ArrowRightIcon aria-hidden="true" className="size-4" />
                            </Link>
                        )}
                    </div>
                </Panel>

                <div className="flex flex-col gap-4">
                    <Panel title={`Elemen predetermined (${predetermined.length})`} description="Berdampak tinggi tetapi hampir pasti; berlaku di keempat skenario.">
                        {predetermined.length === 0 ? (
                            <p className="m-0 text-xs text-muted-foreground">Belum ada driving force yang ditandai predetermined di langkah 2.</p>
                        ) : (
                            <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
                                {predetermined.map((force) => (
                                    <li key={force.id} className="text-sm text-foreground">
                                        {force.keyword}
                                        {force.dimension && <span className="text-xs text-muted-foreground"> · {force.dimension}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                    <Panel title="Aturan">
                        <ul className="m-0 flex list-disc flex-col gap-1 pl-4 text-xs text-muted-foreground">
                            <li>Tiap kuadran butuh minimal {minimum} kondisi agar langkah ini selesai.</li>
                            <li>Sumbu dijelaskan lewat kondisi kutub; driving force lain lewat kondisi perilaku.</li>
                            <li>Kondisi yang sama di keempat kuadran ditandai; mungkin itu elemen predetermined.</li>
                        </ul>
                    </Panel>
                </div>
            </main>

            <ConditionDialog
                target={target}
                axes={framework?.axes ?? []}
                forces={forces}
                loading={actions.busy}
                onOpenChange={(open) => !open && setTarget(null)}
                onSave={(values) => actions.save(values, { quadrantId: target.quadrant.id, conditionId: target.condition?.id })}
            />
        </AuthenticatedLayout>
    );
}
