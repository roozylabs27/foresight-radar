import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Label } from "@/Components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Switch } from "@/Components/ui/switch";
import { Textarea } from "@/Components/ui/textarea";
import { INCLUSION_STATUSES } from "@/domain/scenarioPlanning";

/**
 * What one driving force means for this exercise: included or not, its
 * influence on the focal issue, and whether it is a predetermined element.
 * `onSave(values)` resolves when the request finishes.
 */
export default function EditForceDialog({ link, onOpenChange, loading, onSave }) {
    const [values, setValues] = useState({ inclusion_status: "included", influence_note: "", is_predetermined: false });

    // Reset only when another force is opened, not on a props refresh while the dialog is open.
    useEffect(() => {
        if (link) {
            setValues({ inclusion_status: link.inclusion_status, influence_note: link.influence_note ?? "", is_predetermined: link.is_predetermined });
        }
    }, [link?.id]);

    const set = (name) => (value) => setValues((current) => ({ ...current, [name]: value }));
    const save = async () => {
        await onSave(values);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={link?.force?.keyword ?? "Driving force"}
            description={link ? `${link.force?.environment} · ${link.force?.dimension}. Perubahan hanya berlaku untuk latihan ini.` : undefined}
            open={Boolean(link)}
            onOpenChange={onOpenChange}
            onOk={save}
            isEditMode
            btnText="Simpan"
            cancelText="Batal"
            loading={loading}
            width={560}
        >
            <div className="flex flex-col gap-5">
                <FormField id="force-inclusion" label="Status di latihan ini">
                    <Select value={values.inclusion_status} onValueChange={set("inclusion_status")}>
                        <SelectTrigger id="force-inclusion" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent position="popper">
                            {Object.entries(INCLUSION_STATUSES).map(([value, meta]) => (
                                <SelectItem key={value} value={value}>{meta.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField id="force-influence" label="Pengaruh terhadap focal issue" required={false}>
                    <Textarea
                        id="force-influence"
                        value={values.influence_note}
                        onChange={(event) => set("influence_note")(event.target.value)}
                        maxLength={1000}
                        rows={4}
                        placeholder="Bagaimana driving force ini memengaruhi focal issue latihan?"
                    />
                </FormField>
                <div className="flex items-start gap-3">
                    <Switch id="force-predetermined" checked={values.is_predetermined} onCheckedChange={set("is_predetermined")} />
                    <Label htmlFor="force-predetermined" className="flex flex-col items-start gap-0.5 font-normal">
                        <span className="text-sm text-foreground">Predetermined element</span>
                        <span className="text-xs text-muted-foreground">Dampak tinggi tetapi cukup pasti: berlaku di semua skenario, tidak dipakai sebagai sumbu.</span>
                    </Label>
                </div>
            </div>
        </FormDialog>
    );
}
