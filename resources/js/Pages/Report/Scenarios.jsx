import { useCallback, useEffect, useEffectEvent, useMemo, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
    BotIcon,
    LayersIcon,
    LoaderCircleIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DimensionChip from "@/Components/DimensionChip";
import Panel from "@/Components/Panel";
import QuadrantCard from "@/Components/Scenario/QuadrantCard";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Skeleton } from "@/Components/ui/skeleton";
import { isPending, pollUntil, providerLabel } from "@/lib/llm";
import { cn } from "@/lib/utils";

// Grid order: axis B high on top, axis A high on the right.
const GRID = ["LH", "HH", "LL", "HL"];

function errorText(error, fallback) {
    const data = error.response?.data;
    if (data?.errors && typeof data.errors === "object") return Object.values(data.errors).flat().join(" ");
    return data?.message ?? fallback;
}

function ScenarioDetail({ scenario, canManage, llm, onChanged, onDeleted }) {
    const [requesting, setRequesting] = useState(false);
    const [confirmOverwrite, setConfirmOverwrite] = useState(false);
    const generation = scenario.generation;
    const pending = isPending(generation?.status);
    const generating = requesting || pending;
    const evidence = useMemo(
        () => Object.fromEntries((scenario.evidence ?? []).map((item) => [item.ref, item])),
        [scenario.evidence]
    );
    const byPosition = Object.fromEntries(scenario.quadrants.map((quadrant) => [quadrant.position, quadrant]));
    // Edited or reviewed quadrants hold human work a regeneration would replace.
    const editedCount = scenario.quadrants.filter((quadrant) => quadrant.edited || ["reviewed", "approved"].includes(quadrant.review_status)).length;

    // The narrative is written by a queued job: poll until it completes or fails.
    const notifyChanged = useEffectEvent((data) => onChanged(data));
    useEffect(() => {
        if (!pending) return undefined;
        const controller = new AbortController();
        pollUntil(
            () => axios.get(route("scenarios.show", scenario.id), { signal: controller.signal }).then((response) => response.data),
            (data) => !isPending(data.generation?.status),
            { signal: controller.signal }
        )
            .then((data) => {
                if (controller.signal.aborted) return;
                // The result arrives while the analyst may be looking elsewhere, so it stays up longer than a default toast.
                const duration = 12000;
                if (!data) {
                    toast.info("Narasi masih dalam antrean.", { description: "Buka lagi skenario ini nanti untuk melihat hasilnya.", duration });
                    return;
                }
                const result = data.generation;
                if (result?.status === "failed") toast.error("Narasi tidak dibuat.", { description: result.error, duration });
                else if (result?.notice) toast.warning(result.message, { description: result.notice, duration });
                else toast.success(result?.message ?? "Narasi dibuat.", { duration });
                notifyChanged(data);
            })
            .catch((error) => {
                if (!controller.signal.aborted) toast.error("Status narasi tidak dapat dimuat.", { description: errorText(error, error.message) });
            });
        return () => controller.abort();
    }, [scenario.id, pending]);

    const generate = async (overwrite = false) => {
        setRequesting(true);
        try {
            const response = await axios.post(route("scenarios.generate", scenario.id), { overwrite_edited: overwrite });
            onChanged(response.data.scenario);
        } catch (error) {
            toast.error("Narasi tidak dibuat.", { description: errorText(error, error.message) });
        } finally {
            setRequesting(false);
        }
    };

    const saveQuadrant = (quadrant) => async (values) => {
        try {
            const response = await axios.put(route("scenarios.quadrant", [scenario.id, quadrant.id]), values);
            toast.success(response.data.message);
            onChanged(response.data.scenario);
            return true;
        } catch (error) {
            toast.error("Kuadran tidak tersimpan.", { description: errorText(error, error.message) });
            return false;
        }
    };

    const reviewQuadrant = (quadrant) => async (status) => {
        try {
            const response = await axios.post(route("scenarios.quadrant.review", [scenario.id, quadrant.id]), { status });
            toast.success(response.data.message);
            onChanged(response.data.scenario);
        } catch (error) {
            toast.error("Status tinjauan tidak tersimpan.", { description: errorText(error, error.message) });
        }
    };

    const remove = async () => {
        await axios.delete(route("scenarios.destroy", scenario.id));
        toast.success("Skenario dihapus.");
        onDeleted();
    };

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <Panel
                icon={<LayersIcon className="text-primary" />}
                title={scenario.name}
                badge={
                    scenario.provider && (
                        <span className={cn(statusBadge(), scenario.provider !== "template" ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
                            {scenario.provider !== "template" ? `${providerLabel(scenario.provider)} · ${scenario.model ?? ""}` : "Kerangka tanpa AI"}
                        </span>
                    )
                }
                description={scenario.focal_question || "Belum ada pertanyaan fokus."}
            >
                {scenario.project && (
                    <p className="m-0 text-xs text-muted-foreground">
                        Kerangka dari latihan{" "}
                        <Link href={route("scenario-planning.framework.index", scenario.project.id)} className="font-medium text-primary hover:underline">
                            {scenario.project.name}
                        </Link>
                        {scenario.framework_status === "approved" ? " · disetujui (G5)" : " · draf: setujui kerangka di latihan sebelum membuat narasi"}
                    </p>
                )}
                <div className="flex flex-wrap gap-x-6 gap-y-2 text-xs text-muted-foreground">
                    {[
                        ["A", scenario.axis_a],
                        ["B", scenario.axis_b],
                    ].map(([letter, axis]) => (
                        <span key={letter} className="flex flex-wrap items-center gap-1.5">
                            <b className="text-foreground">Sumbu {letter}:</b> {axis?.keyword ?? "(Faktor tidak tersedia)"}
                            {axis?.dimension && (scenario.project ? <span>[{axis.dimension}]</span> : <DimensionChip name={axis.dimension} />)}
                            {axis?.is_deleted && (
                                <span className="rounded bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive">
                                    Dihapus
                                </span>
                            )}
                            <span>
                                ({axis?.low ?? "-"} ↔ {axis?.high ?? "-"})
                            </span>
                        </span>
                    ))}
                </div>
                {canManage && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            onClick={() => (editedCount > 0 ? setConfirmOverwrite(true) : generate(false))}
                            disabled={generating || (scenario.project && scenario.framework_status !== "approved")}
                            aria-busy={generating || undefined}
                        >
                            {generating ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <BotIcon aria-hidden="true" />}
                            {scenario.quadrants.some((item) => item.narrative) ? "Buat ulang narasi" : "Buat narasi"}
                        </Button>
                        {scenario.framework_status !== "approved" && <ConfirmDialog
                            title="Hapus skenario ini?"
                            description="Skenario dan keempat kuadrannya dihapus permanen."
                            confirmText="Hapus"
                            onConfirm={remove}
                        >
                            <Button variant="outline" className="border-destructive/50 text-destructive">
                                <Trash2Icon aria-hidden="true" />
                                Hapus
                            </Button>
                        </ConfirmDialog>}
                        <span role="status" className="text-xs text-muted-foreground">
                            {generating
                                ? llm.available
                                    ? `${llm.label} sedang menulis empat kuadran; biasanya 10–150 detik. Halaman ini boleh ditinggalkan.`
                                    : "Menyusun kerangka…"
                                : llm.available
                                  ? `Narasi ditulis oleh ${llm.label} (${llm.model}) dari sinyal ACCEPTED yang tertaut ke kedua sumbu.`
                                  : "Tanpa API key: tombol ini membuat kerangka template tanpa AI."}
                        </span>
                    </div>
                )}
                {generation?.status === "failed" && !generating && (
                    <p role="alert" className="m-0 flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">
                        <TriangleAlertIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                        {generation.error}
                    </p>
                )}
            </Panel>

            {scenario.quadrants.length === 0 ? (
                <p className="m-0 rounded-lg border border-dashed border-border py-12 text-center text-sm text-muted-foreground">
                    Belum ada kuadran. {canManage ? "Klik “Buat narasi” untuk menyusunnya." : ""}
                </p>
            ) : (
                <section aria-label="Matriks skenario 2×2" className="flex flex-col gap-2">
                    <div className="flex justify-between text-[11px] text-muted-foreground">
                        <span>↑ {scenario.axis_b.keyword}: {scenario.axis_b.high}</span>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        {GRID.map((position) =>
                            byPosition[position] ? (
                                <QuadrantCard
                                    key={position}
                                    quadrant={byPosition[position]}
                                    evidence={evidence}
                                    canManage={canManage}
                                    titleLocked={scenario.framework_status === "approved"}
                                    onSave={saveQuadrant(byPosition[position])}
                                    onReview={reviewQuadrant(byPosition[position])}
                                />
                            ) : null
                        )}
                    </div>
                    <div className="flex justify-between text-[11px] text-muted-foreground">
                        <span>↓ {scenario.axis_b.keyword}: {scenario.axis_b.low}</span>
                        <span>
                            {scenario.axis_a.keyword}: {scenario.axis_a.low} ← → {scenario.axis_a.high}
                        </span>
                    </div>
                </section>
            )}

            {scenario.evidence?.length > 0 && (
                <Panel title={`Bukti (${scenario.evidence.length} sinyal)`} description="Sinyal ACCEPTED yang tertaut ke kedua sumbu, dengan label yang dikutip narasi.">
                    <ul className="m-0 flex list-none flex-col gap-2 p-0 text-xs">
                        {scenario.evidence.map((item) => (
                            <li key={item.ref} className="flex flex-col gap-0.5">
                                <span className="font-semibold text-foreground">
                                    {item.ref} · {item.title}
                                </span>
                                {item.evidence_quote && <span className="text-muted-foreground italic">“{item.evidence_quote}”</span>}
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}

            <ConfirmDialog
                open={confirmOverwrite}
                onOpenChange={setConfirmOverwrite}
                title="Timpa kuadran yang sudah disunting?"
                description={`${editedCount} kuadran sudah disunting atau ditinjau. Membuat ulang narasi akan menimpanya dan mengembalikan statusnya ke draf.`}
                confirmText="Timpa dan buat ulang"
                onConfirm={() => generate(true)}
            />
        </div>
    );
}

/** PAD-2 (2026-11-13): read-only archive of stand-alone scenarios; exercise frameworks are edited in the exercise. */
export default function Scenarios({ auth, title, llm = { available: false, model: null } }) {
    const [list, setList] = useState(null);
    const [selected, setSelected] = useState(null);
    const [detail, setDetail] = useState(null);

    const loadList = useCallback(
        () =>
            axios
                .get(route("scenarios.fetch"))
                .then((response) => {
                    setList(response.data);
                    return response.data;
                })
                .catch((error) => {
                    toast.error("Gagal memuat skenario.", { description: error.message });
                    setList([]);
                    return [];
                }),
        []
    );

    useEffect(() => {
        loadList().then((rows) => rows[0] && setSelected(rows[0].id));
    }, [loadList]);

    useEffect(() => {
        if (!selected) {
            setDetail(null);
            return;
        }
        axios
            .get(route("scenarios.show", selected))
            .then((response) => setDetail(response.data))
            .catch((error) => toast.error("Gagal memuat skenario.", { description: error.message }));
    }, [selected]);

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <LayersIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>Matriks 2×2</span>
                </div>
            }
        >
            <Head title={title} />

            <main className="grid flex-1 gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)] lg:px-6 lg:py-5">
                <p role="note" className="m-0 rounded-md bg-muted/60 p-3 text-sm text-muted-foreground lg:col-span-2">
                    Arsip: skenario lepas di halaman ini hanya bisa dilihat. Skenario baru disusun di dalam latihan{" "}
                    <Link href={route("scenario-planning.index")} className="font-medium text-primary hover:underline">
                        Scenario Planning
                    </Link>
                    {" "}(langkah 4–6), dengan gerbang persetujuannya.
                </p>
                <Panel title="Daftar skenario">
                    {list === null ? (
                        <Skeleton className="h-40 w-full" />
                    ) : list.length === 0 ? (
                        <p className="m-0 py-6 text-center text-sm text-muted-foreground">Belum ada skenario.</p>
                    ) : (
                        <ul aria-label="Daftar skenario" className="m-0 flex list-none flex-col gap-1.5 p-0">
                            {list.map((row) => (
                                <li key={row.id}>
                                    <button
                                        type="button"
                                        aria-current={row.id === selected || undefined}
                                        onClick={() => setSelected(row.id)}
                                        className={cn(
                                            "flex w-full cursor-pointer flex-col gap-0.5 rounded-md border border-solid border-border bg-card px-3 py-2 text-left hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                                            row.id === selected && "border-primary bg-accent/50"
                                        )}
                                    >
                                        <span className="text-[13px] font-semibold text-foreground">{row.name}</span>
                                        <span className="text-[11px] text-muted-foreground">
                                            {row.axis_a ?? "(Faktor dihapus)"} × {row.axis_b ?? "(Faktor dihapus)"}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                {detail ? (
                    <ScenarioDetail
                        key={detail.id}
                        scenario={detail}
                        canManage={false}
                        llm={llm}
                        onChanged={(next) => {
                            setDetail(next);
                            loadList();
                        }}
                        onDeleted={async () => {
                            const rows = await loadList();
                            setSelected(rows[0]?.id ?? null);
                        }}
                    />
                ) : (
                    <p className="m-0 rounded-lg border border-dashed border-border py-16 text-center text-sm text-muted-foreground">
                        {list?.length === 0 ? "Belum ada skenario di arsip." : "Pilih skenario dari daftar."}
                    </p>
                )}
            </main>

        </AuthenticatedLayout>
    );
}
