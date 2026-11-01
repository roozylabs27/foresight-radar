import { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeftIcon, ArrowRightIcon, PlusIcon, SplitIcon } from "lucide-react";

import Panel from "@/Components/Panel";
import ApproveUncertaintyDialog from "@/Components/ScenarioPlanning/ApproveUncertaintyDialog";
import PairSuggestions from "@/Components/ScenarioPlanning/PairSuggestions";
import UncertaintyCard from "@/Components/ScenarioPlanning/UncertaintyCard";
import UncertaintyFormDialog from "@/Components/ScenarioPlanning/UncertaintyFormDialog";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { useCriticalUncertainties } from "@/hooks/use-critical-uncertainties";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/** Step 3, second half: propose critical uncertainties and approve two as axes (gate G4, roadmap R0.4). */
export default function Uncertainties({ auth, title, project, uncertainties, candidates, pairs, threshold, canManage, errors = {} }) {
    const actions = useCriticalUncertainties(project.id);
    const [form, setForm] = useState({ open: false, uncertainty: null });
    const [approving, setApproving] = useState(null);
    const approved = uncertainties.filter((item) => item.status === "approved");
    const locked = !project.scores_confirmed_at;

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <SplitIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Langkah 3 · Pilih Dua Critical Uncertainty</h1>
                    <span className={statusBadge({ tone: approved.length === 2 ? "approved" : "pending" })}>{approved.length} dari 2 disetujui</span>
                </div>
            }
        >
            <Head title={title} />
            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-6 lg:py-5">
                <Panel
                    title={`Usulan critical uncertainty (${uncertainties.length})`}
                    description={project.focal_question}
                    badge={canManage && !locked && (
                        <Button size="sm" onClick={() => setForm({ open: true, uncertainty: null })}>
                            <PlusIcon aria-hidden="true" />
                            Usulan baru
                        </Button>
                    )}
                >
                    {locked && <p className="m-0 mb-3 rounded-md bg-warning-soft p-3 text-sm text-warning">Sepakati skor di halaman skor dulu sebelum mengusulkan critical uncertainty.</p>}
                    {(errors.name || errors.forces) && <p role="alert" className="m-0 mb-3 rounded-md bg-danger-soft p-3 text-sm text-danger">{errors.name ?? errors.forces}</p>}
                    {uncertainties.length === 0 ? (
                        <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada usulan. Pilih dari kandidat di zona kanan atas matriks.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {uncertainties.map((item) => (
                                <UncertaintyCard
                                    key={item.id}
                                    uncertainty={item}
                                    canManage={canManage}
                                    locked={locked || approved.length >= 2}
                                    onEdit={() => setForm({ open: true, uncertainty: item })}
                                    onRemove={() => actions.remove(item.id)}
                                    onApprove={() => setApproving(item)}
                                    onWithdraw={() => actions.withdraw(item.id)}
                                />
                            ))}
                        </div>
                    )}
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                        <Link href={route("scenario-planning.assessment.index", project.id)} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            <ArrowLeftIcon aria-hidden="true" className="size-4" />
                            Skor dan matriks
                        </Link>
                        {approved.length === 2 && (
                            <Link href={route("scenario-planning.framework.index", project.id)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                                Lanjut: kerangka skenario
                                <ArrowRightIcon aria-hidden="true" className="size-4" />
                            </Link>
                        )}
                    </div>
                </Panel>

                <div className="flex flex-col gap-4">
                    <Panel title="Pasangan sumbu" description="Sumbu yang baik sama-sama kuat dan tidak saling mendorong. Keputusan tetap di tangan tim.">
                        <PairSuggestions pairs={pairs} />
                    </Panel>
                    <Panel title="Aturan">
                        <ul className="m-0 flex list-disc flex-col gap-1 pl-4 text-xs text-muted-foreground">
                            <li>Tepat dua critical uncertainty disetujui, sebagai sumbu A dan B.</li>
                            <li>Satu critical uncertainty boleh menggabungkan beberapa driving force.</li>
                            <li>Driving force predetermined tidak dipakai sebagai sumbu.</li>
                            <li>Mengubah skor, daftar driving force, atau focal issue membatalkan persetujuan.</li>
                        </ul>
                    </Panel>
                </div>
            </main>

            <UncertaintyFormDialog
                open={form.open}
                uncertainty={form.uncertainty}
                candidates={candidates}
                threshold={threshold}
                loading={actions.busy}
                onOpenChange={(open) => setForm((current) => ({ ...current, open }))}
                onSave={(values) => actions.save(values, form.uncertainty?.id)}
            />
            <ApproveUncertaintyDialog
                uncertainty={approving}
                takenAxes={approved.map((item) => item.axis)}
                loading={actions.busy}
                onOpenChange={(open) => !open && setApproving(null)}
                onApprove={(axis, acknowledge) => actions.approve(approving.id, axis, acknowledge)}
            />
        </AuthenticatedLayout>
    );
}
