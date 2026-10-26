import { useId, useMemo, useState } from "react";
import {
    ActivityIcon,
    ArrowRightIcon,
    CheckIcon,
    EditIcon,
    FilterIcon,
    HelpCircleIcon,
    InfoIcon,
    RotateCcwIcon,
    SlidersHorizontalIcon,
} from "lucide-react";

import DimensionChip, { dimensionColor } from "@/Components/DimensionChip";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import { cn } from "@/lib/utils";

const WIDTH = 920;
const HEIGHT = 680;
const CX = WIDTH / 2;
const CY = HEIGHT / 2;
const RADIUS = 250;
const NODE_RADIUS = 16;

const signedScore = (score) => (score > 0 ? `+${score}` : String(score));

const SCORE_LABELS = {
    "-3": "Sangat menekan",
    "-2": "Menekan",
    "-1": "Sedikit menekan",
    0: "Tidak berpengaruh",
    1: "Sedikit memperkuat",
    2: "Memperkuat",
    3: "Sangat memperkuat",
};

const ROLE_TONES = {
    driver: "bg-accent text-primary",
    critical: "bg-destructive/15 text-destructive",
    indicator: "bg-warning/15 text-warning",
    buffer: "bg-muted text-muted-foreground",
    unscored: "bg-muted text-muted-foreground",
};

/**
 * Calculates curved Bézier path between two circle boundaries.
 * Adds a perpendicular normal offset so A -> B and B -> A don't overlap.
 */
function calculateEdgeCurve(sourcePos, targetPos) {
    const dx = targetPos.x - sourcePos.x;
    const dy = targetPos.y - sourcePos.y;
    const dist = Math.hypot(dx, dy);

    if (dist === 0) return { path: "", start: sourcePos, end: targetPos, mid: sourcePos };

    const ux = dx / dist;
    const uy = dy / dist;

    // Normal vector perpendicular to direction (pointing clockwise)
    const nx = -uy;
    const ny = ux;

    // Offset start & end so arrows don't collide into the node circles
    const startX = sourcePos.x + ux * (NODE_RADIUS + 3);
    const startY = sourcePos.y + uy * (NODE_RADIUS + 3);
    const endX = targetPos.x - ux * (NODE_RADIUS + 8);
    const endY = targetPos.y - uy * (NODE_RADIUS + 8);

    // Midpoint
    const mx = (startX + endX) / 2;
    const my = (startY + endY) / 2;

    // Pull toward center slightly, plus perpendicular offset for curve
    const pullFactor = 0.22;
    const curveOffset = 30;

    const ctrlX = mx * (1 - pullFactor) + CX * pullFactor + nx * curveOffset;
    const ctrlY = my * (1 - pullFactor) + CY * pullFactor + ny * curveOffset;

    // Quadratic Bézier curve
    const path = `M ${startX} ${startY} Q ${ctrlX} ${ctrlY} ${endX} ${endY}`;

    // Midpoint along the curve for label/marker positioning
    const midX = 0.25 * startX + 0.5 * ctrlX + 0.25 * endX;
    const midY = 0.25 * startY + 0.5 * ctrlY + 0.25 * endY;

    return { path, start: { x: startX, y: startY }, end: { x: endX, y: endY }, mid: { x: midX, y: midY } };
}

