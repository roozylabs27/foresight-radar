import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, ArrowRightIcon, ScaleIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ImplicationDialog from "@/Components/ScenarioPlanning/ImplicationDialog";
import QuadrantImplications from "@/Components/ScenarioPlanning/QuadrantImplications";
import StaleBanner from "@/Components/ScenarioPlanning/StaleBanner";
import { statusBadge } from "@/Components/StatusBadge";
import { useScenarioImplications } from "@/hooks/use-scenario-implications";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Grid order: axis B high on top, axis A high on the right (same as the framework). */
const LAYOUT = ["LH", "HH", "LL", "HL"];

const isComplete = (quadrant) => ["opportunity", "challenge"].every((type) => quadrant.items.some((item) => item.type === type));

/** Step 7: opportunities and challenges of each future (roadmap R1.7). */
export default function Implications({ auth, title, project, framework, quadrants, forces, canManage, errors = {} }) {
    const actions = useScenarioImplications(project.id);
    const [target, setTarget] = useState(null);
    const ready = framework?.approved;
    const editable = canManage && ready;
    const done = quadrants.filter(isComplete).length;
    const byPosition = Object.fromEntries(quadrants.map((quadrant) => [quadrant.position, quadrant]));

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ScaleIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 7 · Challenges & Opportunities</h1>
                    {ready && <span className={statusBadge({ tone: done === 4 ? "approved" : "pending" })}>{done} dari 4 kuadran lengkap</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <Panel title={framework?.name ?? "Peluang dan tantangan"} description="Tiap kuadran butuh minimal satu peluang dan satu tantangan. Kaitkan item dengan driving force dan kondisi penyebabnya.">
                    <StaleBanner project={project} canManage={canManage} />
                    {errors.implications && <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.implications}</p>}
                    {!ready ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui kerangka skenario (langkah 4) dulu sebelum menyusun peluang dan tantangan.</p>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2">
                            {LAYOUT.map((position) => byPosition[position] && (
                                <QuadrantImplications
                                    key={position}
                                    quadrant={byPosition[position]}
                                    canManage={editable}
                                    onAdd={(prefill) => setTarget({ quadrant: byPosition[position], item: null, prefill })}
                                    onEdit={(item) => setTarget({ quadrant: byPosition[position], item, prefill: null })}
                                    onRemove={actions.remove}
                                />
                            ))}
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link href={route("scenario-planning.conditions.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            Kondisi tiap skenario
                        </Link>
                        {ready && (
                            <Link href={route("scenario-planning.responses.index", project.id)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                                Respons strategis
                                <ArrowRightIcon aria-hidden="true" className="size-4" />
                            </Link>
                        )}
                    </div>
                </Panel>
            </main>

            <ImplicationDialog
                target={target}
                forces={forces}
                loading={actions.busy}
                onOpenChange={(open) => !open && setTarget(null)}
                onSave={(values) => actions.save(values, { quadrantId: target.quadrant.id, itemId: target.item?.id })}
            />
        </AuthenticatedLayout>
    );
}
