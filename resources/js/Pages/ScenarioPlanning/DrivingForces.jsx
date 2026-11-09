import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, CircleCheckIcon, PlusIcon, ZapIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import Panel from "@/Components/Panel";
import ExerciseStepper from "@/Components/ScenarioPlanning/ExerciseStepper";
import AddForcesDialog from "@/Components/ScenarioPlanning/AddForcesDialog";
import CandidateForces from "@/Components/ScenarioPlanning/CandidateForces";
import CoverageChips from "@/Components/ScenarioPlanning/CoverageChips";
import EditForceDialog from "@/Components/ScenarioPlanning/EditForceDialog";
import ProjectForceList from "@/Components/ScenarioPlanning/ProjectForceList";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { useProjectForces } from "@/hooks/use-project-forces";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Step 2 of an exercise: its driving force list and gate G2 (roadmap R0.2), with AI candidate forces (R2.4). */
export default function DrivingForces({ auth, title, project, steps, forces, library, coverage, candidates, candidateStatus, candidateError, canManage, errors = {} }) {
    const actions = useProjectForces(project.id);
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState(null);
    const included = forces.filter((link) => link.inclusion_status === "included").length;
    const locked = !project.confirmed_at;

    return (
        <AuthenticatedLayout
            auth={auth}
            subheader={<ExerciseStepper project={project} steps={steps} current={2} />}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <ZapIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 2 · Driving Forces</h1>
                    <span className={statusBadge({ tone: project.forces_confirmed_at ? "approved" : "pending" })}>
                        {project.forces_confirmed_at ? "Daftar disepakati" : "Belum disepakati"}
                    </span>
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-6 lg:py-5">
                <Panel
                    title={`Driving force latihan (${included} disertakan)`}
                    description={project.focal_question}
                    badge={canManage && !locked && (
                        <Button size="sm" onClick={() => setAdding(true)}>
                            <PlusIcon aria-hidden="true" />
                            Tambah dari pustaka
                        </Button>
                    )}
                >
                    {locked && <p className="m-0 mb-3 rounded-md bg-warning-soft p-3 text-sm text-warning">Konfirmasi focal issue dulu di halaman latihan sebelum menyusun driving force.</p>}
                    {errors.forces && <p role="alert" className="m-0 mb-3 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.forces}</p>}
                    <ProjectForceList forces={forces} canManage={canManage} onEdit={setEditing} onRemove={(link) => actions.remove(link.id)} />
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <Link href={route("scenario-planning.show", project.id)} className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            {project.name}
                        </Link>
                        <Link href={route("driving-force.")} className="text-primary hover:underline">Buat driving force baru di modul Driving Force</Link>
                    </div>
                </Panel>

                <div className="flex flex-col gap-4">
                    <Panel title="Kesepakatan daftar (G2)" description="Disepakati bersama tim lintas fungsi. Mengubah status disertakan/dikeluarkan setelahnya membatalkan kesepakatan.">
                        {project.forces_confirmed_at ? (
                            <p className="m-0 text-sm text-foreground">Disepakati oleh {project.forces_confirmed_by}, {new Date(project.forces_confirmed_at).toLocaleDateString("id-ID")}. Langkah 3–9 terbuka.</p>
                        ) : canManage ? (
                            <ConfirmDialog
                                title="Sepakati daftar driving force?"
                                description={`${included} driving force disertakan. Langkah 3–9 akan terbuka.`}
                                confirmText="Sepakati"
                                variant="default"
                                onConfirm={actions.confirm}
                            >
                                <Button size="sm" disabled={locked || included === 0}>
                                    <CircleCheckIcon aria-hidden="true" />
                                    Sepakati daftar
                                </Button>
                            </ConfirmDialog>
                        ) : (
                            <p className="m-0 text-sm text-muted-foreground">Menunggu koordinator menyepakati daftar.</p>
                        )}
                    </Panel>
                    <CandidateForces
                        candidates={candidates}
                        status={candidateStatus}
                        error={candidateError}
                        validationError={errors.ai}
                        canManage={canManage}
                        locked={locked}
                        agreed={Boolean(project.forces_confirmed_at)}
                        busy={actions.busy}
                        onSuggest={actions.suggest}
                        onAccept={actions.acceptCandidate}
                        onReject={actions.rejectCandidate}
                    />
                    <Panel title="Cakupan kategori" description="Jumlah driving force yang disertakan per kategori. Kategori kosong ditandai.">
                        <CoverageChips coverage={coverage} />
                    </Panel>
                </div>
            </main>

            <AddForcesDialog open={adding} onOpenChange={setAdding} library={library} loading={actions.busy} agreed={Boolean(project.forces_confirmed_at)} onAdd={actions.add} />
            <EditForceDialog link={editing} onOpenChange={(open) => !open && setEditing(null)} loading={actions.busy} onSave={(values) => actions.update(editing.id, values)} />
        </AuthenticatedLayout>
    );
}
