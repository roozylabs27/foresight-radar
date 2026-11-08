/**
 * Scenario planning exercise vocabulary (roadmap R0.1). Pure data and helpers,
 * no React or Inertia. Mirrors App\Models\ScenarioPlanningProject and
 * App\Services\ScenarioPlanningProjectService::STEPS.
 */

/** @type {Record<string, { label: string, tone: string }>} */
export const PROJECT_STATUSES = {
    draft: { label: "Draf", tone: "pending" },
    active: { label: "Aktif", tone: "approved" },
    monitoring: { label: "Pemantauan", tone: "approved" },
    archived: { label: "Diarsipkan", tone: "closed" },
};

/** @type {Record<string, { label: string, tone: string }>} */
export const STEP_STATUSES = {
    done: { label: "Selesai", tone: "approved" },
    in_progress: { label: "Sedang dikerjakan", tone: "pending" },
    available: { label: "Terbuka", tone: "neutral" },
    locked: { label: "Terkunci", tone: "closed" },
};

/** Per-exercise status of a driving force (App\Models\ProjectDrivingForce::STATUSES). */
export const INCLUSION_STATUSES = {
    included: { label: "Disertakan", tone: "approved" },
    proposed: { label: "Usulan", tone: "pending" },
    excluded: { label: "Dikeluarkan", tone: "closed" },
};

/** Coverage rows grouped by environment level, keeping the method order. */
export function groupCoverage(coverage) {
    return coverage.reduce((groups, row) => {
        const key = row.environment ?? "Lainnya";
        (groups[key] ??= []).push(row);
        return groups;
    }, {});
}

/** Library forces whose keyword, category or description contain the query. */
export function filterLibrary(library, query) {
    const needle = query.trim().toLowerCase();
    if (!needle) return library;
    return library.filter((force) => [force.keyword, force.dimension, force.description].some((text) => String(text ?? "").toLowerCase().includes(needle)));
}

/**
 * Where a scored force falls on the uncertainty × impact matrix (slide 19). `threshold` is the
 * first "high" score (App\Services\ScenarioPlanningProjectService::HIGH_SCORE).
 *   candidate:     high impact, high uncertainty (axis candidate)
 *   predetermined: high impact, low uncertainty (holds in every scenario)
 *   watch:         low impact, high uncertainty
 *   minor:         low impact, low uncertainty
 */
export function classifyScore(impact, uncertainty, threshold = 6) {
    if (impact == null || uncertainty == null) return "unscored";
    const highImpact = impact >= threshold;
    const highUncertainty = uncertainty >= threshold;
    if (highImpact && highUncertainty) return "candidate";
    if (highImpact) return "predetermined";
    if (highUncertainty) return "watch";
    return "minor";
}

/** Gate decision actions in the decision log (App\Models\ProjectDecision). */
export const DECISION_ACTIONS = {
    approved: { label: "Disetujui", tone: "approved" },
    rejected: { label: "Ditolak", tone: "rejected" },
    withdrawn: { label: "Dibatalkan", tone: "pending" },
};

/** Exercise participant roles (App\Models\ProjectParticipant); viewers only read. */
export const PARTICIPANT_ROLES = {
    coordinator: { label: "Koordinator" },
    contributor: { label: "Kontributor" },
    expert: { label: "Pakar" },
    viewer: { label: "Pengamat" },
};

/** "6–9 (median 7,5)" for one axis of the participants' scores (roadmap R2.1). */
export function formatSpread(axis) {
    if (!axis) return "–";
    const median = String(axis.median).replace(".", ",");
    return axis.min === axis.max ? `${axis.min}` : `${axis.min}–${axis.max} (median ${median})`;
}

/** The consensus suggested by the participants: medians rounded half up, or null without scores. */
export function medianScores(spread) {
    if (!spread?.count) return null;
    return { impact: Math.round(spread.impact.median), uncertainty: Math.round(spread.uncertainty.median) };
}

/** @type {Record<string, { label: string, tone: string }>} */
export const SCORE_CLASSES = {
    candidate: { label: "Kandidat critical uncertainty", tone: "approved" },
    predetermined: { label: "Kandidat predetermined", tone: "pending" },
    watch: { label: "Pantau", tone: "neutral" },
    minor: { label: "Dampak rendah", tone: "closed" },
    unscored: { label: "Belum diberi skor", tone: "rejected" },
};

