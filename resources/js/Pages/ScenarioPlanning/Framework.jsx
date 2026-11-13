import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, CircleCheckIcon, Grid2x2Icon, LockOpenIcon, PlusIcon, SlidersHorizontalIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import Panel from "@/Components/Panel";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import FrameworkMatrix from "@/Components/ScenarioPlanning/FrameworkMatrix";
import FrameworkSuggestion from "@/Components/ScenarioPlanning/FrameworkSuggestion";
import PolesDialog from "@/Components/ScenarioPlanning/PolesDialog";
import QuadrantDialog from "@/Components/ScenarioPlanning/QuadrantDialog";
import StaleBanner from "@/Components/ScenarioPlanning/StaleBanner";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { useScenarioFramework } from "@/hooks/use-scenario-framework";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Step 4: the 2x2 framework on the two approved critical uncertainties, and gate G5 (roadmap R0.5), with AI pole and name suggestions (R2.5). */
export default function Framework({ auth, title, project, steps, framework, axesApproved, canManage, errors = {} }) {
    const actions = useScenarioFramework(project.id);
    const [editingPoles, setEditingPoles] = useState(false);
    const [quadrant, setQuadrant] = useState(null);
    const approved = framework?.status === "approved";
    const editable = canManage && framework && !approved;

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} current={4} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <Grid2x2Icon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 4 · Scenario Framework</h1>
                    {framework && <span className={statusBadge({ tone: approved ? "approved" : "pending" })}>{approved ? "Disetujui" : "Draf"}</span>}
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:px-6 lg:py-5">
                <Panel
                    title={framework ? framework.name : "Kerangka skenario"}
                    description={project.focal_question}
                    badge={editable && (
                        <Button size="sm" variant="outline" onClick={() => setEditingPoles(true)}>
                            <SlidersHorizontalIcon aria-hidden="true" />
                            Ubah kutub
                        </Button>
                    )}
                >
                    <StaleBanner project={project} canManage={canManage} />
                    {errors.framework && <p role="alert" className="m-0 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.framework}</p>}
                    {framework && !approved && (
                        <FrameworkSuggestion
                            framework={framework}
                            canManage={editable}
                            busy={actions.busy}
                            validationError={errors.ai}
                            onSuggest={actions.suggest}
                            onAccept={actions.acceptSuggestion}
                            onReject={actions.rejectSuggestion}
                        />
                    )}
                    {framework?.poleChecks?.map((check) => (
                        <p key={check.message} role="status" className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">{check.message}</p>
                    ))}
                    {framework ? (
                        <FrameworkMatrix axes={framework.axes} quadrants={framework.quadrants} onEdit={editable ? setQuadrant : undefined} />
                    ) : !axesApproved ? (
                        <p className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">Setujui dua critical uncertainty sebagai sumbu A dan B dulu.</p>
                    ) : (
                        <div className="flex flex-col items-center gap-3 py-8 text-center">
                            <p className="m-0 max-w-md text-sm text-muted-foreground">Kerangka memakai dua critical uncertainty yang disetujui. Empat kuadran langsung dibuat dengan nama dari kutubnya; narasi menyusul di langkah 6.</p>
                            {canManage && (
                                <Button onClick={actions.create} disabled={actions.busy}>
                                    <PlusIcon aria-hidden="true" />
                                    Buat kerangka
                                </Button>
                            )}
                        </div>
                    )}
                    <Link href={route("scenario-planning.uncertainties.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeftIcon aria-hidden="true" className="size-4" />
                        Critical uncertainties
                    </Link>
                </Panel>

                <div className="flex flex-col gap-4">
                    {framework && (
                        <Panel title="Persetujuan kerangka (G5)">
                            {approved ? (
                                <p className="m-0 text-sm text-muted-foreground">
                                    Disetujui oleh {framework.approved_by} pada {new Date(framework.approved_at).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" })}. Kutub dan nama kuadran terkunci.
                                </p>
                            ) : (
                                <p className="m-0 text-sm text-muted-foreground">Setelah disetujui, kutub dan nama kuadran terkunci dan langkah 5–9 terbuka.</p>
                            )}
                            {canManage && (approved ? (
                                <ConfirmDialog
                                    title="Buka kembali kerangka?"
                                    description="Kerangka kembali menjadi draf dan langkah 5–9 terkunci lagi sampai disetujui ulang."
                                    confirmText="Buka kembali"
                                    onConfirm={actions.reopen}
                                    withRationale
                                >
                                    <Button size="sm" variant="outline">
                                        <LockOpenIcon aria-hidden="true" />
                                        Buka kembali
                                    </Button>
                                </ConfirmDialog>
                            ) : (
                                <ConfirmDialog title="Setujui kerangka (G5)?" description="Kutub dan nama kuadran terkunci, dan langkah 5–9 terbuka." confirmText="Setujui" variant="default" onConfirm={actions.approve} withRationale>
                                    <Button size="sm" disabled={actions.busy}><CircleCheckIcon aria-hidden="true" />Setujui kerangka</Button>
                                </ConfirmDialog>
                            ))}
                            {approved && (
                                <Link href={route("scenario-planning.conditions.index", project.id)} className="text-sm font-medium text-primary hover:underline">
                                    Lanjut: kondisi tiap skenario
                                </Link>
                            )}
                            {approved && (
                                <Link href={route("scenario-planning.narratives.index", project.id)} className="text-sm text-primary hover:underline">
                                    Narasi skenario (langkah 6)
                                </Link>
                            )}
                        </Panel>
                    )}
                    <Panel title="Aturan">
                        <ul className="m-0 flex list-disc flex-col gap-1 pl-4 text-xs text-muted-foreground">
                            <li>Sumbu A dan B adalah dua critical uncertainty yang disetujui.</li>
                            <li>Kuadran bernama "{"{kutub A} · {kutub B}"}" sampai tim menggantinya; nama harus berbeda.</li>
                            <li>Kedua kutub satu sumbu harus berbeda; kutub yang sama di dua sumbu diberi peringatan.</li>
                            <li>Membatalkan persetujuan sumbu mengembalikan kerangka ke draf.</li>
                        </ul>
                    </Panel>
                </div>
            </main>

            {framework && (
                <PolesDialog open={editingPoles} axes={framework.axes} loading={actions.busy} onOpenChange={setEditingPoles} onSave={actions.savePoles} />
            )}
            <QuadrantDialog
                quadrant={quadrant}
                loading={actions.busy}
                onOpenChange={(open) => !open && setQuadrant(null)}
                onSave={(values) => actions.saveQuadrant(quadrant.id, values)}
            />
        </AuthenticatedLayout>
    );
}
