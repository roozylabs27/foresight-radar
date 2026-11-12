import { ArchiveIcon, RadarIcon, RotateCcwIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import { Button } from "@/Components/ui/button";

const MOVES = {
    monitoring: { label: "Mulai pemantauan", icon: RadarIcon, title: "Masuk tahap pemantauan?", description: "Latihan selesai disusun; tim memantau indikator peringatan dini. Langkah-langkah tetap bisa diubah." },
    archived: { label: "Arsipkan", icon: ArchiveIcon, title: "Arsipkan latihan?", description: "Latihan ditandai selesai dan tidak dipakai lagi. Datanya tetap ada dan bisa diaktifkan kembali." },
    active: { label: "Aktifkan kembali", icon: RotateCcwIcon, title: "Aktifkan kembali latihan?", description: "Latihan kembali berstatus aktif." },
};

/**
 * PRD-06: after step 9 the coordinator closes the exercise into monitoring, archives it, or makes it
 * active again. Draft is not offered: it follows from withdrawing the focal issue confirmation (G1).
 */
export default function ExerciseStatusControl({ project, steps, onChange }) {
    if (!project.confirmed_at) return null;
    const stepNineDone = steps.find((step) => step.number === 9)?.status === "done";
    const moves = {
        active: [stepNineDone && "monitoring", "archived"],
        monitoring: ["archived", "active"],
        archived: ["active"],
    }[project.status]?.filter(Boolean) ?? [];

    return moves.map((status) => {
        const { label, icon: Icon, title, description } = MOVES[status];
        return (
            <ConfirmDialog key={status} title={title} description={description} confirmText={label} variant="default" onConfirm={() => onChange(status)}>
                <Button size="sm" variant="outline">
                    <Icon aria-hidden="true" />
                    {label}
                </Button>
            </ConfirmDialog>
        );
    });
}
