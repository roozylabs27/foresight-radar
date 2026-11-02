/**
 * Layout for the Foresight Radar: polar coordinates in "radius units" (0 at
 * the centre, RADAR_MAX_RADIUS at the edge) and degrees (0 = east, counter-
 * clockwise), moved here unchanged from the ECharts version of Radar.jsx.
 */

export const RADAR_MAX_RADIUS = 9;

/** The "FIRM" disc at the centre covers the first ring (radius units). */
export const CORE_RADIUS = 1;

/** Closest a marker centre may come to the centre, so a marker (triangles included) clears the core. */
const MIN_MARKER_RADIUS = 2.2;

/**
 * The eleven categories of the scenario planning method and the polar angle their sector is
 * centred on, 360/11 degrees apart: macro (six) on the left half, mezzo (five) on the right.
 */
export const DIMENSION_CONFIGS = [
    { name: "Political", angle: 98.2 },
    { name: "Economic", angle: 130.9 },
    { name: "Social", angle: 163.6 },
    { name: "Technology", angle: 196.4 },
    { name: "Ecology", angle: 229.1 },
    { name: "Legislative", angle: 261.8 },
    { name: "Substitute", angle: 294.5 },
    { name: "New Entrant", angle: 327.3 },
    { name: "Competitor", angle: 0 },
    { name: "Supply Chain", angle: 32.7 },
    { name: "Consumer", angle: 65.5 },
];

/** Concentric bands, inner to outer, in radius units. */
export const HORIZON_BANDS = [
    { horizon: 1, label: "Short Term (0 - 1 yr)", from: 0, to: 4 },
    { horizon: 2, label: "Mid Term (1 - 3 yrs)", from: 4, to: 6 },
    { horizon: 3, label: "Long Term (> 3 yrs)", from: 6, to: 8 },
];

const DIMENSION_ANGLES = Object.fromEntries(DIMENSION_CONFIGS.map((dimension) => [dimension.name, dimension.angle]));

export function horizonLabel(horizon) {
    return HORIZON_BANDS.find((band) => band.horizon === horizon)?.label ?? HORIZON_BANDS[1].label;
}

/** Polar (radius units, degrees) to SVG coordinates, y pointing down. */
export function polarToXY(radius, angle, scale, centre) {
    const radians = (angle * Math.PI) / 180;
    return {
        x: centre + radius * scale * Math.cos(radians),
        y: centre - radius * scale * Math.sin(radians),
    };
}

/**
 * Anti-collision layout: groups points by dimension and time horizon, fans
 * them out within the dimension's sector, then runs a relaxation pass so
 * markers in the same dimension do not overlap.
 */
