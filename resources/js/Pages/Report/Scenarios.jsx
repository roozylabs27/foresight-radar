import { useCallback, useEffect, useId, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
    LayersIcon,
    LoaderCircleIcon,
    PlusIcon,
    SparklesIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ConfirmDialog from "@/Components/ConfirmDialog";
import DimensionChip from "@/Components/DimensionChip";
import FormDialog from "@/Components/FormDialog";
import Panel from "@/Components/Panel";
import QuadrantCard from "@/Components/Scenario/QuadrantCard";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Skeleton } from "@/Components/ui/skeleton";
import { Textarea } from "@/Components/ui/textarea";
import { isPending, pollUntil, providerLabel } from "@/lib/llm";
import { cn } from "@/lib/utils";

const FORM_ID = "scenario-form";
const EMPTY_FORM = {
    name: "",
    focal_question: "",
    axis_a: "",
    axis_b: "",
    axis_a_low_label: "",
    axis_a_high_label: "",
    axis_b_low_label: "",
    axis_b_high_label: "",
};
// Grid order: axis B high on top, axis A high on the right.
const GRID = ["LH", "HH", "LL", "HL"];

function errorText(error, fallback) {
    const data = error.response?.data;
    if (data?.errors && typeof data.errors === "object") return Object.values(data.errors).flat().join(" ");
    return data?.message ?? fallback;
}

function Field({ id, label, children, hint }) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <span className="text-xs text-muted-foreground">{hint}</span>}
        </div>
    );
}

