import { useEffect, useEffectEvent, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { Textarea } from "@/Components/ui/textarea";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";

/**
 * Approve a critical uncertainty as axis A or B (gate G4). When the server
 * reports that the two axes strongly influence each other, the dialog asks for
 * an explicit acknowledgement and a reason (roadmap #13) before trying again; otherwise the reason is optional.
 */
export default function ApproveUncertaintyDialog({ uncertainty, takenAxes, loading, onOpenChange, onApprove }) {
    const [axis, setAxis] = useState("A");
    const [acknowledge, setAcknowledge] = useState(false);
    const [rationale, setRationale] = useState("");
    const [errors, setErrors] = useState({});

    // Reset only when another uncertainty is opened: props are new objects after every Inertia
    // reload, and resetting on those would wipe the server's errors as soon as they arrive.
    const resetForm = useEffectEvent(() => {
        if (!uncertainty) return;
        setErrors({});
        setAcknowledge(false);
        setRationale("");
        setAxis(takenAxes.includes("A") ? "B" : "A");
    });
    useEffect(() => resetForm(), [uncertainty?.id]);

    const approve = async () => {
        const result = await onApprove(axis, acknowledge, rationale.trim() || null);
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog
            title={uncertainty ? `Setujui "${uncertainty.name}"` : "Setujui"}
            description="Dua critical uncertainty yang disetujui menjadi sumbu matriks skenario 2×2. Keputusan ini dicatat beserta nama Anda."
            open={Boolean(uncertainty)}
            onOpenChange={onOpenChange}
            onOk={approve}
            btnText="Setujui"
            cancelText="Batal"
            loading={loading}
            width={520}
        >
            <div className="flex flex-col gap-4">
                <FormField id="approve-axis" label="Sumbu" group error={errors.axis ? { message: [].concat(errors.axis)[0] } : undefined}>
                    <ToggleGroup type="single" variant="outline" value={axis} onValueChange={(value) => value && setAxis(value)} aria-labelledby="approve-axis-label">
                        {["A", "B"].map((value) => (
                            <ToggleGroupItem key={value} value={value} disabled={takenAxes.includes(value)} className="px-4">
                                Sumbu {value}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </FormField>
                {errors.forces && <p role="alert" className="m-0 text-sm text-destructive">{[].concat(errors.forces)[0]}</p>}
                {errors.acknowledge_dependency && (
                    <div role="alert" className="flex flex-col gap-2 rounded-md bg-warning-soft p-3 text-sm text-warning">
                        <p className="m-0">{[].concat(errors.acknowledge_dependency)[0]}</p>
                        <label className="flex items-center gap-2 text-foreground">
                            <input type="checkbox" className="size-4 accent-primary" checked={acknowledge} onChange={(event) => setAcknowledge(event.target.checked)} />
                            Saya paham dan tetap memakai pasangan sumbu ini.
                        </label>
                    </div>
                )}
                <FormField id="approve-rationale" label={acknowledge ? "Alasan (wajib karena peringatan diabaikan)" : "Alasan (opsional)"} error={errors.rationale ? { message: [].concat(errors.rationale)[0] } : undefined}>
                    <Textarea id="approve-rationale" value={rationale} maxLength={1000} onChange={(event) => setRationale(event.target.value)} placeholder="Dicatat di riwayat keputusan." />
                </FormField>
            </div>
        </FormDialog>
    );
}
