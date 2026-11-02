import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, ArrowRightIcon, CompassIcon, PlusIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import ResponseCard from "@/Components/ScenarioPlanning/ResponseCard";
import ResponseDialog from "@/Components/ScenarioPlanning/ResponseDialog";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { RESPONSE_STATUSES } from "@/domain/scenarioPlanning";
import { useStrategicResponses } from "@/hooks/use-strategic-responses";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Step 8: strategic responses ranked by robustness, and gate G7 (roadmap R1.8). */
export default function Responses({ auth, title, project, steps, frameworkApproved, quadrants, responses, implications, owners, canManage, errors = {} }) {
    const actions = useStrategicResponses(project.id);
    const [target, setTarget] = useState(null);
    const [filter, setFilter] = useState("all");
    const editable = canManage && frameworkApproved;
    const approved = responses.filter((response) => response.status === "approved").length;
    const shown = filter === "all" ? responses : responses.filter((response) => response.status === filter);

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} current={8} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <CompassIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 8 · Strategic Response</h1>
                    {frameworkApproved && <span className={statusBadge({ tone: approved > 0 ? "approved" : "pending" })}>{approved} disetujui</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <Panel
                    title={`Respons strategis (${responses.length})`}
                    description="Diurutkan dari yang cocok di paling banyak kuadran. Robust = cocok di 3 kuadran atau lebih."
                    badge={editable && (
                        <Button size="sm" onClick={() => setTarget({ response: null })}>
                            <PlusIcon aria-hidden="true" />
                            Respons baru
                        </Button>
                    )}
                >
                    {(errors.responses || errors.rationale || errors.fit) && (
                        <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{[errors.responses, errors.rationale, errors.fit].filter(Boolean).join(" ")}</p>
                    )}
                    {!frameworkApproved ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui kerangka skenario (langkah 4) dulu sebelum menyusun respons strategis.</p>
                    ) : (
                        <>
                            <ToggleGroup type="single" variant="outline" size="sm" value={filter} onValueChange={(value) => value && setFilter(value)} aria-label="Saring status" className="self-start">
                                <ToggleGroupItem value="all" className="px-3">Semua</ToggleGroupItem>
                                {Object.entries(RESPONSE_STATUSES).map(([value, status]) => (
                                    <ToggleGroupItem key={value} value={value} className="px-3">
                                        {status.label}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                            {shown.length === 0 ? (
                                <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada respons di sini.</p>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {shown.map((response) => (
                                        <ResponseCard
                                            key={response.id}
                                            response={response}
                                            quadrants={quadrants}
                                            canManage={editable}
                                            busy={actions.busy}
                                            onEdit={() => setTarget({ response })}
                                            onRemove={() => actions.remove(response.id)}
                                            onApprove={() => actions.approve(response.id)}
                                            onReject={() => actions.reject(response.id)}
                                        />
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link href={route("scenario-planning.implications.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            Peluang dan tantangan
                        </Link>
                        {frameworkApproved && (
                            <Link href={route("scenario-planning.indicators.index", project.id)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                                Indikator peringatan dini
                                <ArrowRightIcon aria-hidden="true" className="size-4" />
                            </Link>
                        )}
                    </div>
                </Panel>
            </main>

            <ResponseDialog
                target={target}
                quadrants={quadrants}
                implications={implications}
                owners={owners}
                loading={actions.busy}
                onOpenChange={(open) => !open && setTarget(null)}
                onSave={(values) => actions.save(values, target?.response?.id)}
            />
        </AuthenticatedLayout>
    );
}
