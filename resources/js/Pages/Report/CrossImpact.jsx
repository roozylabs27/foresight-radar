import { useEffect, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { GitCompareArrowsIcon, Grid3x3Icon, RefreshCwIcon, ScatterChartIcon } from "lucide-react";
import {
    CartesianGrid,
    ReferenceLine,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import DimensionChip from "@/Components/DimensionChip";
import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Skeleton } from "@/Components/ui/skeleton";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { cn } from "@/lib/utils";

const SCORES = [-3, -2, -1, 0, 1, 2, 3];
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
    critical: "bg-danger-soft text-danger",
    indicator: "bg-warning-soft text-warning",
    buffer: "bg-muted text-muted-foreground",
    unscored: "bg-muted text-muted-foreground",
};
const AXIS = { fill: "var(--muted-foreground)", fontSize: 11 };
const decimal = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 });

const signedScore = (score) => (score > 0 ? `+${score}` : String(score));

/** Diverging fill: danger for suppressing, success for amplifying, stronger with |score|. */
function cellFill(score) {
    if (!score) return undefined;
    const tone = score > 0 ? "var(--success)" : "var(--danger)";
    return `color-mix(in oklab, ${tone} ${Math.abs(score) * 22}%, transparent)`;
}

function Legend() {
    return (
        <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
            <span>Menekan</span>
            {SCORES.map((score) => (
                <span
                    key={score}
                    className="inline-flex size-6 items-center justify-center rounded border border-solid border-border font-semibold text-foreground tabular-nums"
                    style={{ backgroundColor: cellFill(score) }}
                    title={SCORE_LABELS[score]}
                >
                    {score === 0 ? "·" : signedScore(score)}
                </span>
            ))}
            <span>Memperkuat</span>
        </div>
    );
}

const LOOP_FILTERS = [
    { value: "all", label: "Semua" },
    { value: "R", label: "Penguat (R)" },
    { value: "B", label: "Penyeimbang (B)" },
];
const POLARITY = {
    R: { label: "R", title: "Penguat", tone: "bg-accent text-primary" },
    B: { label: "B", title: "Penyeimbang", tone: "bg-warning-soft text-warning" },
};

