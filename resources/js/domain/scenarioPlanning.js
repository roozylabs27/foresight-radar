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
