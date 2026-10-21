/**
 * Layout for the Foresight Radar: polar coordinates in "radius units" (0 at
 * the centre, RADAR_MAX_RADIUS at the edge) and degrees (0 = east, counter-
 * clockwise), moved here unchanged from the ECharts version of Radar.jsx.
 */

export const RADAR_MAX_RADIUS = 9;

/** The eight dimensions and the polar angle their sector is centred on. */
export const DIMENSION_CONFIGS = [
    { name: "Economy", angle: 90 },
    { name: "Competitor", angle: 45 },
    { name: "Customer", angle: 0 },
    { name: "Supplier", angle: 315 },
    { name: "Substitute", angle: 270 },
    { name: "Technology", angle: 225 },
    { name: "Regulation", angle: 180 },
    { name: "Ecology", angle: 135 },
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

        if (count === 2) {
            angleOffsets = [-10, 10];
            radiusOffsets = [-0.25, 0.25];
        } else if (count === 3) {
            angleOffsets = [-14, 0, 14];
            radiusOffsets = [0.35, -0.35, 0.35];
        } else if (count === 4) {
            angleOffsets = [-16, -6, 6, 16];
            radiusOffsets = [-0.35, 0.35, -0.35, 0.35];
        } else if (count > 4) {
            // Even fan across up to 34 degrees
            const span = 34;
            const step = span / (count - 1);
            angleOffsets = groupItems.map((_, i) => -span / 2 + i * step);
            radiusOffsets = groupItems.map((_, i) => (i % 2 === 0 ? -0.35 : 0.35));
        }

        groupItems.forEach((item, idx) => {
            dispersed.push({
                ...item,
                angle: (centerAngle + angleOffsets[idx] + 360) % 360,
                radius: Math.max(1.8, Math.min(8.6, item.baseRadius + radiusOffsets[idx])),
                centerAngle,
            });
        });
    });

    // Relaxation pass so markers in a dimension do not overlap
    const MIN_DIST = 0.95;
    for (let iter = 0; iter < 12; iter++) {
        let hasOverlap = false;
        for (let i = 0; i < dispersed.length; i++) {
            for (let j = i + 1; j < dispersed.length; j++) {
                const p1 = dispersed[i];
                const p2 = dispersed[j];
                if (p1.dimension !== p2.dimension) continue;

                const rad1 = (p1.angle * Math.PI) / 180;
                const rad2 = (p2.angle * Math.PI) / 180;
                const dx = p2.radius * Math.cos(rad2) - p1.radius * Math.cos(rad1);
                const dy = p2.radius * Math.sin(rad2) - p1.radius * Math.sin(rad1);
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < MIN_DIST && dist > 0.001) {
                    hasOverlap = true;
                    const overlap = (MIN_DIST - dist) / 2;
                    const avgR = Math.max(2, (p1.radius + p2.radius) / 2);
                    const angleShift = (overlap / avgR) * (180 / Math.PI);

                    if (p1.angle <= p2.angle) {
                        p1.angle = Math.max(p1.centerAngle - 18, p1.angle - angleShift);
                        p2.angle = Math.min(p2.centerAngle + 18, p2.angle + angleShift);
                    } else {
                        p1.angle = Math.min(p1.centerAngle + 18, p1.angle + angleShift);
                        p2.angle = Math.max(p1.centerAngle - 18, p2.angle - angleShift);
                    }
                }
            }
        }
        if (!hasOverlap) break;
    }

    return dispersed;
}
