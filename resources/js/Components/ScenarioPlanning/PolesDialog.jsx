import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Input } from "@/Components/ui/input";

const error = (errors, name) => (errors[name] ? { message: [].concat(errors[name])[0] } : undefined);

const valuesOf = (axes) => ({
    axis_a_low_label: axes[0].low,
    axis_a_high_label: axes[0].high,
    axis_b_low_label: axes[1].low,
    axis_b_high_label: axes[1].high,
});

/**
 * Edit the four pole labels of a framework. Quadrants still carrying the default
 * "{pole A} · {pole B}" name follow the new labels. `onSave` resolves to the 422 errors or undefined.
 */
export default function PolesDialog({ open, axes, loading, onOpenChange, onSave }) {
    const [values, setValues] = useState(() => valuesOf(axes));
    const [errors, setErrors] = useState({});

    const resetForm = useEffectEvent(() => {
        if (!open) return;
        setErrors({});
        setValues(valuesOf(axes));
    });
    useEffect(() => resetForm(), [open]);

    const set = (name) => (event) => setValues((current) => ({ ...current, [name]: event.target.value }));
    const save = async () => {
        const result = await onSave(values);
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title="Ubah kutub sumbu"
            description="Kutub adalah dua kondisi ekstrem dari satu critical uncertainty."
            open={open}
            onOpenChange={onOpenChange}
            onOk={save}
            btnText="Simpan kutub"
            cancelText="Batal"
            loading={loading}
        >
            <div className="flex flex-col gap-5">
                {axes.map((axis) => {
                    const key = axis.axis.toLowerCase();
                    return (
                        <fieldset key={axis.axis} className="m-0 grid gap-3 border-0 p-0 sm:grid-cols-2">
                            <legend className="mb-2 text-sm font-semibold text-foreground">
                                Sumbu {axis.axis} · {axis.name}
                            </legend>
                            <FormField id={`pole-${key}-low`} label="Kutub rendah (L)" error={error(errors, `axis_${key}_low_label`)}>
                                <Input id={`pole-${key}-low`} value={values[`axis_${key}_low_label`]} onChange={set(`axis_${key}_low_label`)} maxLength={120} />
                            </FormField>
                            <FormField id={`pole-${key}-high`} label="Kutub tinggi (H)" error={error(errors, `axis_${key}_high_label`)}>
                                <Input id={`pole-${key}-high`} value={values[`axis_${key}_high_label`]} onChange={set(`axis_${key}_high_label`)} maxLength={120} />
                            </FormField>
                        </fieldset>
                    );
                })}
                {errors.framework && <p role="alert" className="m-0 text-sm text-danger">{errors.framework}</p>}
            </div>
        </FormDialog>
    );
}
