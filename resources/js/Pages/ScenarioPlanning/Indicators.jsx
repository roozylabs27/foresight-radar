import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, CopyPlusIcon, PlusIcon, RadarIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import IndicatorCard from "@/Components/ScenarioPlanning/IndicatorCard";
import IndicatorDialog from "@/Components/ScenarioPlanning/IndicatorDialog";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { useEarlyWarningIndicators } from "@/hooks/use-early-warning-indicators";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Step 9: early warning indicator register with expected direction per scenario (roadmap R1.9). */
export default function Indicators({ auth, title, project, steps, frameworkApproved, quadrants, indicators, uncertainties, forces, owners, canManage, errors = {} }) {
    const actions = useEarlyWarningIndicators(project.id);
    const [target, setTarget] = useState(null);
    const editable = canManage && frameworkApproved;
    const active = indicators.filter((indicator) => indicator.status === "active" && indicator.review_status === "accepted").length;
    const narrative = quadrants.filter((quadrant) => quadrant.narrative_indicators.length > 0);

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} current={9} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <RadarIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 9 · Early Warning Signal</h1>
                    {frameworkApproved && <span className={statusBadge({ tone: active > 0 ? "approved" : "pending" })}>{active} indikator aktif</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-6 lg:py-5">
                <Panel
                    title={`Indikator (${indicators.length})`}
                    description="Indikator yang baik bergerak ke arah berbeda di skenario yang berbeda."
                    badge={editable && (
                        <Button size="sm" onClick={() => setTarget({ indicator: null, prefill: null })}>
                            <PlusIcon aria-hidden="true" />
                            Indikator baru
                        </Button>
                    )}
                >
                    {errors.indicators && <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.indicators}</p>}
                    {!frameworkApproved ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui kerangka skenario (langkah 4) dulu sebelum menyusun indikator.</p>
                    ) : indicators.length === 0 ? (
                        <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada indikator.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {indicators.map((indicator) => (
                                <IndicatorCard
                                    key={indicator.id}
                                    indicator={indicator}
                                    quadrants={quadrants}
                                    canManage={editable}
                                    onEdit={() => setTarget({ indicator, prefill: null })}
                                    onRemove={() => actions.remove(indicator.id)}
                                />
                            ))}
                        </div>
                    )}
                    <Link href={route("scenario-planning.responses.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeftIcon aria-hidden="true" className="size-4" />
                        Respons strategis
                    </Link>
                </Panel>

                <Panel title="Indikator dari narasi" description="Indikator awal yang ditulis di narasi tiap skenario (langkah 6).">
                    {narrative.length === 0 ? (
                        <p className="m-0 text-xs text-muted-foreground">Belum ada narasi dengan indikator awal.</p>
                    ) : (
                        narrative.map((quadrant) => (
                            <div key={quadrant.position} className="flex flex-col gap-1">
                                <span className="text-xs font-semibold text-foreground">{quadrant.title}</span>
                                <ul className="m-0 flex list-none flex-col gap-1 p-0">
                                    {quadrant.narrative_indicators.map((text, index) => (
                                        <li key={index} className="flex items-start justify-between gap-2 text-xs text-muted-foreground">
                                            <span>{text}</span>
                                            {editable && (
                                                <Button size="icon-xs" variant="ghost" aria-label={`Jadikan indikator: ${text}`} onClick={() => setTarget({ indicator: null, prefill: { name: text.slice(0, 160) } })}>
                                                    <CopyPlusIcon aria-hidden="true" />
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                </Panel>
            </main>

            <IndicatorDialog
                target={target}
                quadrants={quadrants}
                uncertainties={uncertainties}
                forces={forces}
                owners={owners}
                loading={actions.busy}
                onOpenChange={(open) => !open && setTarget(null)}
                onSave={(values) => actions.save(values, target?.indicator?.id)}
            />
        </AuthenticatedLayout>
    );
}
