import { CORE_RADIUS, DIMENSION_CONFIGS, HORIZON_BANDS, RADAR_MAX_RADIUS, horizonLabel, polarToXY } from "@/lib/radarLayout";
import { cn } from "@/lib/utils";

const WIDTH = 640;
const HEIGHT = 600;
const CENTRE_X = WIDTH / 2;
const CENTRE_Y = HEIGHT / 2;
const SCALE = 250 / RADAR_MAX_RADIUS;
const LABEL_RADIUS = RADAR_MAX_RADIUS + 0.6;

const BAND_FILLS = {
    1: "var(--horizon-short)",
    2: "var(--horizon-mid)",
    3: "var(--horizon-long)",
};

export const PRIORITY_FILLS = {
    high: "var(--danger)",
    medium: "var(--warning)",
    low: "var(--success)",
};

export function priorityFill(priority) {
    return PRIORITY_FILLS[String(priority ?? "").trim().toLowerCase()] ?? "var(--primary)";
}

/**
 * Status of Action shapes (status_actions.symbol): Monitoring is a triangle, Decided Plan a circle.
 * Labels for the legend; anything else falls back to a circle.
 */
export const ACTION_SHAPES = {
    circle: "Decided Plan",
    triangle: "Monitoring",
};

/** Equilateral triangle around the origin with circumradius `size`, point up. */
function trianglePoints(size) {
    const half = size * Math.sin(Math.PI / 3);
    return `0,${-size} ${half},${size / 2} ${-half},${size / 2}`;
}

/**
 * One marker outline. `size` is the circle radius; the triangle gets a larger circumradius so both
 * read as the same weight on the radar.
 */
export function MarkerShape({ symbol, size, ...props }) {
    if (symbol === "triangle") {
        return <polygon points={trianglePoints(size * 1.45)} strokeLinejoin="round" {...props} />;
    }
    return <circle r={size} {...props} />;
}

function point(radius, angle) {
    const { x, y } = polarToXY(radius, angle, SCALE, 0);
    return { x: CENTRE_X + x, y: CENTRE_Y + y };
}

function DimensionLabel({ name, angle }) {
    const { x, y } = point(LABEL_RADIUS, angle);
    const width = name.length * 7.4 + 18;

    return (
        <g transform={`translate(${x} ${y})`} aria-hidden="true">
            <rect
                x={-width / 2}
                y={-12}
                width={width}
                height={24}
                rx={6}
                fill="var(--card)"
                stroke="var(--border)"
            />
            <text textAnchor="middle" dominantBaseline="central" fontSize={12} fontWeight={700} fill="var(--foreground)">
                {name}
            </text>
        </g>
    );
}

function Marker({ item, active, onActivate, onSelect }) {
    const { x, y } = point(item.radius, item.angle);
    const label = `#${item.no} ${item.keyword}, ${item.dimension}, ${horizonLabel(item.horizon)}, prioritas ${item.priority ?? "-"}${item.status_action_name ? `, ${item.status_action_name}` : ""}`;

    const handleKeyDown = (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            onSelect(item);
        }
    };

    return (
        <g
            role="button"
            tabIndex={0}
            aria-label={label}
            transform={`translate(${x} ${y})`}
            className="cursor-pointer outline-none"
            onMouseEnter={() => onActivate(item.no)}
            onMouseLeave={() => onActivate(null)}
            onFocus={() => onActivate(item.no)}
            onBlur={() => onActivate(null)}
            onClick={() => onSelect(item)}
            onKeyDown={handleKeyDown}
        >
            {active && <MarkerShape symbol={item.symbol} size={21} fill="none" stroke="var(--ring)" strokeWidth={3} />}
            <MarkerShape
                symbol={item.symbol}
                size={active ? 17 : 15}
                fill={priorityFill(item.priority)}
                stroke="var(--card)"
                strokeWidth={2}
                className="transition-[r] motion-reduce:transition-none"
            />
            <text
                y={item.symbol === "triangle" ? 4 : 0}
                textAnchor="middle"
                dominantBaseline="central"
                fontSize={item.symbol === "triangle" ? 11 : 12}
                fontWeight={700}
                fill="var(--primary-foreground)"
                pointerEvents="none"
            >
                {item.no}
            </text>
        </g>
    );
}

function MarkerTooltip({ item }) {
    const { x, y } = point(item.radius, item.angle);
    const below = y < HEIGHT * 0.3;

    return (
        <div
            aria-hidden="true"
            className={cn(
                "pointer-events-none absolute z-10 w-56 -translate-x-1/2 rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md",
                below ? "mt-5" : "-translate-y-full -mt-5"
            )}
            style={{ left: `${(x / WIDTH) * 100}%`, top: `${(y / HEIGHT) * 100}%` }}
        >
            <p className="m-0 mb-1 text-sm font-semibold">
                #{item.no} {item.keyword}
            </p>
            <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-muted-foreground">
                <dt>Dimension</dt>
                <dd className="m-0 font-medium text-foreground">{item.dimension}</dd>
                <dt>Horizon</dt>
                <dd className="m-0 font-medium text-foreground">{horizonLabel(item.horizon)}</dd>
                <dt>Priority</dt>
                <dd className="m-0 flex items-center gap-1.5 font-medium text-foreground">
                    <span aria-hidden="true" className="size-2 rounded-full" style={{ backgroundColor: priorityFill(item.priority) }} />
                    {item.priority ?? "-"}
                </dd>
                {item.status_action && (
                    <>
                        <dt>Action</dt>
                        <dd className="m-0 font-medium text-foreground">{item.status_action_name ?? item.status_action}</dd>
                    </>
                )}
            </dl>
        </div>
    );
}

