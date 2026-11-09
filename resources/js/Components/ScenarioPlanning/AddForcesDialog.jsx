import { useMemo, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import { Input } from "@/Components/ui/input";
import { filterLibrary } from "@/domain/scenarioPlanning";

/**
 * Pick driving forces from the organization's library (any month) to add to
 * the exercise. `onAdd(ids)` resolves when the request finishes.
 */
export default function AddForcesDialog({ open, onOpenChange, library, loading, agreed, onAdd }) {
    const [query, setQuery] = useState("");
    const [selected, setSelected] = useState([]);
    const visible = useMemo(() => filterLibrary(library, query), [library, query]);

    const toggle = (id) => setSelected((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
    const close = () => {
        setSelected([]);
        setQuery("");
    };
    const submit = async () => {
        await onAdd(selected);
        close();
        onOpenChange(false);
    };

    return (
        <FormDialog
            title="Tambah dari pustaka driving force"
            description={agreed
                ? "Daftar sudah disepakati: menambah driving force membatalkan kesepakatan daftar (G2) dan semua kesepakatan sesudahnya (skor, critical uncertainty, kerangka)."
                : "Pilih driving force organisasi yang memengaruhi focal issue ini. Driving force dari bulan mana pun ditampilkan."}
            open={open}
            onOpenChange={onOpenChange}
            onCancel={close}
            onOk={submit}
            btnText={selected.length > 0 ? `Tambahkan (${selected.length})` : "Tambahkan"}
            cancelText="Batal"
            loading={loading}
            className="sm:max-w-2xl"
        >
            <div className="flex flex-col gap-3">
                <Input
                    type="search"
                    aria-label="Cari driving force"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari kata kunci, kategori, atau deskripsi..."
                />
                {visible.length === 0 ? (
                    <p className="m-0 py-6 text-center text-sm text-muted-foreground">
                        {library.length === 0 ? "Semua driving force organisasi sudah ada di latihan ini." : "Tidak ada yang cocok."}
                    </p>
                ) : (
                    <ul className="m-0 flex max-h-96 list-none flex-col gap-1 overflow-y-auto p-0">
                        {visible.map((force) => (
                            <li key={force.id}>
                                <label className="flex cursor-pointer items-start gap-3 rounded-md border border-border p-2.5 hover:bg-muted/40">
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4 accent-primary"
                                        checked={selected.includes(force.id)}
                                        onChange={() => toggle(force.id)}
                                    />
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-sm font-medium text-foreground">{force.keyword}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {force.environment} · {force.dimension} · {force.signals_count} sinyal · {force.status}
                                        </span>
                                        <span className="line-clamp-2 text-xs text-muted-foreground">{force.description}</span>
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </FormDialog>
    );
}
