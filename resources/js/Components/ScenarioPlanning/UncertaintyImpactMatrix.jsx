import HintTooltip from "@/Components/HintTooltip";
import { SCORE_CLASSES, classifyScore, matrixPoints } from "@/domain/scenarioPlanning";

const SIZE = 360;
const PAD = 40;
const SPAN = SIZE - PAD * 2;

// Score 1..10 to SVG coordinates; y grows downward, so impact is flipped.
const sx = (value) => PAD + ((value - 0.5) / 10) * SPAN;
const sy = (value) => SIZE - PAD - ((value - 0.5) / 10) * SPAN;

const FILL = {
    candidate: "var(--primary)",
    predetermined: "var(--warning)",
    watch: "var(--muted-foreground)",
    minor: "var(--border)",
};

/**
 * Uncertainty (x) × impact (y) matrix of the exercise's forces, numbered as in
 * the list (slide 19). The top-right region holds axis candidates; the
 * top-left holds predetermined candidates.
 */
export default function UncertaintyImpactMatrix({ forces, threshold }) {
    const points = matrixPoints(forces);
    const split = threshold - 0.5;

    return (
        <figure className="m-0 flex flex-col gap-2">
            {/* aria-label, not <title>: a <title> would also pop the browser's native tooltip over the chart. */}
            <svg viewBox={`0 0 ${SIZE} ${SIZE}`} role="group" aria-label={`Matriks uncertainty × impact, ${points.length} driving force`} className="h-auto w-full max-w-md self-center">
                <rect x={sx(split)} y={PAD} width={sx(10.5) - sx(split)} height={sy(split) - PAD} fill="var(--primary)" opacity="0.08" />
                <rect x={PAD} y={PAD} width={sx(split) - PAD} height={sy(split) - PAD} fill="var(--warning)" opacity="0.08" />
                <rect x={PAD} y={PAD} width={SPAN} height={SPAN} fill="none" stroke="var(--border)" />
                <line x1={sx(split)} y1={PAD} x2={sx(split)} y2={SIZE - PAD} stroke="var(--border)" strokeDasharray="4 4" />
                <line x1={PAD} y1={sy(split)} x2={SIZE - PAD} y2={sy(split)} stroke="var(--border)" strokeDasharray="4 4" />
                {[1, 5, 10].map((tick) => (
                    <g key={tick} fontSize="10" fill="var(--muted-foreground)">
                        <text x={sx(tick)} y={SIZE - PAD + 14} textAnchor="middle">{tick}</text>
                        <text x={PAD - 8} y={sy(tick) + 3} textAnchor="end">{tick}</text>
                    </g>
                ))}
                <text x={SIZE / 2} y={SIZE - 6} textAnchor="middle" fontSize="11" fontWeight="600" fill="var(--foreground)">Uncertainty</text>
                <text x={12} y={SIZE / 2} textAnchor="middle" fontSize="11" fontWeight="600" fill="var(--foreground)" transform={`rotate(-90 12 ${SIZE / 2})`}>Impact</text>
                <text x={sx(10.4)} y={PAD + 12} textAnchor="end" fontSize="9" fill="var(--primary)">Kandidat sumbu</text>
                <text x={PAD + 4} y={PAD + 12} fontSize="9" fill="var(--warning)">Predetermined</text>
                {points.map((point) => {
                    const kind = classifyScore(point.impact, point.uncertainty, threshold);
                    const summary = `${point.number}. ${point.force.keyword}: impact ${point.impact}, uncertainty ${point.uncertainty}`;
                    return (
                        <HintTooltip
                            key={point.id}
                            content={
                                <span className="flex flex-col gap-0.5">
                                    <span className="font-semibold">{point.number}. {point.force.keyword}</span>
                                    <span>Impact {point.impact} · Uncertainty {point.uncertainty}</span>
                                    <span className="opacity-80">{SCORE_CLASSES[kind].label}{point.is_predetermined ? " · ditandai predetermined" : ""}</span>
                                </span>
                            }
                        >
                            <g tabIndex={0} role="img" aria-label={summary} className="cursor-default outline-none focus-visible:[&>circle]:stroke-ring">
                                <circle cx={sx(point.x)} cy={sy(point.y)} r="10" fill={FILL[kind]} stroke={point.is_predetermined ? "var(--foreground)" : "var(--card)"} strokeWidth="1.5" />
                                <text x={sx(point.x)} y={sy(point.y) + 3.5} textAnchor="middle" fontSize="10" fontWeight="700" fill={kind === "minor" ? "var(--foreground)" : "var(--card)"}>
                                    {point.number}
                                </text>
                            </g>
                        </HintTooltip>
                    );
                })}
            </svg>
            <figcaption className="text-center text-xs text-muted-foreground">
                Garis putus-putus di skor {threshold}. Lingkaran bergaris tebal: ditandai predetermined di langkah 2.
            </figcaption>
        </figure>
    );
}