export function computeAntiCollisionPoints(items) {
    if (!items || items.length === 0) return [];

    const groups = {};

    items.forEach((item) => {
        // 1 = Short Term, 2 = Mid Term, 3 = Long Term
        let horizon = 2;
        if (item.short_term === "✔" || item.time_horizon_id === 1) {
            horizon = 1;
        } else if (item.long_term === "✔" || item.time_horizon_id === 3) {
            horizon = 3;
        } else if (item.mid_term === "✔" || item.time_horizon_id === 2) {
            horizon = 2;
        } else if (Array.isArray(item.value)) {
            const val = item.value.find((v) => v > 0);
            if (val && val <= 4) horizon = 1;
            else if (val && val <= 6) horizon = 2;
            else if (val) horizon = 3;
        }

        // Base radius for the horizon band
        let baseRadius = horizon === 1 ? 2.8 : horizon === 2 ? 5.4 : 7.6;

        // Use the backend's per-item value when present
        if (Array.isArray(item.value)) {
            const rawVal = item.value.find((v) => v > 0);
            if (rawVal) {
                if (horizon === 1) {
                    baseRadius = 2.3 + ((rawVal - 2) / 2) * 1.3;
                } else if (horizon === 2) {
                    baseRadius = 4.7 + ((rawVal - 5) / 1) * 1.2;
                } else {
                    baseRadius = 7.1 + ((rawVal - 7) / 1) * 1.2;
                }
            }
        }

        const key = `${item.dimension}_${horizon}`;
        if (!groups[key]) groups[key] = [];
        groups[key].push({ ...item, horizon, baseRadius });
    });

    const dispersed = [];

    // Fan out the points of each cluster
    Object.values(groups).forEach((groupItems) => {
        const centerAngle = DIMENSION_ANGLES[groupItems[0].dimension] ?? 0;
        const count = groupItems.length;

        let angleOffsets = [0];
        let radiusOffsets = [0];

        // Sectors are about 32.7 degrees wide, so a fan stays within +/-13 degrees of the centre.
        if (count === 2) {
            angleOffsets = [-8, 8];
            radiusOffsets = [-0.25, 0.25];
        } else if (count === 3) {
            angleOffsets = [-11, 0, 11];
            radiusOffsets = [0.35, -0.35, 0.35];
        } else if (count === 4) {
            angleOffsets = [-12, -4, 4, 12];
            radiusOffsets = [-0.35, 0.35, -0.35, 0.35];
        } else if (count > 4) {
            // Even fan across up to 26 degrees
            const span = 26;
            const step = span / (count - 1);
            angleOffsets = groupItems.map((_, i) => -span / 2 + i * step);
            radiusOffsets = groupItems.map((_, i) => (i % 2 === 0 ? -0.35 : 0.35));
        }

        groupItems.forEach((item, idx) => {
            dispersed.push({
                ...item,
                angle: (centerAngle + angleOffsets[idx] + 360) % 360,
                radius: Math.max(MIN_MARKER_RADIUS, Math.min(8.6, item.baseRadius + radiusOffsets[idx])),
                centerAngle,
            });
        });
    });

    // Relaxation pass so no two markers overlap, also across neighbouring sectors near the centre.
    // Overlapping markers push each other apart along the line between them; each stays inside its own
    // sector and horizon band, so its position keeps its meaning.
    const MIN_DIST = 1.15;
    for (let iter = 0; iter < 80; iter++) {
        let hasOverlap = false;
        for (let i = 0; i < dispersed.length; i++) {
            for (let j = i + 1; j < dispersed.length; j++) {
                const p1 = dispersed[i];
                const p2 = dispersed[j];
                const a = toXY(p1);
                const b = toXY(p2);
                let dx = b.x - a.x;
                let dy = b.y - a.y;
                let dist = Math.hypot(dx, dy);
                if (dist >= MIN_DIST) continue;

                hasOverlap = true;
                if (dist < 0.001) {
                    // Same spot: separate along the tangent.
                    const tangent = ((p1.angle + 90) * Math.PI) / 180;
                    dx = Math.cos(tangent);
                    dy = Math.sin(tangent);
                    dist = 1;
                }
                const push = (MIN_DIST - Math.min(dist, MIN_DIST)) / 2 + 0.01;
                const ux = dx / dist;
                const uy = dy / dist;
                place(p1, a.x - ux * push, a.y - uy * push);
                place(p2, b.x + ux * push, b.y + uy * push);
            }
        }
        if (!hasOverlap) break;
    }

    return dispersed;
}

function toXY(item) {
    const radians = (item.angle * Math.PI) / 180;
    return { x: item.radius * Math.cos(radians), y: item.radius * Math.sin(radians) };
}

/** Radius range a marker may use: its horizon band, minus a margin, and never inside the core. */
function radiusBounds(item) {
    const band = HORIZON_BANDS.find((entry) => entry.horizon === item.horizon) ?? HORIZON_BANDS[1];
    const outer = item.horizon === 3 ? 8.6 : band.to - 0.4;
    return [Math.max(MIN_MARKER_RADIUS, band.from + 0.4), outer];
}

/** Move a marker to (x, y), then keep it within 16° of its sector centre and inside its band. */
function place(item, x, y) {
    const [low, high] = radiusBounds(item);
    item.radius = Math.max(low, Math.min(high, Math.hypot(x, y)));
    const angle = (Math.atan2(y, x) * 180) / Math.PI;
    const offset = ((angle - item.centerAngle + 540) % 360) - 180;
    item.angle = (item.centerAngle + Math.max(-16, Math.min(16, offset)) + 360) % 360;
}
