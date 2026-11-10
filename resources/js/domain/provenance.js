/**
 * Origin and review vocabulary shared by every scenario planning artefact (roadmap R0.6).
 * Mirrors App\Support\Provenance; review states cover `review_status` and the tables that use
 * their own decision column (critical uncertainty / response `status`). Pure data, no React.
 */

/** Where an artefact came from. `human` is the normal case and shows no badge. */
export const ORIGINS = {
    ai: { label: "Dibuat AI", tone: "ai" },
    template: { label: "Kerangka tanpa AI", tone: "neutral" },
    library: { label: "Dari pustaka", tone: "neutral" },
    migrated: { label: "Dari narasi", tone: "pending" },
    external: { label: "Sumber eksternal", tone: "neutral" },
    human: null,
};

/** Whether a person has accepted it. */
export const REVIEW_STATES = {
    proposed: { label: "Usulan", tone: "pending" },
    draft: { label: "Draf", tone: "neutral" },
    reviewed: { label: "Ditinjau", tone: "pending" },
    accepted: { label: "Diterima", tone: "approved" },
    approved: { label: "Disetujui", tone: "approved" },
    rejected: { label: "Ditolak", tone: "closed" },
    retired: { label: "Tidak dipakai", tone: "closed" },
};