export default function CrossImpactNetworkGraph({
    forces = [],
    scores = new Map(),
    summary = [],
    highlight = null,
    canManage = false,
    onEdit = () => {},
    selectedLoop = null,
}) {
    const filterId = useId();
    const [polarityFilter, setPolarityFilter] = useState("all"); // 'all' | 'pos' | 'neg' | 'strong'
    const [hoveredForceId, setHoveredForceId] = useState(null);
    const [pinnedForceId, setPinnedForceId] = useState(null);
    const [hoveredEdge, setHoveredEdge] = useState(null);

    const activeForceId = pinnedForceId || hoveredForceId;

    // Lookup summary by force id
    const summaryMap = useMemo(
        () => new Map(summary.map((row) => [row.id, row])),
        [summary]
    );

    // Calculate node positions around the circle
    const nodePositions = useMemo(() => {
        const count = forces.length;
        if (count === 0) return new Map();

        const map = new Map();
        forces.forEach((force, index) => {
            const angle = (2 * Math.PI * index) / count - Math.PI / 2;
            const x = CX + RADIUS * Math.cos(angle);
            const y = CY + RADIUS * Math.sin(angle);

            // Label anchor position (further out)
            const labelDist = RADIUS + 32;
            const lx = CX + labelDist * Math.cos(angle);
            const ly = CY + labelDist * Math.sin(angle);

            let textAnchor = "middle";
            const cos = Math.cos(angle);
            if (cos > 0.3) textAnchor = "start";
            else if (cos < -0.3) textAnchor = "end";

            map.set(force.id, {
                ...force,
                index,
                x,
                y,
                lx,
                ly,
                textAnchor,
                angle,
            });
        });
        return map;
    }, [forces]);

    // Build list of active directed edges
    const edges = useMemo(() => {
        const list = [];
        forces.forEach((source) => {
            forces.forEach((target) => {
                if (source.id === target.id) return;
                const cell = scores.get(`${source.id}|${target.id}`);
                const score = cell?.score ?? 0;
                if (!score) return;

                // Apply polarity / strength filter
                if (polarityFilter === "pos" && score <= 0) return;
                if (polarityFilter === "neg" && score >= 0) return;
                if (polarityFilter === "strong" && Math.abs(score) < 2) return;

                const sPos = nodePositions.get(source.id);
                const tPos = nodePositions.get(target.id);
                if (!sPos || !tPos) return;

                const curve = calculateEdgeCurve(sPos, tPos);
                const key = `${source.id}|${target.id}`;

                list.push({
                    key,
                    source,
                    target,
                    score,
                    note: cell?.note,
                    ...curve,
                });
            });
        });
        return list;
    }, [forces, scores, nodePositions, polarityFilter]);

    // Summary counts for filter badges
    const edgeCounts = useMemo(() => {
        let pos = 0;
        let neg = 0;
        let strong = 0;
        forces.forEach((source) => {
            forces.forEach((target) => {
                if (source.id === target.id) return;
                const cell = scores.get(`${source.id}|${target.id}`);
                const score = cell?.score ?? 0;
                if (score > 0) pos++;
                if (score < 0) neg++;
                if (Math.abs(score) >= 2) strong++;
            });
        });
        return { total: pos + neg, pos, neg, strong };
    }, [forces, scores]);

    // Selected force detail for inspector overlay
    const activeForce = activeForceId ? nodePositions.get(activeForceId) : null;
    const activeSummary = activeForceId ? summaryMap.get(activeForceId) : null;

    return (
        <div className="relative flex flex-col gap-3 rounded-md border border-border bg-card p-4">
            {/* Top Toolbar: Filter options and graph indicators */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                <div className="flex flex-wrap items-center gap-1.5 text-xs">
                    <span className="font-semibold text-muted-foreground mr-1 flex items-center gap-1">
                        <FilterIcon className="size-3.5" /> Filter Pengaruh:
                    </span>
                    <Button
                        type="button"
                        variant={polarityFilter === "all" ? "default" : "outline"}
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => setPolarityFilter("all")}
                    >
                        Semua ({edgeCounts.total})
                    </Button>
                    <Button
                        type="button"
                        variant={polarityFilter === "pos" ? "default" : "outline"}
                        size="sm"
                        className="h-7 text-xs text-emerald-600 dark:text-emerald-400"
                        onClick={() => setPolarityFilter("pos")}
                    >
                        Memperkuat + ({edgeCounts.pos})
                    </Button>
                    <Button
                        type="button"
                        variant={polarityFilter === "neg" ? "default" : "outline"}
                        size="sm"
                        className="h-7 text-xs text-rose-600 dark:text-rose-400"
                        onClick={() => setPolarityFilter("neg")}
                    >
                        Menekan − ({edgeCounts.neg})
                    </Button>
                    <Button
                        type="button"
                        variant={polarityFilter === "strong" ? "default" : "outline"}
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => setPolarityFilter("strong")}
                    >
                        Kuat |≥2| ({edgeCounts.strong})
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    {pinnedForceId && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs text-muted-foreground hover:text-foreground"
                            onClick={() => setPinnedForceId(null)}
                        >
                            <RotateCcwIcon className="mr-1 size-3.5" />
                            Lepas Fokus
                        </Button>
                    )}
                    <span className="text-[11px] text-muted-foreground">
                        {forces.length} Force · {edges.length} Relasi
                    </span>
                </div>
            </div>

            {/* SVG Interactive Canvas */}
            <div className="relative w-full overflow-hidden rounded-md bg-muted/20">
                <svg
                    viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                    className="w-full h-auto select-none"
                    style={{ maxHeight: "72vh" }}
                >
                    <defs>
                        {/* Markers for directed arrows */}
                        <marker
                            id="arrow-pos"
                            markerWidth="7"
                            markerHeight="7"
                            refX="5"
                            refY="3.5"
                            orient="auto"
                        >
                            <polygon points="0 0, 7 3.5, 0 7" className="fill-emerald-500" />
                        </marker>
                        <marker
                            id="arrow-neg"
                            markerWidth="7"
                            markerHeight="7"
                            refX="5"
                            refY="3.5"
                            orient="auto"
                        >
                            <polygon points="0 0, 7 3.5, 0 7" className="fill-rose-500" />
                        </marker>
                        <marker
                            id="arrow-highlight"
                            markerWidth="9"
                            markerHeight="9"
                            refX="6"
                            refY="4.5"
                            orient="auto"
                        >
                            <polygon points="0 0, 9 4.5, 0 9" className="fill-primary" />
                        </marker>
                        <marker
                            id="arrow-dimmed"
                            markerWidth="5"
                            markerHeight="5"
                            refX="4"
                            refY="2.5"
                            orient="auto"
                        >
                            <polygon points="0 0, 5 2.5, 0 5" className="fill-muted-foreground/30" />
                        </marker>

                        {/* Drop shadow filter for highlighted edges */}
                        <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>

                    {/* Background guides: soft center circle */}
                    <circle
                        cx={CX}
                        cy={CY}
                        r={RADIUS}
                        fill="none"
                        stroke="var(--border)"
                        strokeDasharray="4 6"
                        strokeWidth="1"
                        opacity="0.45"
                    />

                    {/* Directed Edges (Arrows) */}
                    <g className="edges-layer">
                        {edges.map((edge) => {
                            const isLoopEdge = highlight?.has(edge.key);
                            const isConnectedToActive =
                                activeForceId &&
                                (edge.source.id === activeForceId || edge.target.id === activeForceId);
                            const isEdgeHovered = hoveredEdge?.key === edge.key;

                            let opacity = 0.55;
                            let isHighlighted = false;

                            if (highlight) {
                                if (isLoopEdge) {
                                    opacity = 1;
                                    isHighlighted = true;
                                } else {
                                    opacity = 0.08;
                                }
                            } else if (activeForceId) {
                                if (isConnectedToActive) {
                                    opacity = 1;
                                    isHighlighted = true;
                                } else {
                                    opacity = 0.08;
                                }
                            } else if (isEdgeHovered) {
                                opacity = 1;
                                isHighlighted = true;
                            }

                            const isPositive = edge.score > 0;
                            const baseColor = isPositive ? "var(--success, #10b981)" : "var(--danger, #f43f5e)";
                            const strokeColor = isHighlighted && isLoopEdge ? "var(--primary)" : baseColor;
                            const strokeWidth = isHighlighted
                                ? Math.max(3, Math.abs(edge.score) * 1.5 + 1.5)
                                : Math.abs(edge.score) * 1.1 + 0.8;

                            let markerId = isPositive ? "url(#arrow-pos)" : "url(#arrow-neg)";
                            if (isHighlighted) {
                                markerId = isLoopEdge ? "url(#arrow-highlight)" : markerId;
                            } else if (opacity < 0.2) {
                                markerId = "url(#arrow-dimmed)";
                            }

                            return (
                                <g key={edge.key} className="transition-opacity duration-200">
                                    {/* Visible Curved Line */}
                                    <path
                                        d={edge.path}
                                        fill="none"
                                        stroke={strokeColor}
                                        strokeWidth={strokeWidth}
                                        strokeOpacity={opacity}
                                        markerEnd={markerId}
                                        strokeDasharray={isLoopEdge ? "6 3" : undefined}
                                        className={cn(isLoopEdge && "animate-pulse")}
                                        filter={isHighlighted ? "url(#glow)" : undefined}
                                    />

                                    {/* Transparent fat hit area for mouse interaction */}
                                    <path
                                        d={edge.path}
                                        fill="none"
                                        stroke="transparent"
                                        strokeWidth="16"
                                        className="cursor-pointer"
                                        onMouseEnter={() => setHoveredEdge(edge)}
                                        onMouseLeave={() => setHoveredEdge(null)}
                                        onClick={() => {
                                            if (canManage) {
                                                const cell = scores.get(edge.key);
                                                onEdit(edge.source, edge.target, cell);
                                            }
                                        }}
                                    >
                                        <title>
                                            {`${edge.source.keyword} → ${edge.target.keyword} (${signedScore(edge.score)}: ${SCORE_LABELS[edge.score]})${edge.note ? ` - Catatan: ${edge.note}` : ""}`}
                                        </title>
                                    </path>
                                </g>
                            );
                        })}
                    </g>

                    {/* Nodes (Driving Forces) */}
                    <g className="nodes-layer">
                        {forces.map((force) => {
                            const pos = nodePositions.get(force.id);
                            if (!pos) return null;

                            const isSelected = activeForceId === force.id;
                            const isInLoop = highlight && [...highlight].some((k) => k.startsWith(force.id) || k.endsWith(force.id));
                            const isConnected =
                                activeForceId &&
                                (scores.has(`${activeForceId}|${force.id}`) || scores.has(`${force.id}|${activeForceId}`));

                            let opacity = 1;
                            if (highlight && !isInLoop) opacity = 0.25;
                            else if (activeForceId && !isSelected && !isConnected) opacity = 0.25;

                            const dimColor = dimensionColor(force.dimension);
                            const sum = summaryMap.get(force.id);

                            return (
                                <g
                                    key={force.id}
                                    transform={`translate(${pos.x}, ${pos.y})`}
                                    className="cursor-pointer transition-all duration-200"
                                    opacity={opacity}
                                    onMouseEnter={() => setHoveredForceId(force.id)}
                                    onMouseLeave={() => setHoveredForceId(null)}
                                    onClick={() =>
                                        setPinnedForceId((prev) => (prev === force.id ? null : force.id))
                                    }
                                >
                                    {/* Active ring indicator */}
                                    {isSelected && (
                                        <circle
                                            r={NODE_RADIUS + 7}
                                            fill="none"
                                            stroke="var(--primary)"
                                            strokeWidth="2.5"
                                            strokeDasharray="4 2"
                                            className="animate-spin origin-center"
                                            style={{ transformOrigin: "0px 0px" }}
                                        />
                                    )}

                                    {/* Loop highlight ring */}
                                    {isInLoop && !isSelected && (
                                        <circle
                                            r={NODE_RADIUS + 5}
                                            fill="none"
                                            stroke="var(--primary)"
                                            strokeWidth="2"
                                        />
                                    )}

                                    {/* Main Node Circle */}
                                    <circle
                                        r={NODE_RADIUS}
                                        fill="var(--card)"
                                        stroke={dimColor}
                                        strokeWidth="3.5"
                                        className="shadow-sm hover:scale-110 transition-transform"
                                    />

                                    {/* Number label inside node */}
                                    <text
                                        textAnchor="middle"
                                        dominantBaseline="central"
                                        fontSize="11"
                                        fontWeight="700"
                                        fill="var(--foreground)"
                                        className="pointer-events-none tabular-nums"
                                    >
                                        {pos.index + 1}
                                    </text>

                                    {/* Keyword label outside node */}
                                    <text
                                        x={pos.lx - pos.x}
                                        y={pos.ly - pos.y}
                                        textAnchor={pos.textAnchor}
                                        dominantBaseline="central"
                                        fontSize="11.5"
                                        fontWeight={isSelected || isInLoop ? "700" : "500"}
                                        fill={isSelected ? "var(--primary)" : "var(--foreground)"}
                                        className="pointer-events-none transition-colors"
                                    >
                                        {force.keyword}
                                    </text>
                                </g>
                            );
                        })}
                    </g>
                </svg>

                {/* Floating Inspector HUD (Bottom Overlay) */}
                {activeForce && activeSummary ? (
                    <div className="absolute bottom-3 left-3 right-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card/95 p-3 backdrop-blur-md shadow-lg sm:bottom-4 sm:left-4 sm:right-4">
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="inline-flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                                    {activeForce.index + 1}
                                </span>
                                <span className="font-bold text-foreground text-sm">
                                    {activeForce.keyword}
                                </span>
                                <DimensionChip name={activeForce.dimension} />
                                <span className={cn(statusBadge(), ROLE_TONES[activeSummary.role])}>
                                    {activeSummary.role_label}
                                </span>
                            </div>
                            <p className="m-0 text-xs text-muted-foreground">
                                Nilai Pengaruh Vester: Aktif (memengaruhi) <b>{activeSummary.active}</b> · Pasif (dipengaruhi) <b>{activeSummary.passive}</b> · Net <b>{signedScore(activeSummary.net)}</b>
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            {canManage && (
                                <p className="m-0 text-[11px] text-muted-foreground hidden md:block">
                                    Klik garis panah mana saja untuk mengubah skor pengaruh.
                                </p>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => {
                                    setPinnedForceId(null);
                                    setHoveredForceId(null);
                                }}
                            >
                                Tutup
                            </Button>
                        </div>
                    </div>
                ) : hoveredEdge ? (
                    <div className="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-3 rounded-lg border border-border bg-card/95 px-4 py-2.5 backdrop-blur-md shadow-md sm:bottom-4 sm:left-4 sm:right-4">
                        <div className="flex items-center gap-2 text-xs">
                            <span className="font-semibold text-foreground">{hoveredEdge.source.keyword}</span>
                            <ArrowRightIcon className="size-3 text-muted-foreground" />
                            <span className="font-semibold text-foreground">{hoveredEdge.target.keyword}</span>
                            <span
                                className={cn(
                                    "ml-2 rounded px-1.5 py-0.5 font-bold tabular-nums",
                                    hoveredEdge.score > 0
                                        ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
                                        : "bg-rose-500/15 text-rose-600 dark:text-rose-400"
                                )}
                            >
                                {signedScore(hoveredEdge.score)} · {SCORE_LABELS[hoveredEdge.score]}
                            </span>
                            {hoveredEdge.note && (
                                <span className="text-muted-foreground italic">
                                    "{hoveredEdge.note}"
                                </span>
                            )}
                        </div>
                        {canManage && (
                            <span className="text-[11px] text-muted-foreground">
                                Klik untuk mengedit skor
                            </span>
                        )}
                    </div>
                ) : null}
            </div>

            {/* Bottom Legend */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-2 text-xs text-muted-foreground">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="font-medium text-foreground">Legenda Garis Panah:</span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-0.5 w-4 bg-emerald-500 rounded" />
                        <span>Memperkuat (+)</span>
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-0.5 w-4 bg-rose-500 rounded" />
                        <span>Menekan (−)</span>
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-1 w-4 bg-emerald-500 rounded" />
                        <span>Tebal = Kekuatan |Skor| 1 s/d 3</span>
                    </span>
                </div>
                <div className="text-[11px]">
                    Tip: Klik pada nama Driving Force untuk mengunci sorotan koneksinya.
                </div>
            </div>
        </div>
    );
}

