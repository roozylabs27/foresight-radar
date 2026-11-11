import { ArchiveIcon, ArchiveRestoreIcon, ArrowDownIcon, ArrowUpIcon, PencilIcon, PlusIcon, Trash2Icon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import { dimensionColor } from "@/Components/DimensionChip";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";

function IconButton({ label, onClick, disabled, children, className }) {
    return (
        <Button type="button" size="icon" variant="ghost" aria-label={label} title={label} disabled={disabled} onClick={onClick} className={cn("size-8", className)}>
            {children}
        </Button>
    );
}

/** One environment with its dimensions, in order, and the actions on them. */
export default function EnvironmentSection({ environment, first, last, busy, actions }) {
    const dimensions = environment.dimensions;

    return (
        <section aria-labelledby={`environment-${environment.id}`} className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-2xs">
            <header className="flex flex-wrap items-center gap-2">
                <h3 id={`environment-${environment.id}`} className="m-0 text-sm font-semibold text-foreground">
                    {environment.name}
                </h3>
                <span className="text-xs text-muted-foreground">{dimensions.length} dimensi</span>
                <span className="ml-auto flex items-center gap-0.5">
                    <IconButton label={`Naikkan ${environment.name}`} disabled={first || busy} onClick={() => actions.moveEnvironment(environment.id, "up")}>
                        <ArrowUpIcon aria-hidden="true" />
                    </IconButton>
                    <IconButton label={`Turunkan ${environment.name}`} disabled={last || busy} onClick={() => actions.moveEnvironment(environment.id, "down")}>
                        <ArrowDownIcon aria-hidden="true" />
                    </IconButton>
                    <IconButton label={`Ubah ${environment.name}`} onClick={() => actions.editEnvironment(environment)}>
                        <PencilIcon aria-hidden="true" />
                    </IconButton>
                    <ConfirmDialog title={`Hapus lingkungan ${environment.name}?`} confirmText="Hapus" onConfirm={() => actions.deleteEnvironment(environment.id)}>
                        <IconButton label={`Hapus ${environment.name}`} disabled={dimensions.length > 0 || busy} className="text-destructive">
                            <Trash2Icon aria-hidden="true" />
                        </IconButton>
                    </ConfirmDialog>
                    <Button type="button" size="sm" variant="outline" onClick={() => actions.addDimension(environment.id)}>
                        <PlusIcon aria-hidden="true" />
                        Dimensi
                    </Button>
                </span>
            </header>

            {dimensions.length === 0 ? (
                <p className="m-0 text-xs text-muted-foreground">Belum ada dimensi. Lingkungan kosong bisa dihapus.</p>
            ) : (
                <ol className="m-0 flex list-none flex-col divide-y divide-border p-0">
                    {dimensions.map((dimension, index) => (
                        <li key={dimension.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                            <span aria-hidden="true" className="size-3 shrink-0 rounded-full" style={{ backgroundColor: dimensionColor(dimension.name, dimension.color) }} />
                            <span className={cn("text-sm font-medium", dimension.archived ? "text-muted-foreground line-through" : "text-foreground")}>{dimension.name}</span>
                            <code className="text-[11px] text-muted-foreground">{dimension.code}</code>
                            {dimension.archived && <span className={statusBadge({ tone: "neutral" })}>Diarsipkan</span>}
                            <span className="text-xs text-muted-foreground">{dimension.in_use > 0 ? `${dimension.in_use} data` : "belum dipakai"}</span>
                            <span className="ml-auto flex items-center gap-0.5">
                                <IconButton label={`Naikkan ${dimension.name}`} disabled={index === 0 || busy} onClick={() => actions.moveDimension(dimension.id, "up")}>
                                    <ArrowUpIcon aria-hidden="true" />
                                </IconButton>
                                <IconButton label={`Turunkan ${dimension.name}`} disabled={index === dimensions.length - 1 || busy} onClick={() => actions.moveDimension(dimension.id, "down")}>
                                    <ArrowDownIcon aria-hidden="true" />
                                </IconButton>
                                <IconButton label={`Ubah ${dimension.name}`} onClick={() => actions.editDimension(dimension, environment.id)}>
                                    <PencilIcon aria-hidden="true" />
                                </IconButton>
                                <IconButton
                                    label={dimension.archived ? `Pakai lagi ${dimension.name}` : `Arsipkan ${dimension.name}`}
                                    disabled={busy}
                                    onClick={() => actions.archiveDimension(dimension.id, !dimension.archived)}
                                >
                                    {dimension.archived ? <ArchiveRestoreIcon aria-hidden="true" /> : <ArchiveIcon aria-hidden="true" />}
                                </IconButton>
                                <ConfirmDialog title={`Hapus dimensi ${dimension.name}?`} description="Dimensi yang belum dipakai dihapus permanen." confirmText="Hapus" onConfirm={() => actions.deleteDimension(dimension.id)}>
                                    <IconButton
                                        label={dimension.in_use > 0 ? `${dimension.name} masih dipakai; arsipkan saja` : `Hapus ${dimension.name}`}
                                        disabled={dimension.in_use > 0 || busy}
                                        className="text-destructive"
                                    >
                                        <Trash2Icon aria-hidden="true" />
                                    </IconButton>
                                </ConfirmDialog>
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
