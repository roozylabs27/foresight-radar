import { Head, Link } from "@inertiajs/react";
import { CalendarIcon, PlusIcon, TargetIcon } from "lucide-react";

import FormDialog from "@/Components/FormDialog";
import Panel from "@/Components/Panel";
import ProjectForm from "@/Components/ScenarioPlanning/ProjectForm";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { PROJECT_STATUSES } from "@/domain/scenarioPlanning";
import { useProjectDialog } from "@/hooks/use-project-dialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

const FORM_ID = "scenario-planning-project-form";

/** Scenario planning exercises of the organization (roadmap R0.1). */
export default function Index({ auth, title, projects, canManage, owners }) {
    const dialog = useProjectDialog({ method: "post", url: route("scenario-planning.store") });

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <TargetIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                </div>
            }
        >
            <Head title={title} />
            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                <Panel
                    title="Latihan scenario planning"
                    description="Setiap latihan menjawab satu key focal issue melalui sembilan langkah metode."
                    badge={canManage && (
                        <Button size="sm" onClick={() => dialog.setOpen(true)}>
                            <PlusIcon aria-hidden="true" />
                            Latihan baru
                        </Button>
                    )}
                >
                    {projects.length === 0 ? (
                        <p className="m-0 py-6 text-center text-sm text-muted-foreground">
                            Belum ada latihan. {canManage ? "Mulai dengan menuliskan key focal issue." : "Hubungi koordinator untuk membuat latihan."}
                        </p>
                    ) : (
                        <ul className="m-0 grid list-none gap-3 p-0 md:grid-cols-2">
                            {projects.map((project) => (
                                <li key={project.id}>
                                    <Link
                                        href={route("scenario-planning.show", project.id)}
                                        className="flex h-full flex-col gap-2 rounded-lg border border-border bg-card p-4 transition-colors hover:border-primary/50"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="text-sm font-semibold text-foreground">{project.name}</span>
                                            <span className={statusBadge({ tone: PROJECT_STATUSES[project.status]?.tone })}>{PROJECT_STATUSES[project.status]?.label ?? project.status}</span>
                                        </div>
                                        <p className="m-0 line-clamp-2 text-sm text-muted-foreground">{project.focal_question}</p>
                                        <span className="mt-auto inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <CalendarIcon aria-hidden="true" className="size-3.5" />
                                            Tahun target {project.target_year}
                                            {project.confirmed_at ? " · focal issue dikonfirmasi" : " · menunggu konfirmasi"}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </main>

            <FormDialog
                title="Latihan scenario planning baru"
                description="Langkah 1: tuliskan key focal issue, tujuan, dan tahun target. Isian lain boleh dilengkapi nanti."
                open={dialog.open}
                onOpenChange={dialog.setOpen}
                formId={FORM_ID}
                btnText="Buat latihan"
                cancelText="Batal"
                loading={dialog.saving}
                className="sm:max-w-2xl"
            >
                {dialog.open && <ProjectForm id={FORM_ID} owners={owners} loading={dialog.saving} onSubmit={dialog.submit} />}
            </FormDialog>
        </AuthenticatedLayout>
    );
}
