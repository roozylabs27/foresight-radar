import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";

/**
 * Add or rename an environment (a group of dimensions, by default Macro and Mezzo). `target` is
 * `{ environment }` (null for a new one) or null when closed. `onSave(values)` resolves to the 422
 * errors or undefined.
 */
export default function EnvironmentDialog({ target, loading, onOpenChange, onSave }) {
    const [name, setName] = useState("");
    const [errors, setErrors] = useState({});
    const environment = target?.environment;

    const resetForm = useEffectEvent(() => {
        if (!target) return;
        setErrors({});
        setName(environment?.name ?? "");
    });
    useEffect(() => resetForm(), [target, environment?.id]);

    const save = async () => {
        const result = await onSave({ name });
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={environment ? "Ubah lingkungan" : "Lingkungan baru"}
            description="Lingkungan mengelompokkan dimensi, misalnya Macro dan Mezzo."
            open={target !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan lingkungan"
            cancelText="Batal"
            loading={loading}
        >
            <FormField id="environment-name" label="Nama" error={errors.name ? { message: [].concat(errors.name)[0] } : undefined}>
                <Input id="environment-name" value={name} onChange={(event) => setName(event.target.value)} maxLength={100} placeholder="Contoh: Micro" />
            </FormField>
        </FormDialog>
    );
}
