import { useState } from "react";
import { Trash2Icon, UserPlusIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import Panel from "@/Components/Panel";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { PARTICIPANT_ROLES } from "@/domain/scenarioPlanning";
import { useProjectParticipants } from "@/hooks/use-project-participants";

const EMPTY = { user: "", role: "contributor", division: "" };

function RoleSelect({ id, value, onChange, disabled, label }) {
    return (
        <Select value={value} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger id={id} className="w-full sm:w-40" aria-label={label}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {Object.entries(PARTICIPANT_ROLES).map(([role, item]) => (
                    <SelectItem key={role} value={role}>
                        {item.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Who takes part in an exercise and in which role (roadmap R2.2). The coordinator adds, re-roles
 * and removes participants; once any are listed, only contributing roles give input.
 */
export default function ParticipantPanel({ projectId, participants, owners, canManage }) {
    const actions = useProjectParticipants(projectId);
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const listed = new Set(participants.map((row) => row.user.id));
    const candidates = owners.filter((owner) => !listed.has(owner.value));

    const add = async () => {
        const result = await actions.add({ ...values, division: values.division || null });
        if (result) return setErrors(result);
        setErrors({});
        setValues(EMPTY);
    };

    return (
        <Panel
            title={`Peserta (${participants.length})`}
            description={participants.length === 0
                ? "Belum ada peserta: semua yang bisa membuka latihan boleh memberi skor sendiri."
                : "Hanya koordinator, kontributor, dan pakar yang memberi skor sendiri. Pengamat hanya membaca."}
        >
            {participants.length > 0 && (
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                    {participants.map((row) => (
                        <li key={row.id} className="flex flex-wrap items-center gap-2 rounded-md border border-border p-2">
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-sm font-medium text-foreground">{row.user.name}</span>
                                {row.division && <span className="text-xs text-muted-foreground">{row.division}</span>}
                            </div>
                            {canManage ? (
                                <>
                                    <RoleSelect id={`participant-role-${row.id}`} label={`Peran ${row.user.name}`} value={row.role} disabled={actions.busy} onChange={(role) => actions.update(row.id, { role, division: row.division })} />
                                    <ConfirmDialog title="Hapus peserta?" description={row.user.name} confirmText="Hapus" onConfirm={() => actions.remove(row.id)}>
                                        <Button size="icon" variant="ghost" className="text-destructive" aria-label={`Hapus ${row.user.name}`}>
                                            <Trash2Icon aria-hidden="true" />
                                        </Button>
                                    </ConfirmDialog>
                                </>
                            ) : (
                                <span className="text-xs text-muted-foreground">{PARTICIPANT_ROLES[row.role]?.label ?? row.role}</span>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canManage && candidates.length > 0 && (
                <div className="flex flex-col gap-2 rounded-md bg-muted/40 p-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Select value={values.user} onValueChange={(user) => setValues((current) => ({ ...current, user }))}>
                            <SelectTrigger className="w-full" aria-label="Pilih peserta">
                                <SelectValue placeholder="Pilih orang" />
                            </SelectTrigger>
                            <SelectContent>
                                {candidates.map((owner) => (
                                    <SelectItem key={owner.value} value={owner.value}>
                                        {owner.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <RoleSelect id="participant-role-new" label="Peran peserta baru" value={values.role} onChange={(role) => setValues((current) => ({ ...current, role }))} />
                    </div>
                    <Input value={values.division} onChange={(event) => setValues((current) => ({ ...current, division: event.target.value }))} maxLength={100} placeholder="Divisi (opsional)" aria-label="Divisi" />
                    {(errors.user || errors.role || errors.division) && <p role="alert" className="m-0 text-xs text-danger">{errors.user || errors.role || errors.division}</p>}
                    <Button size="sm" className="self-start" disabled={!values.user || actions.busy} onClick={add}>
                        <UserPlusIcon aria-hidden="true" />
                        Tambah peserta
                    </Button>
                </div>
            )}
        </Panel>
    );
}
