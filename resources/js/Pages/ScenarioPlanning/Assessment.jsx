import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, CircleCheckIcon, GaugeIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import Panel from "@/Components/Panel";
import ScoreDialog from "@/Components/ScenarioPlanning/ScoreDialog";
import ScoreList from "@/Components/ScenarioPlanning/ScoreList";
import UncertaintyImpactMatrix from "@/Components/ScenarioPlanning/UncertaintyImpactMatrix";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { useProjectAssessment } from "@/hooks/use-project-assessment";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Step 3 of an exercise: impact × uncertainty scores, the matrix and gate G3 (roadmap R1.1). */
export default function Assessment({ auth, title, project, forces, threshold, canManage, errors = {} }) {
    const actions = useProjectAssessment(project.id);
    const [editing, setEditing] = useState(null);
    const unscored = forces.filter((item) => item.impact == null).length;
    const locked = !project.forces_confirmed_at;

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <GaugeIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 3 · Critical Uncertainties</h1>
                    <span className={statusBadge({ tone: project.scores_confirmed_at ? "approved" : "pending" })}>
                        {project.scores_confirmed_at ? "Skor disepakati" : "Skor belum disepakati"}
                    </span>
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_420px] lg:px-6 lg:py-5">
                <Panel title={`Skor driving force (${forces.length})`} description="Impact dan uncertainty 1–10 hasil kesepakatan tim untuk focal issue latihan ini.">
                    {locked && <p className="m-0 mb-3 rounded-md bg-warning-soft p-3 text-sm text-warning">Sepakati daftar driving force di langkah 2 dulu sebelum memberi skor.</p>}
                    {errors.scores && <p role="alert" className="m-0 mb-3 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.scores}</p>}
                    <ScoreList forces={forces} threshold={threshold} canManage={canManage && !locked} onEdit={setEditing} />
                    <Link href={route("scenario-planning.show", project.id)} className="mt-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeftIcon aria-hidden="true" className="size-4" />
                        {project.name}
                    </Link>
                </Panel>

                <div className="flex flex-col gap-4">
                    <Panel title="Matriks uncertainty × impact" description="Nomor sesuai daftar. Pemilihan dua critical uncertainty tetap keputusan tim, bukan otomatis skor tertinggi.">
                        <UncertaintyImpactMatrix forces={forces} threshold={threshold} />
                    </Panel>
                    <Panel title="Kesepakatan skor (G3)" description="Mengubah angka skor setelah disepakati membatalkan kesepakatan; mengubah alasan tidak.">
                        {project.scores_confirmed_at ? (
                            <div className="flex flex-col gap-3">
                                <p className="m-0 text-sm text-foreground">Disepakati oleh {project.scores_confirmed_by}, {new Date(project.scores_confirmed_at).toLocaleDateString("id-ID")}.</p>
                                <Button size="sm" asChild>
                                    <Link href={route("scenario-planning.uncertainties.index", project.id)}>Lanjut: pilih dua critical uncertainty</Link>
                                </Button>
                            </div>
                        ) : canManage ? (
                            <ConfirmDialog
                                title="Sepakati skor?"
                                description={`${forces.length} driving force sudah diberi skor. Langkah berikutnya akan terbuka.`}
                                confirmText="Sepakati"
                                variant="default"
                                onConfirm={actions.confirm}
                            >
                                <Button size="sm" disabled={locked || forces.length === 0 || unscored > 0}>
                                    <CircleCheckIcon aria-hidden="true" />
                                    {unscored > 0 ? `${unscored} belum diberi skor` : "Sepakati skor"}
                                </Button>
                            </ConfirmDialog>
                        ) : (
                            <p className="m-0 text-sm text-muted-foreground">Menunggu koordinator menyepakati skor.</p>
                        )}
                    </Panel>
                </div>
            </main>

            <ScoreDialog force={editing} onOpenChange={(open) => !open && setEditing(null)} loading={actions.busy} onSave={(values) => actions.save(editing.id, values)} />
        </AuthenticatedLayout>
    );
}
