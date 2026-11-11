/**
 * What the firm reports show: the whole firm (default) or one Key Focal Issue (a scenario planning
 * exercise). Mirrors App\Support\ReportScope. Pure data, no React.
 */
export const FIRM = "firm";

/** The scope to start from: the requested one when it is a known exercise, otherwise the firm. */
export function initialScope(requested, scopes = []) {
    return scopes.some((scope) => scope.id === requested) ? requested : FIRM;
}

/** Short label for headers and chips. */
export function scopeLabel(scope, scopes = []) {
    if (scope === FIRM) return "FIRM";
    const exercise = scopes.find((item) => item.id === scope);
    return exercise ? `Key Focal Issue · ${exercise.name}` : "FIRM";
}

/** Why an exercise's numbers may still move: its scores are not agreed yet (gate G3). */
export function scopeHint(exercise) {
    return exercise && !exercise.scores_confirmed ? "skor belum disepakati (G3)" : null;
}

/** Sentence for the forces a Key Focal Issue view left out (no consensus score, or no horizon on the radar). */
export function skippedNote(count, { radar = false } = {}) {
    if (!count) return null;
    return radar
        ? `${count} driving force latihan ini tidak ditampilkan: belum punya skor konsensus atau belum punya time horizon di rating firm.`
        : `${count} driving force latihan ini tidak ditampilkan karena belum punya skor konsensus.`;
}