/** Feedback loops found in the matrix; selecting one highlights its cells. */
function LoopPanel({ loops, forces, selected, onSelect }) {
    const [filter, setFilter] = useState("all");
    const names = useMemo(() => new Map(forces.map((force) => [force.id, force.keyword])), [forces]);
    const visible = loops.loops
        .map((loop, index) => ({ ...loop, index }))
        .filter((loop) => filter === "all" || loop.polarity === filter);

    return (
        <Panel
            icon={<RefreshCwIcon className="text-primary" />}
            title="Causal Loops"
            badge={<span className={cn(statusBadge(), "bg-accent text-primary tabular-nums")}>{loops.count} loop</span>}
            description="Rantai pengaruh yang kembali ke force awal (maks. 5 force). Penguat (R) mendorong perubahan makin besar; Penyeimbang (B), dengan jumlah pengaruh negatif ganjil, meredamnya. Kekuatan = rata-rata geometris |skor| ÷ 3."
        >
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={filter}
                onValueChange={(value) => value && setFilter(value)}
                aria-label="Filter jenis loop"
                className="w-fit"
            >
                {LOOP_FILTERS.map((item) => (
                    <ToggleGroupItem key={item.value} value={item.value} className="px-3">
                        {item.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            {visible.length === 0 ? (
                <p className="m-0 py-6 text-center text-sm text-muted-foreground">
                    {loops.count === 0
                        ? "Belum ada loop. Loop muncul setelah ada rantai pengaruh yang kembali ke force awal, misalnya A → B dan B → A."
                        : "Tidak ada loop dengan jenis ini."}
                </p>
            ) : (
                <ul aria-label="Daftar causal loop" className="m-0 flex max-h-[360px] list-none flex-col gap-1.5 overflow-y-auto p-0">
                    {visible.map((loop) => {
                        const polarity = POLARITY[loop.polarity];
                        const active = selected === loop.index;
                        return (
                            <li key={loop.index}>
                                <button
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => onSelect(active ? null : loop.index)}
                                    className={cn(
                                        "flex w-full cursor-pointer flex-col gap-1.5 rounded-md border border-solid border-border bg-card px-3 py-2 text-left hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                                        active && "border-primary bg-accent/50"
                                    )}
                                >
                                    <span className="flex flex-wrap items-center gap-1.5 text-xs text-foreground">
                                        <span className={cn(statusBadge(), polarity.tone)} title={polarity.title}>
                                            {polarity.label} · {polarity.title}
                                        </span>
                                        {loop.links.map((link, position) => (
                                            <span key={position} className="inline-flex items-center gap-1">
                                                <span className="font-medium">{names.get(link.source)}</span>
                                                <span
                                                    className="rounded px-1 font-semibold tabular-nums"
                                                    style={{ backgroundColor: cellFill(link.score) }}
                                                >
                                                    {signedScore(link.score)}
                                                </span>
                                                <span aria-hidden="true">→</span>
                                            </span>
                                        ))}
                                        <span className="font-medium">{names.get(loop.nodes[0])}</span>
                                    </span>
                                    <span className="flex items-center gap-2 text-[11px] text-muted-foreground">
                                        <span className="h-1.5 w-24 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                            <span className="block h-full rounded-full bg-primary" style={{ width: `${loop.strength * 100}%` }} />
                                        </span>
                                        <span className="tabular-nums">
                                            Kekuatan {decimal.format(loop.strength)} · {loop.length} force
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
            {loops.truncated && (
                <p className="m-0 text-xs text-warning">
                    Pencarian dihentikan di batas {loops.count} loop; loop yang lebih lemah atau lebih panjang mungkin tidak tampil.
                </p>
            )}
        </Panel>
    );
}

function Matrix({ forces, scores, canManage, onEdit, highlight = null }) {
    return (
        // `isolate` keeps the sticky headers' z-index inside the matrix, below the page header.
        <div className="isolate max-h-[70vh] overflow-auto rounded-md border border-solid border-border">
            <table className="border-separate border-spacing-0 text-xs">
                <caption className="sr-only">
                    Matriks cross-impact: baris memengaruhi, kolom dipengaruhi
                </caption>
                <thead>
                    <tr>
                        <th
                            scope="col"
                            className="sticky top-0 left-0 z-20 min-w-[200px] border-0 border-r border-b border-solid border-border bg-card px-2 py-1.5 text-left font-semibold text-muted-foreground"
                        >
                            Memengaruhi ↓ / Dipengaruhi →
                        </th>
                        {forces.map((force, index) => (
                            <th
                                key={force.id}
                                scope="col"
                                className="sticky top-0 z-10 border-0 border-b border-solid border-border bg-card px-1 py-1.5 text-center font-semibold text-muted-foreground tabular-nums"
                            >
                                <abbr title={force.keyword} className="no-underline">
                                    {index + 1}
                                </abbr>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {forces.map((source, row) => (
                        <tr key={source.id}>
                            <th
                                scope="row"
                                className="sticky left-0 z-10 border-0 border-r border-b border-solid border-border bg-card px-2 py-1 text-left font-medium text-foreground"
                            >
                                <span className="mr-1.5 text-muted-foreground tabular-nums">{row + 1}.</span>
                                {source.keyword}
                            </th>
                            {forces.map((target) => {
                                if (source.id === target.id) {
                                    return (
                                        <td key={target.id} className="border-0 border-b border-solid border-border bg-muted/60 p-0.5">
                                            <span aria-hidden="true" className="block size-8" />
                                        </td>
                                    );
                                }
                                const cell = scores.get(`${source.id}|${target.id}`);
                                const score = cell?.score ?? 0;
                                const label = `Pengaruh ${source.keyword} terhadap ${target.keyword}: ${signedScore(score)}${cell?.note ? `, catatan: ${cell.note}` : ""}`;
                                const content = (
                                    <>
                                        {score === 0 ? "·" : signedScore(score)}
                                        {cell?.note && (
                                            <span aria-hidden="true" className="absolute top-0.5 right-0.5 size-1.5 rounded-full bg-primary" />
                                        )}
                                    </>
                                );
                                const inLoop = highlight?.has(`${source.id}|${target.id}`);
                                const className = cn(
                                    "relative flex size-8 items-center justify-center rounded font-semibold text-foreground tabular-nums transition-opacity",
                                    highlight && (inLoop ? "ring-2 ring-primary" : "opacity-25")
                                );

                                return (
                                    <td key={target.id} className="border-0 border-b border-solid border-border p-0.5">
                                        {canManage ? (
                                            <button
                                                type="button"
                                                aria-label={inLoop ? `${label}, bagian dari loop terpilih` : label}
                                                title={cell?.note || undefined}
                                                onClick={() => onEdit(source, target, cell)}
                                                className={cn(
                                                    className,
                                                    "cursor-pointer border-0 hover:ring-2 hover:ring-ring/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                                                    !score && "bg-transparent text-muted-foreground"
                                                )}
                                                style={{ backgroundColor: cellFill(score) }}
                                            >
                                                {content}
                                            </button>
                                        ) : (
                                            <span
                                                aria-label={inLoop ? `${label}, bagian dari loop terpilih` : label}
                                                title={cell?.note || undefined}
                                                className={cn(className, !score && "text-muted-foreground")}
                                                style={{ backgroundColor: cellFill(score) }}
                                            >
                                                {content}
                                            </span>
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ScoreDialog({ editing, onClose, onSaved }) {
    const [score, setScore] = useState("0");
    const [note, setNote] = useState("");
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (editing) {
            setScore(String(editing.cell?.score ?? 0));
            setNote(editing.cell?.note ?? "");
        }
    }, [editing]);

    const save = () => {
        setSaving(true);
        axios
            .post(route("visualization.cross-impact.update"), {
                source: editing.source.id,
                target: editing.target.id,
                score: Number(score),
                note: note || null,
            })
            .then((response) => {
                toast.success(response.data.message);
                onSaved(response.data.matrix);
                onClose();
            })
            .catch((error) => {
                const errors = error.response?.data?.errors;
                toast.error("Gagal menyimpan cross-impact.", {
                    description: errors ? Object.values(errors).flat().join(" ") : error.message,
                });
            })
            .finally(() => setSaving(false));
    };

    return (
        <Dialog open={!!editing} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Pengaruh antar driving force</DialogTitle>
                    <DialogDescription>
                        Seberapa kuat <strong className="text-foreground">{editing?.source.keyword}</strong> memengaruhi{" "}
                        <strong className="text-foreground">{editing?.target.keyword}</strong>?
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-3">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={score}
                        onValueChange={(value) => value && setScore(value)}
                        aria-label="Skor pengaruh"
                        className="w-full"
                    >
                        {SCORES.map((value) => (
                            <ToggleGroupItem
                                key={value}
                                value={String(value)}
                                aria-label={`${signedScore(value)}: ${SCORE_LABELS[value]}`}
                                className="flex-1 tabular-nums"
                            >
                                {value === 0 ? "0" : signedScore(value)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <p className="m-0 text-xs text-muted-foreground">{SCORE_LABELS[score]}</p>
                    <label className="flex flex-col gap-1.5 text-sm font-medium text-foreground">
                        Catatan (opsional)
                        <Textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            maxLength={1000}
                            rows={3}
                            placeholder="Mengapa force ini memengaruhi yang lain?"
                        />
                    </label>
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={onClose} disabled={saving}>
                        Batal
                    </Button>
                    <Button onClick={save} disabled={saving}>
                        {saving ? "Menyimpan…" : "Simpan"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function InfluenceTooltip({ active, payload }) {
    const row = active && payload?.[0]?.payload;
    if (!row) return null;

    return (
        <div className="flex flex-col gap-0.5 rounded-lg border border-solid border-border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-sm">
            <span className="font-semibold">{row.keyword}</span>
            <span className="text-muted-foreground">
                Aktif {row.active} · Pasif {row.passive} · {row.role_label}
            </span>
        </div>
    );
}

/** Vester map: active sum (x) against passive sum (y), split at the matrix averages. */
function InfluenceMap({ rows, thresholds }) {
    const scored = rows.filter((row) => row.role !== "unscored");
    const max = Math.max(4, ...scored.map((row) => Math.max(row.active, row.passive))) + 1;

    return (
        <div aria-hidden="true" className="h-[300px] w-full">
            <ResponsiveContainer width="100%" height="100%">
                <ScatterChart margin={{ top: 12, right: 16, bottom: 20, left: -8 }}>
                    <CartesianGrid stroke="var(--border)" strokeDasharray="4 4" />
                    <XAxis
                        type="number"
                        dataKey="active"
                        domain={[0, max]}
                        allowDecimals={false}
                        tick={AXIS}
                        stroke="var(--border)"
                        label={{ value: "Aktif (memengaruhi)", position: "insideBottom", offset: -12, ...AXIS }}
                    />
                    <YAxis
                        type="number"
                        dataKey="passive"
                        domain={[0, max]}
                        allowDecimals={false}
                        tick={AXIS}
                        stroke="var(--border)"
                        label={{ value: "Pasif", angle: -90, position: "insideLeft", offset: 20, ...AXIS }}
                    />
                    <ReferenceLine x={thresholds.active} stroke="var(--muted-foreground)" strokeDasharray="3 3" />
                    <ReferenceLine y={thresholds.passive} stroke="var(--muted-foreground)" strokeDasharray="3 3" />
                    <Tooltip content={<InfluenceTooltip />} cursor={{ strokeDasharray: "3 3", stroke: "var(--border)" }} />
                    <Scatter data={scored} fill="var(--chart-1)" stroke="var(--card)" strokeWidth={2} isAnimationActive={false} />
                </ScatterChart>
            </ResponsiveContainer>
        </div>
    );
}

function Summary({ matrix }) {
    const rows = matrix.summary;
    const scored = rows.filter((row) => row.role !== "unscored");

    return (
        <Panel
            icon={<ScatterChartIcon className="text-primary" />}
            title="Ringkasan Pengaruh per Driving Force"
            badge={<span className={cn(statusBadge(), "bg-accent text-primary")}>Metode Vester</span>}
            description={`Aktif = jumlah |skor| di baris (memengaruhi), Pasif = jumlah |skor| di kolom (dipengaruhi). Garis putus-putus adalah rata-rata matriks (aktif ${decimal.format(matrix.thresholds.active)}, pasif ${decimal.format(matrix.thresholds.passive)}).`}
        >
            {scored.length === 0 ? (
                <p className="m-0 py-8 text-center text-sm text-muted-foreground">
                    Belum ada pasangan yang dinilai. Isi matriks di atas untuk melihat peran tiap force.
                </p>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    <InfluenceMap rows={rows} thresholds={matrix.thresholds} />
                    <div className="max-h-[300px] overflow-auto">
                        <table className="w-full border-collapse text-xs">
                            <caption className="sr-only">Peran tiap driving force dalam matriks cross-impact</caption>
                            <thead>
                                <tr className="text-left text-muted-foreground">
                                    <th scope="col" className="sticky top-0 bg-card py-1.5 pr-2 font-semibold">Driving force</th>
                                    <th scope="col" className="sticky top-0 bg-card px-2 py-1.5 text-right font-semibold">Aktif</th>
                                    <th scope="col" className="sticky top-0 bg-card px-2 py-1.5 text-right font-semibold">Pasif</th>
                                    <th scope="col" className="sticky top-0 bg-card px-2 py-1.5 text-right font-semibold">Net</th>
                                    <th scope="col" className="sticky top-0 bg-card py-1.5 pl-2 font-semibold">Peran</th>
                                </tr>
                            </thead>
                            <tbody>
                                {scored.map((row) => (
                                    <tr key={row.id} className="border-0 border-t border-solid border-border">
                                        <th scope="row" className="py-1.5 pr-2 text-left font-medium text-foreground">
                                            <span className="flex flex-col gap-1">
                                                {row.keyword}
                                                <DimensionChip name={row.dimension} />
                                            </span>
                                        </th>
                                        <td className="px-2 text-right tabular-nums">{row.active}</td>
                                        <td className="px-2 text-right tabular-nums">{row.passive}</td>
                                        <td className="px-2 text-right tabular-nums">{signedScore(row.net)}</td>
                                        <td className="pl-2">
                                            <span className={cn(statusBadge(), ROLE_TONES[row.role])}>{row.role_label}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
            <dl className="m-0 grid gap-x-4 gap-y-1 text-xs text-muted-foreground sm:grid-cols-2">
                <div><dt className="inline font-semibold text-foreground">Pendorong</dt> <dd className="inline m-0">memengaruhi banyak, jarang dipengaruhi: tuas utama.</dd></div>
                <div><dt className="inline font-semibold text-foreground">Kritis</dt> <dd className="inline m-0">memengaruhi dan dipengaruhi kuat: perubahan cepat menjalar.</dd></div>
                <div><dt className="inline font-semibold text-foreground">Indikator</dt> <dd className="inline m-0">banyak dipengaruhi: cocok dipantau sebagai sinyal.</dd></div>
                <div><dt className="inline font-semibold text-foreground">Penyangga</dt> <dd className="inline m-0">pengaruh kecil ke dua arah.</dd></div>
            </dl>
        </Panel>
    );
}

export default function CrossImpact({ auth, title, canManage = false }) {
    const [matrix, setMatrix] = useState(null);
    const [editing, setEditing] = useState(null);
    const [selectedLoop, setSelectedLoop] = useState(null);

    useEffect(() => {
        axios
            .get(route("visualization.cross-impact.get-data"))
            .then((response) => setMatrix(response.data))
            .catch((error) => {
                toast.error("Gagal memuat matriks cross-impact.", { description: error.message });
                setMatrix({ forces: [], cells: [], summary: [], thresholds: { active: 0, passive: 0 }, scored_pairs: 0, possible_pairs: 0 });
            });
    }, []);

    const scores = useMemo(
        () => new Map((matrix?.cells ?? []).map((cell) => [`${cell.source}|${cell.target}`, cell])),
        [matrix]
    );
    const loop = selectedLoop === null ? null : matrix?.loops?.loops?.[selectedLoop];
    const highlight = useMemo(
        () => (loop ? new Set(loop.links.map((link) => `${link.source}|${link.target}`)) : null),
        [loop]
    );

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <GitCompareArrowsIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Pengaruh Antar Driving Force
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                {!matrix ? (
                    <>
                        <Skeleton className="h-96 w-full" />
                        <Skeleton className="h-72 w-full" />
                    </>
                ) : (
                    <>
                        <Panel
                            icon={<Grid3x3Icon className="text-primary" />}
                            title="Matriks Cross-Impact"
                            badge={
                                <span className={cn(statusBadge(), "bg-accent text-primary tabular-nums")}>
                                    {matrix.scored_pairs} dari {matrix.possible_pairs} pasangan dinilai
                                </span>
                            }
                            description={
                                canManage
                                    ? "Baris memengaruhi kolom. Klik sel untuk menilai pengaruh dari −3 (sangat menekan) sampai +3 (sangat memperkuat). Hanya driving force berstatus APPROVED."
                                    : "Baris memengaruhi kolom, dari −3 (sangat menekan) sampai +3 (sangat memperkuat). Hanya driving force berstatus APPROVED."
                            }
                        >
                            {matrix.forces.length < 2 ? (
                                <p className="m-0 py-8 text-center text-sm text-muted-foreground">
                                    Matriks butuh minimal dua driving force berstatus APPROVED.
                                </p>
                            ) : (
                                <>
                                    <Legend />
                                    <Matrix
                                        forces={matrix.forces}
                                        scores={scores}
                                        canManage={canManage}
                                        onEdit={(source, target, cell) => setEditing({ source, target, cell })}
                                        highlight={highlight}
                                    />
                                </>
                            )}
                        </Panel>
                        {matrix.loops && (
                            <LoopPanel
                                loops={matrix.loops}
                                forces={matrix.forces}
                                selected={selectedLoop}
                                onSelect={setSelectedLoop}
                            />
                        )}
                        <Summary matrix={matrix} />
                    </>
                )}
            </main>

            {canManage && <ScoreDialog editing={editing} onClose={() => setEditing(null)} onSaved={(next) => { setMatrix(next); setSelectedLoop(null); }} />}
        </AuthenticatedLayout>
    );
}
