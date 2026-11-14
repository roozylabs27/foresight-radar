import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { CONDITION_KINDS, IMPLICATION_TYPES, RESPONSE_FITS } from "@/domain/scenarioPlanning";
import { useDraft } from "@/hooks/use-draft";

const EMPTY = { title: "", description: "", rationale: "", initiatives: "", priority: "", owner: "", depends_on: "", fit: {}, implications: [], conditions: [] };
const NONE = "none";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Add or edit a strategic response with its fit in each quadrant. `target` is `{ response }`
 * (null response to add) or null when closed. Saving an approved response returns it to proposed.
 * `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function ResponseDialog({ target, quadrants, implications, conditions, owners, loading, onOpenChange, onSave }) {
    const { values, setValues, reset, dirty } = useDraft(EMPTY);
    const [errors, setErrors] = useState({});
    const response = target?.response;

    const resetForm = useEffectEvent(() => {
        if (!target) return;
        setErrors({});
        reset(response
            ? { ...EMPTY, ...response, description: response.description ?? "", rationale: response.rationale ?? "", initiatives: response.initiatives ?? "", depends_on: response.depends_on ?? "", priority: response.priority ? String(response.priority) : "", owner: response.owner?.id ?? "", fit: { ...response.fit }, implications: response.implications.map((item) => item.id), conditions: response.conditions.map((item) => item.id) }
            : EMPTY);
    });
    useEffect(() => resetForm(), [target, response?.id]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const setFit = (position, fit) => setValues((current) => ({ ...current, fit: { ...current.fit, [position]: fit } }));
    const toggle = (name, id) => set(name, values[name].includes(id) ? values[name].filter((item) => item !== id) : [...values[name], id]);
    const save = async () => {
        const result = await onSave({ ...values, priority: values.priority ? Number(values.priority) : null, owner: values.owner || null });
        if (result) return setErrors(result);
        onOpenChange(false);
    };
    const fitError = Object.keys(errors).find((key) => key === "fit" || key.startsWith("fit."));

    return (
        <FormDialog
            title={response ? "Ubah respons strategis" : "Respons strategis baru"}
            description={response?.status === "approved" ? "Respons ini sudah disetujui; menyimpan perubahan mengembalikannya menjadi usulan." : "Catat satu kali, lalu nilai kecocokannya di tiap kuadran."}
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan respons"
            cancelText="Batal"
            loading={loading}
            dirty={dirty}
            className="sm:max-w-2xl"
        >
            <div className="flex flex-col gap-5">
                <FormField id="response-title" label="Judul" error={error(errors, "title")}>
                    <Input id="response-title" value={values.title} onChange={(event) => set("title", event.target.value)} maxLength={160} placeholder="Contoh: Secure USP for each xEV model" />
                </FormField>
                <FormField id="response-rationale" label="Alasan (wajib untuk persetujuan)" required={false} error={error(errors, "rationale")}>
                    <Textarea id="response-rationale" value={values.rationale} onChange={(event) => set("rationale", event.target.value)} maxLength={2000} rows={2} />
                </FormField>
                <FormField id="response-initiatives" label="Inisiatif (satu per baris)" required={false} error={error(errors, "initiatives")}>
                    <Textarea id="response-initiatives" value={values.initiatives} onChange={(event) => set("initiatives", event.target.value)} maxLength={3000} rows={3} />
                </FormField>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="response-priority" label="Prioritas" required={false} error={error(errors, "priority")}>
                        <Select value={values.priority || NONE} onValueChange={(value) => set("priority", value === NONE ? "" : value)}>
                            <SelectTrigger id="response-priority" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Belum ditentukan</SelectItem>
                                {[1, 2, 3, 4, 5].map((rank) => (
                                    <SelectItem key={rank} value={String(rank)}>
                                        P{rank}
                                        {rank === 1 ? " (tertinggi)" : ""}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="response-owner" label="Penanggung jawab" required={false} error={error(errors, "owner")}>
                        <Select value={values.owner || NONE} onValueChange={(value) => set("owner", value === NONE ? "" : value)}>
                            <SelectTrigger id="response-owner" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Belum ditentukan</SelectItem>
                                {owners.map((owner) => (
                                    <SelectItem key={owner.value} value={owner.value}>
                                        {owner.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
                <FormField id="response-fit" label="Kecocokan per kuadran (wajib lengkap untuk persetujuan)" required={false} error={fitError ? { message: [].concat(errors[fitError])[0] } : undefined} group>
                    <div aria-labelledby="response-fit-label" className="flex flex-col gap-2">
                        {quadrants.map((quadrant) => (
                            <div key={quadrant.position} className="flex flex-wrap items-center justify-between gap-2">
                                <span className="min-w-0 text-sm text-foreground">{quadrant.title}</span>
                                <ToggleGroup type="single" variant="outline" size="sm" value={values.fit[quadrant.position] ?? ""} onValueChange={(value) => value && setFit(quadrant.position, value)} aria-label={`Kecocokan di ${quadrant.title}`}>
                                    {Object.entries(RESPONSE_FITS).map(([value, fit]) => (
                                        <ToggleGroupItem key={value} value={value} className="px-2.5 text-xs">
                                            {fit.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </div>
                        ))}
                    </div>
                </FormField>
                <FormField id="response-implications" label="Menjawab peluang atau tantangan" required={false} error={error(errors, "implications")} group>
                    {implications.length === 0 ? (
                        <p className="m-0 text-xs text-muted-foreground">Belum ada peluang atau tantangan (langkah 7).</p>
                    ) : (
                        <ul aria-labelledby="response-implications-label" className="m-0 flex max-h-36 list-none flex-col gap-1 overflow-y-auto p-0">
                            {implications.map((item) => (
                                <li key={item.id}>
                                    <label className="flex cursor-pointer items-start gap-2 rounded px-1 py-0.5 text-sm hover:bg-muted/50">
                                        <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={values.implications.includes(item.id)} onChange={() => toggle("implications", item.id)} />
                                        <span>
                                            <span className="text-xs text-muted-foreground">{item.position} · {IMPLICATION_TYPES[item.type]?.label}:</span> {item.title}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </FormField>
                <FormField id="response-conditions" label="Bergantung pada kondisi" required={false} error={error(errors, "conditions")} group>
                    {conditions.length === 0 ? (
                        <p className="m-0 text-xs text-muted-foreground">Belum ada kondisi skenario (langkah 5).</p>
                    ) : (
                        <ul aria-labelledby="response-conditions-label" className="m-0 flex max-h-36 list-none flex-col gap-1 overflow-y-auto p-0">
                            {conditions.map((item) => (
                                <li key={item.id}>
                                    <label className="flex cursor-pointer items-start gap-2 rounded px-1 py-0.5 text-sm hover:bg-muted/50">
                                        <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={values.conditions.includes(item.id)} onChange={() => toggle("conditions", item.id)} />
                                        <span>
                                            <span className="text-xs text-muted-foreground">{item.position} · {CONDITION_KINDS[item.kind]?.label}:</span> {item.text}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="m-0 text-xs text-muted-foreground">Respons ditandai untuk ditinjau bila salah satu kondisi ini diubah atau dihapus.</p>
                </FormField>
                <FormField id="response-depends" label="Asumsi lain (teks bebas)" required={false} error={error(errors, "depends_on")}>
                    <Input id="response-depends" value={values.depends_on} onChange={(event) => set("depends_on", event.target.value)} maxLength={1000} placeholder="Contoh: harga baterai terus turun" />
                </FormField>
                {errors.responses && <p role="alert" className="m-0 text-sm text-danger">{errors.responses}</p>}
            </div>
        </FormDialog>
    );
}
