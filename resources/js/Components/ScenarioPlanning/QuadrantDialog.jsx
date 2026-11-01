import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

/**
 * Name and one-line summary of a framework quadrant. `quadrant` null closes the dialog.
 * `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function QuadrantDialog({ quadrant, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState({ title: "", summary: "" });
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (!quadrant) return;
        setErrors({});
        setValues({ title: quadrant.title, summary: quadrant.summary ?? "" });
    }, [quadrant?.id]);

    const set = (name) => (event) => setValues((current) => ({ ...current, [name]: event.target.value }));
    const save = async () => {
        const result = await onSave(values);
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title="Nama dan ringkasan kuadran"
            description={quadrant ? `${quadrant.poles[0]} × ${quadrant.poles[1]}` : undefined}
            open={quadrant !== null}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan kuadran"
            cancelText="Batal"
            loading={loading}
        >
            <div className="flex flex-col gap-5">
                <FormField id="quadrant-title" label="Nama skenario" error={error(errors, "title")}>
                    <Input id="quadrant-title" value={values.title} onChange={set("title")} maxLength={120} placeholder="Contoh: Green Acceleration" />
                </FormField>
                <FormField id="quadrant-summary" label="Ringkasan satu kalimat" required={false} error={error(errors, "summary")}>
                    <Textarea id="quadrant-summary" value={values.summary} onChange={set("summary")} maxLength={200} rows={3} placeholder="Gambaran singkat masa depan di kuadran ini." />
                </FormField>
                {errors.framework && <p role="alert" className="m-0 text-sm text-danger">{errors.framework}</p>}
            </div>
        </FormDialog>
    );
}