/** New scenario: name, focal question and two axes, each with a low and a high pole. */
function ScenarioForm({ suggestions, values, onChange, onSubmit }) {
    const prefix = useId();
    const id = (name) => `${prefix}-${name}`;
    const forces = suggestions?.forces ?? [];
    const influence = useMemo(() => {
        if (!values.axis_a || !values.axis_b) return 0;
        const scores = (suggestions?.cross_impacts ?? [])
            .filter(
                (cell) =>
                    (cell.source === values.axis_a && cell.target === values.axis_b) ||
                    (cell.source === values.axis_b && cell.target === values.axis_a)
            )
            .map((cell) => Math.abs(cell.score));
        return Math.max(0, ...scores);
    }, [suggestions, values.axis_a, values.axis_b]);
    const set = (key) => (event) => onChange({ ...values, [key]: event.target.value });

    const axisSelect = (key, label) => (
        <Field id={id(key)} label={label}>
            <Select value={values[key]} onValueChange={(value) => onChange({ ...values, [key]: value })}>
                <SelectTrigger id={id(key)} className="w-full">
                    <SelectValue placeholder="Pilih driving force APPROVED" />
                </SelectTrigger>
                <SelectContent position="popper">
                    {forces.map((force) => (
                        <SelectItem key={force.id} value={force.id}>
                            {force.keyword} ({force.dimension ?? "-"})
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );

    return (
        <form id={FORM_ID} onSubmit={onSubmit} className="flex flex-col gap-4">
            {suggestions?.pairs?.length > 0 && (
                <div className="flex flex-col gap-1.5">
                    <span className="text-xs font-medium text-muted-foreground">
                        Usulan sumbu: impact × uncertainty tertinggi, beda dimensi, dan tidak saling memengaruhi kuat
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                        {suggestions.pairs.map((pair) => (
                            <Button
                                key={`${pair.a.id}-${pair.b.id}`}
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => onChange({ ...values, axis_a: pair.a.id, axis_b: pair.b.id })}
                            >
                                {pair.a.keyword} × {pair.b.keyword}
                            </Button>
                        ))}
                    </div>
                </div>
            )}
            <Field id={id("name")} label="Nama skenario">
                <Input id={id("name")} value={values.name} onChange={set("name")} required maxLength={255} />
            </Field>
            <Field id={id("focal")} label="Pertanyaan fokus" hint="Misalnya: Bagaimana pasar kendaraan listrik Indonesia pada 2030?">
                <Textarea id={id("focal")} value={values.focal_question} onChange={set("focal_question")} rows={2} maxLength={2000} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex flex-col gap-3">
                    {axisSelect("axis_a", "Sumbu A (horizontal)")}
                    <Field id={id("a-low")} label="Kutub rendah A">
                        <Input id={id("a-low")} value={values.axis_a_low_label} onChange={set("axis_a_low_label")} required maxLength={120} />
                    </Field>
                    <Field id={id("a-high")} label="Kutub tinggi A">
                        <Input id={id("a-high")} value={values.axis_a_high_label} onChange={set("axis_a_high_label")} required maxLength={120} />
                    </Field>
                </div>
                <div className="flex flex-col gap-3">
                    {axisSelect("axis_b", "Sumbu B (vertikal)")}
                    <Field id={id("b-low")} label="Kutub rendah B">
                        <Input id={id("b-low")} value={values.axis_b_low_label} onChange={set("axis_b_low_label")} required maxLength={120} />
                    </Field>
                    <Field id={id("b-high")} label="Kutub tinggi B">
                        <Input id={id("b-high")} value={values.axis_b_high_label} onChange={set("axis_b_high_label")} required maxLength={120} />
                    </Field>
                </div>
            </div>
            {influence >= (suggestions?.max_influence ?? 2) && (
                <p role="alert" className="m-0 flex items-start gap-2 rounded-md bg-warning-soft px-3 py-2 text-xs text-warning">
                    <TriangleAlertIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    Kedua sumbu saling memengaruhi kuat (|cross-impact| {influence}). Sumbu skenario sebaiknya independen agar
                    keempat kuadran masuk akal.
                </p>
            )}
        </form>
    );
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
    const editedCount = scenario.quadrants.filter((quadrant) => quadrant.edited).length;

    // The narrative is written by a queued job: poll until it completes or fails.
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
                onChanged(data);
            })
            .catch((error) => {
                if (!controller.signal.aborted) toast.error("Status narasi tidak dapat dimuat.", { description: errorText(error, error.message) });
            });
        return () => controller.abort();
        // onChanged is recreated on each parent render; polling restarts only for a new scenario or job.
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
                <div className="flex flex-wrap gap-x-6 gap-y-2 text-xs text-muted-foreground">
                    {[
                        ["A", scenario.axis_a],
                        ["B", scenario.axis_b],
                    ].map(([letter, axis]) => (
                        <span key={letter} className="flex flex-wrap items-center gap-1.5">
                            <b className="text-foreground">Sumbu {letter}:</b> {axis?.keyword ?? "(Faktor tidak tersedia)"}
                            {axis?.dimension && <DimensionChip name={axis.dimension} />}
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
                            disabled={generating}
                            aria-busy={generating || undefined}
                        >
                            {generating ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <SparklesIcon aria-hidden="true" />}
                            {scenario.quadrants.length > 0 ? "Buat ulang narasi" : "Buat narasi"}
                        </Button>
                        <ConfirmDialog
                            title="Hapus skenario ini?"
                            description="Skenario dan keempat kuadrannya dihapus permanen."
                            confirmText="Hapus"
                            onConfirm={remove}
                        >
                            <Button variant="outline" className="border-destructive/50 text-destructive">
                                <Trash2Icon aria-hidden="true" />
                                Hapus
                            </Button>
                        </ConfirmDialog>
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
                                    onSave={saveQuadrant(byPosition[position])}
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
                description={`${editedCount} kuadran sudah disunting analis. Membuat ulang narasi akan menimpa suntingan tersebut.`}
                confirmText="Timpa dan buat ulang"
                onConfirm={() => generate(true)}
            />
        </div>
    );
}

export default function Scenarios({ auth, title, canManage = false, llm = { available: false, model: null } }) {
    const [list, setList] = useState(null);
    const [selected, setSelected] = useState(null);
    const [detail, setDetail] = useState(null);
    const [suggestions, setSuggestions] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [saving, setSaving] = useState(false);

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

    const openForm = () => {
        setForm(EMPTY_FORM);
        setFormOpen(true);
        if (!suggestions) {
            axios.get(route("scenarios.suggest-axes")).then((response) => setSuggestions(response.data));
        }
    };

    const create = async (event) => {
        event.preventDefault();
        setSaving(true);
        try {
            const response = await axios.post(route("scenarios.store"), form);
            toast.success(response.data.message);
            setFormOpen(false);
            await loadList();
            setSelected(response.data.scenario.id);
            setDetail(response.data.scenario);
        } catch (error) {
            toast.error("Skenario tidak tersimpan.", { description: errorText(error, error.message) });
        } finally {
            setSaving(false);
        }
    };

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
                <Panel
                    title="Daftar skenario"
                    badge={
                        canManage && (
                            <Button size="sm" onClick={openForm}>
                                <PlusIcon aria-hidden="true" />
                                Skenario baru
                            </Button>
                        )
                    }
                >
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
                        canManage={canManage}
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
                        {list?.length === 0
                            ? canManage
                                ? "Buat skenario pertama dengan memilih dua driving force sebagai sumbu."
                                : "Belum ada skenario yang dibuat."
                            : "Pilih skenario dari daftar."}
                    </p>
                )}
            </main>

            {canManage && (
                <FormDialog
                    title="Skenario baru"
                    description="Pilih dua driving force APPROVED sebagai sumbu, lalu beri nama kedua kutubnya."
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    formId={FORM_ID}
                    btnText="Buat skenario"
                    loading={saving}
                    width={720}
                >
                    <ScenarioForm suggestions={suggestions} values={form} onChange={setForm} onSubmit={create} />
                </FormDialog>
            )}
        </AuthenticatedLayout>
    );
}