/**
 * 360° radar of driving forces: horizon bands, one sector per dimension, and a
 * numbered marker per item (positions from computeAntiCollisionPoints).
 * Markers are buttons: hover or focus calls `onActivate(no)`, click or Enter
 * calls `onSelect(item)`. `activeNo` highlights a marker (e.g. from the list).
 */
export const LINK_STROKES = { amplify: "var(--success)", suppress: "var(--danger)" };

/** Unit vector from `from` to `to`, scaled by `length`. */
function towards(from, to, length) {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const distance = Math.hypot(dx, dy) || 1;
    return { x: (dx / distance) * length, y: (dy / distance) * length };
}

/**
 * Cross-impact arrows between markers: green amplifies, dashed red suppresses,
 * thicker for a stronger score. Each arrow bends to its own right, so A→B and
 * B→A stay apart. Links whose forces are not on the radar are skipped.
 */
function CrossImpactLinks({ points, links }) {
    const byId = new Map(points.map((item) => [item.id, point(item.radius, item.angle)]));

    return (
        <g aria-hidden="true" pointerEvents="none">
            <defs>
                {Object.entries(LINK_STROKES).map(([kind, stroke]) => (
                    <marker
                        key={kind}
                        id={`cross-impact-${kind}`}
                        viewBox="0 0 10 10"
                        refX={8}
                        refY={5}
                        markerWidth={5}
                        markerHeight={5}
                        orient="auto-start-reverse"
                    >
                        <path d="M 0 0 L 10 5 L 0 10 z" fill={stroke} />
                    </marker>
                ))}
            </defs>
            {links.map((link) => {
                const from = byId.get(link.source);
                const to = byId.get(link.target);
                if (!from || !to) return null;

                const kind = link.score > 0 ? "amplify" : "suppress";
                const mid = { x: (from.x + to.x) / 2, y: (from.y + to.y) / 2 };
                const bend = towards(from, to, Math.hypot(to.x - from.x, to.y - from.y) * 0.18);
                const control = { x: mid.x - bend.y, y: mid.y + bend.x };
                const startOffset = towards(from, control, 17);
                const endOffset = towards(to, control, 19);
                const start = { x: from.x + startOffset.x, y: from.y + startOffset.y };
                const end = { x: to.x + endOffset.x, y: to.y + endOffset.y };

                return (
                    <path
                        key={`${link.source}-${link.target}`}
                        d={`M ${start.x} ${start.y} Q ${control.x} ${control.y} ${end.x} ${end.y}`}
                        fill="none"
                        stroke={LINK_STROKES[kind]}
                        strokeOpacity={0.8}
                        strokeWidth={1 + Math.abs(link.score) * 0.6}
                        strokeDasharray={kind === "suppress" ? "5 4" : undefined}
                        markerEnd={`url(#cross-impact-${kind})`}
                    />
                );
            })}
        </g>
    );
}

export default function RadarScope({ points, sectors = DIMENSION_CONFIGS, links = [], activeNo, onActivate, onSelect, className, style }) {
    const active = points.find((item) => item.no === activeNo);
    // Draw the active marker last so it sits on top.
    const ordered = active ? [...points.filter((item) => item !== active), active] : points;

    return (
        <div className={cn("relative mx-auto w-full", className)} style={{ maxWidth: WIDTH, ...style }}>
            <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="block h-auto w-full" role="group" aria-label="Foresight radar">
                <g aria-hidden="true">
                    <circle cx={CENTRE_X} cy={CENTRE_Y} r={RADAR_MAX_RADIUS * SCALE} fill="var(--card)" />
                    {[...HORIZON_BANDS].reverse().map((band) => (
                        <circle key={band.horizon} cx={CENTRE_X} cy={CENTRE_Y} r={band.to * SCALE} fill={BAND_FILLS[band.horizon]} />
                    ))}
                    {Array.from({ length: RADAR_MAX_RADIUS }, (_, index) => index + 1).map((radius) => (
                        <circle
                            key={radius}
                            cx={CENTRE_X}
                            cy={CENTRE_Y}
                            r={radius * SCALE}
                            fill="none"
                            stroke="var(--border)"
                            strokeWidth={HORIZON_BANDS.some((band) => band.to === radius) ? 1.5 : 1}
                        />
                    ))}
                    {sectors.map((dimension) => {
                        const end = point(RADAR_MAX_RADIUS, dimension.angle);
                        return (
                            <line
                                key={dimension.name}
                                x1={CENTRE_X}
                                y1={CENTRE_Y}
                                x2={end.x}
                                y2={end.y}
                                stroke="var(--input)"
                                strokeOpacity={0.6}
                                strokeDasharray="4 4"
                            />
                        );
                    })}
                    {/* The organisation at the centre of the radar: the first ring, labelled. */}
                    <circle cx={CENTRE_X} cy={CENTRE_Y} r={CORE_RADIUS * SCALE} fill="var(--radar-core)" stroke="var(--border)" strokeWidth={1.5} />
                    <text
                        x={CENTRE_X}
                        y={CENTRE_Y}
                        textAnchor="middle"
                        dominantBaseline="central"
                        fontSize={12}
                        fontWeight={800}
                        letterSpacing={1}
                        fill="var(--radar-core-foreground)"
                    >
                        FIRM
                    </text>
                    {sectors.map((dimension) => (
                        <DimensionLabel key={dimension.name} name={dimension.name} angle={dimension.angle} />
                    ))}
                </g>

                {links.length > 0 && <CrossImpactLinks points={points} links={links} />}

                {ordered.map((item) => (
                    <Marker
                        key={item.no}
                        item={item}
                        active={item.no === activeNo}
                        onActivate={onActivate}
                        onSelect={onSelect}
                    />
                ))}
            </svg>

            {active && <MarkerTooltip item={active} />}
        </div>
    );
}