/**
 * Matrix points: forces with both scores, nudged apart when several share a cell so every
 * number stays readable.
 */
export function matrixPoints(forces) {
    const cells = {};
    return forces
        .filter((force) => force.impact != null && force.uncertainty != null)
        .map((force) => {
            const key = `${force.uncertainty}-${force.impact}`;
            const index = (cells[key] = (cells[key] ?? -1) + 1);
            const angle = index * 2.1;
            const offset = index === 0 ? 0 : 0.28;
            return { ...force, x: force.uncertainty + offset * Math.cos(angle), y: force.impact + offset * Math.sin(angle) };
        });
}

/** What each method step is for, in one sentence (from the methodology deck). */
export const STEP_PURPOSES = {
    focal_issue: "Pertanyaan strategis, tujuan, dan tahun target latihan.",
    driving_forces: "Kekuatan makro dan mezzo yang memengaruhi focal issue.",
    critical_uncertainties: "Skor ketidakpastian dan dampak, lalu pilih dua yang paling kritis.",
    scenario_framework: "Dua sumbu dengan dua kutub masing-masing menjadi matriks 2×2.",
    develop_scenarios: "Kondisi yang berlaku di tiap skenario.",
    narratives: "Cerita masa depan untuk tiap skenario.",
    challenges_opportunities: "Peluang dan tantangan di tiap skenario.",
    strategic_response: "Respons strategis untuk peluang dan tantangan.",
    early_warning: "Indikator yang menunjukkan skenario mana yang mulai terjadi.",
};

/** Valid target years: the method looks ahead, so next year at the earliest. */
export function targetYearBounds(today = new Date()) {
    return { min: today.getFullYear() + 1, max: 2100 };
}

/** Years left until the target year, never negative. */
export function yearsAhead(targetYear, today = new Date()) {
    return Math.max(0, Number(targetYear) - today.getFullYear());
}

/** Kinds of a step 5 condition (App\Models\ScenarioCondition::KINDS). */
export const CONDITION_KINDS = {
    pole_expression: { label: "Kutub sumbu", hint: "Bagaimana kutub sumbu terlihat di masa depan ini." },
    force_behaviour: { label: "Perilaku driving force", hint: "Bagaimana driving force lain (bukan sumbu) berperilaku." },
    assumption: { label: "Asumsi", hint: "Asumsi lain yang berlaku di masa depan ini." },
};

/** Step 7 item types and impact levels (App\Models\ScenarioImplication). */
export const IMPLICATION_TYPES = {
    opportunity: { label: "Peluang", tone: "approved" },
    challenge: { label: "Tantangan", tone: "rejected" },
};

export const IMPACT_LEVELS = {
    high: "Dampak tinggi",
    medium: "Dampak sedang",
    low: "Dampak rendah",
};

/** Step 8 response vocabulary (App\Models\StrategicResponse, App\Services\StrategicResponseService). */
export const RESPONSE_STATUSES = {
    proposed: { label: "Usulan", tone: "pending" },
    approved: { label: "Disetujui", tone: "approved" },
    rejected: { label: "Ditolak", tone: "closed" },
};

export const RESPONSE_FITS = {
    applies: { label: "Cocok", short: "✓", tone: "approved" },
    partial: { label: "Sebagian", short: "≈", tone: "pending" },
    not_applicable: { label: "Tidak cocok", short: "–", tone: "neutral" },
};

export const ROBUSTNESS = {
    robust: { label: "Robust", hint: "Cocok di 3 kuadran atau lebih." },
    conditional: { label: "Kondisional", hint: "Cocok di 0 atau 2 kuadran." },
    scenario_specific: { label: "Spesifik skenario", hint: "Cocok di satu kuadran saja." },
};

/** Step 9 indicator vocabulary (App\Models\EarlyWarningIndicator). */
export const INDICATOR_DIRECTIONS = {
    rising: { label: "Naik", short: "↑" },
    falling: { label: "Turun", short: "↓" },
    stable: { label: "Stabil", short: "→" },
    qualitative: { label: "Kualitatif", short: "◇" },
};

export const INDICATOR_FREQUENCIES = { monthly: "Bulanan", quarterly: "Kuartalan", ad_hoc: "Sesuai kebutuhan" };

export const INDICATOR_SOURCES = {
    manual: "Dipantau manual",
    signal_feed: "Dari feed sinyal",
    external: "Sumber eksternal",
};
