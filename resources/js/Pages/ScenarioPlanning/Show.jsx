import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeftIcon, CircleCheckIcon, PencilIcon, TargetIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import DetailList, { DetailItem } from "@/Components/DetailList";
import FormDialog from "@/Components/FormDialog";
import Panel from "@/Components/Panel";
import DecisionLog from "@/Components/ScenarioPlanning/DecisionLog";
import ExerciseStatusControl from "@/Components/ScenarioPlanning/ExerciseStatusControl";
import FocalSuggestion from "@/Components/ScenarioPlanning/FocalSuggestion";
import ParticipantPanel from "@/Components/ScenarioPlanning/ParticipantPanel";
import ProjectForm from "@/Components/ScenarioPlanning/ProjectForm";
import StaleBanner from "@/Components/ScenarioPlanning/StaleBanner";
import NextActionCard from "@/Components/ScenarioPlanning/NextActionCard";
import StepList from "@/Components/ScenarioPlanning/StepList";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { PROJECT_STATUSES, yearsAhead } from "@/domain/scenarioPlanning";
import { useProjectDialog } from "@/hooks/use-project-dialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

const FORM_ID = "scenario-planning-project-edit-form";

const visit = (method, url) => new Promise((resolve) => router.visit(url, { method, preserveScroll: true, onFinish: resolve }));

/** Workspace of one scenario planning exercise: its focal issue (step 1) and the nine steps. */
export default function Show({ auth, title, project, steps, nextAction, lastGate, canManage, owners = [], participants = [], decisions = [], focalSuggestion, focalStatus, focalError, errors = {} }) {
    const dialog = useProjectDialog({ method: "put", url: route("scenario-planning.update", project.id) });
    const [prefill, setPrefill] = useState(null);
    const edit = (question = null) => {
        setPrefill(question);
        dialog.setOpen(true);
    };
    const status = PROJECT_STATUSES[project.status];

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <TargetIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={statusBadge({ tone: status?.tone })}>{status?.label ?? project.status}</span>
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_380px] lg:px-6 lg:py-5">
                <Panel
                    title="Langkah 1 · Key Focal Issue"
                    badge={canManage && (
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" onClick={() => edit()}>
                                <PencilIcon aria-hidden="true" />
                                Ubah
                            </Button>
                            {!project.confirmed_at && (
                                <ConfirmDialog
                                    title="Konfirmasi focal issue?"
                                    description="Setelah dikonfirmasi, langkah 2 (driving forces) terbuka. Mengubah pertanyaan, tujuan, atau tahun target nanti akan membutuhkan konfirmasi ulang."
                                    confirmText="Konfirmasi"
                                    variant="default"
                                    onConfirm={() => visit("post", route("scenario-planning.confirm", project.id))}
                                >
                                    <Button size="sm">
                                        <CircleCheckIcon aria-hidden="true" />
                                        Konfirmasi
                                    </Button>
                                </ConfirmDialog>
                            )}
                        </div>
                    )}
                >
                    <blockquote className="m-0 mb-4 border-0 text-base font-semibold text-foreground">{project.focal_question}</blockquote>
                    <FocalSuggestion projectId={project.id} suggestion={focalSuggestion} status={focalStatus} error={focalError} validationError={errors.ai} canManage={canManage} onUse={edit} />
                    <DetailList className="sm:grid-cols-2">
                        <DetailItem label="Tujuan" className="sm:col-span-2">{project.purpose}</DetailItem>
                        <DetailItem label="Tahun target">{project.target_year} ({yearsAhead(project.target_year)} tahun lagi)</DetailItem>
                        <DetailItem label="Konfirmasi">{project.confirmed_at ? `Oleh ${project.confirmed_by}, ${new Date(project.confirmed_at).toLocaleDateString("id-ID")}` : "Belum dikonfirmasi"}</DetailItem>
                        <DetailItem label="Cakupan geografis">{project.geography}</DetailItem>
                        <DetailItem label="Industri">{project.industry}</DetailItem>
                        <DetailItem label="Unit pemilik">{project.owner_unit}</DetailItem>
                        <DetailItem label="Pemilik latihan">{project.owner?.name}</DetailItem>
                        <DetailItem label="Latar belakang" className="sm:col-span-2">{project.background}</DetailItem>
                        <DetailItem label="Sasaran" className="sm:col-span-2">{project.objectives}</DetailItem>
                    </DetailList>
                    <div className="mt-4 flex items-center justify-between gap-2">
                        <Link href={route("scenario-planning.index")} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            Semua latihan
                        </Link>
                        {canManage && (
                            <ConfirmDialog
                                title="Hapus latihan ini?"
                                description="Latihan disembunyikan dari daftar. Driving force dan skenario yang sudah ada tidak ikut terhapus."
                                confirmText="Hapus"
                                onConfirm={() => visit("delete", route("scenario-planning.destroy", project.id))}
                            >
                                <Button size="sm" variant="ghost" className="text-destructive">
                                    <Trash2Icon aria-hidden="true" />
                                    Hapus
                                </Button>
                            </ConfirmDialog>
                        )}
                    </div>
                </Panel>

                <div className="flex flex-col gap-4">
                    <Panel
                        title="Sembilan langkah"
                        description={!project.confirmed_at ? "Konfirmasi focal issue untuk membuka langkah 2." : !project.forces_confirmed_at ? "Sepakati daftar driving force untuk membuka langkah 3." : "Focal issue dan daftar driving force sudah disepakati."}
                    >
                        <NextActionCard nextAction={nextAction} lastGate={lastGate} />
                        <StaleBanner project={project} canManage={canManage} />
                        {project.unreviewed_narratives > 0 && (
                            <p role="status" className="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning">
                                {project.unreviewed_narratives} narasi AI belum ditinjau.{" "}
                                <Link href={route("scenario-planning.narratives.index", project.id)} className="font-medium underline">
                                    Tinjau di langkah 6
                                </Link>
                            </p>
                        )}
                        <StepList steps={steps} />
                        {canManage && <div className="flex flex-wrap gap-2"><ExerciseStatusControl project={project} steps={steps} onChange={(to) => new Promise((resolve) => router.post(route("scenario-planning.status", project.id), { status: to }, { preserveScroll: true, onFinish: resolve }))} /></div>}
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm font-medium">
                            {project.forces_confirmed_at && <Link href={route("scenario-planning.compare", project.id)} className="text-primary hover:underline">Bandingkan empat skenario</Link>}
                            {project.forces_confirmed_at && <Link href={route("scenario-planning.backcasting.index", project.id)} className="text-primary hover:underline">Masa depan pilihan & backcasting</Link>}
                            <Link href={route("scenario-planning.report", project.id)} className="text-primary hover:underline">Laporan (cetak / PDF)</Link>
                        </div>
                    </Panel>
                    <ParticipantPanel projectId={project.id} participants={participants} owners={owners} canManage={canManage} />
                    <DecisionLog decisions={decisions} />
                </div>
            </main>

            <FormDialog title="Ubah latihan" open={dialog.open} onOpenChange={dialog.setOpen} formId={FORM_ID} isEditMode btnText="Simpan" cancelText="Batal" loading={dialog.saving} className="sm:max-w-2xl">
                {dialog.open && (
                    <ProjectForm id={FORM_ID} owners={owners} loading={dialog.saving} onSubmit={dialog.submit} initialValues={{ ...project, owner: project.owner?.id, ...(prefill ? { focal_question: prefill } : {}) }} />
                )}
            </FormDialog>
        </AuthenticatedLayout>
    );
}
