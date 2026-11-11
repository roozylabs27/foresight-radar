/**
 * Dimension colours (plan part B): an organisation picks a key per dimension (App\Models\Dimension::COLORS);
 * DimensionChip picks one for dimensions without. Each key is a CSS token `--dimension-<key>`
 * defined for light and dark in resources/css/app.css. Pure data, no React.
 */
export const DIMENSION_COLORS = {
    blue: "Biru",
    teal: "Teal",
    green: "Hijau",
    lime: "Lime",
    amber: "Kuning",
    orange: "Oranye",
    red: "Merah",
    pink: "Merah muda",
    purple: "Ungu",
    indigo: "Indigo",
    cyan: "Sian",
    slate: "Abu-abu",
};

/** CSS colour value for a key. */
export function dimensionColor(key) {
    return `var(--dimension-${DIMENSION_COLORS[key] ? key : "slate"})`;
}
