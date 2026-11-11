import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { DIMENSION_COLORS, dimensionColor } from "@/domain/dimensions";

const AUTO = "auto";
const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Add or edit one dimension of the organisation. `target` is `{ dimension, environmentId }` (null
 * dimension for a new one) or null when closed; `environments` are `{ id, name }`. The code is set
 * once from the name and is not editable. `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function DimensionDialog({ target, environments, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState({ name: "", environment: "", color: AUTO });
    const [errors, setErrors] = useState({});
    const dimension = target?.dimension;

    useEffect(() => {
        if (!target) return;
        setErrors({});
        setValues({ name: dimension?.name ?? "", environment: target.environmentId ?? environments[0]?.id ?? "", color: dimension?.color ?? AUTO });
    }, [target, dimension?.id]);

    const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        const result = await onSave({ ...values, color: values.color === AUTO ? null : values.color });
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={dimension ? "Ubah dimensi" : "Dimensi baru"}
            description={dimension ? `Kode ${dimension.code} tetap; nama, lingkungan dan warna bisa diubah.` : "Kode dibuat dari nama dan tidak berubah setelahnya."}
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan dimensi"
            cancelText="Batal"
            loading={loading}
        >
            <div className="flex flex-col gap-5">
                <FormField id="dimension-name" label="Nama" error={error(errors, "name")}>
                    <Input id="dimension-name" value={values.name} onChange={(event) => set("name", event.target.value)} maxLength={100} placeholder="Contoh: AI Policy" />
                </FormField>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="dimension-environment" label="Lingkungan" error={error(errors, "environment")}>
                        <Select value={values.environment} onValueChange={(value) => set("environment", value)}>
                            <SelectTrigger id="dimension-environment" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {environments.map((environment) => (
                                    <SelectItem key={environment.id} value={environment.id}>
                                        {environment.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField id="dimension-color" label="Warna" required={false} error={error(errors, "color")}>
                        <Select value={values.color} onValueChange={(value) => set("color", value)}>
                            <SelectTrigger id="dimension-color" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={AUTO}>Otomatis</SelectItem>
                                {Object.entries(DIMENSION_COLORS).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        <span aria-hidden="true" className="inline-block size-3 rounded-full" style={{ backgroundColor: dimensionColor(key) }} />
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
            </div>
        </FormDialog>
    );
}
