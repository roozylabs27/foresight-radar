import { useEffect, useState } from "react";

import FormDialog from "@/Components/FormDialog";
import FormField from "@/Components/FormField";
import { statusBadge } from "@/Components/StatusBadge";
import { Switch } from "@/Components/ui/switch";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { SIGNAL_HORIZONS, WILD_CARD } from "@/domain/signals";
import { cn } from "@/lib/utils";

/** Roadmap R4.2: the horizon and wild card badges of a signal; nothing when unclassified. */
export function SensingBadges({ signal, className }) {
    const horizon = SIGNAL_HORIZONS[signal.horizon];
    if (!horizon && !signal.wild_card) return null;
    return (
        <span className={cn("inline-flex flex-wrap gap-1", className)}>
            {horizon && <span title={horizon.hint} className={statusBadge({ tone: horizon.tone })}>{horizon.label}</span>}
            {signal.wild_card && <span title={WILD_CARD.hint} className={statusBadge({ tone: "rejected" })}>{WILD_CARD.label}</span>}
        </span>
    );
}

/**
 * Classify a signal by horizon and wild card (deck slide 26). `signal` is the row or null when
 * closed; `onSave(values)` resolves to the 422 errors or undefined.
 */
export default function ClassifyDialog({ signal, loading, onOpenChange, onSave }) {
    const [horizon, setHorizon] = useState("");
    const [wildCard, setWildCard] = useState(false);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (!signal) return;
        setErrors({});
        setHorizon(signal.horizon ?? "");
        setWildCard(Boolean(signal.wild_card));
    }, [signal?.uuid]);

    const save = async () => {
        const result = await onSave({ horizon: horizon || null, wild_card: wildCard });
        if (result) return setErrors(result);
        onOpenChange(false);
    };

    return (
        <FormDialog title="Klasifikasi sinyal" description={signal?.title} open={signal !== null} onOpenChange={onOpenChange} onOk={save} btnText="Simpan klasifikasi" cancelText="Batal" loading={loading}>
            <div className="flex flex-col gap-5">
                <FormField id="signal-horizon" label="Horizon" required={false} error={errors.horizon ? { message: [].concat(errors.horizon)[0] } : undefined} group>
                    <ToggleGroup type="single" variant="outline" value={horizon} onValueChange={setHorizon} aria-labelledby="signal-horizon-label" className="flex-wrap">
                        {Object.entries(SIGNAL_HORIZONS).map(([value, item]) => (
                            <ToggleGroupItem key={value} value={value} className="px-3 text-xs">
                                {item.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <p className="m-0 mt-1.5 text-xs text-muted-foreground">{SIGNAL_HORIZONS[horizon]?.hint ?? "Pilih lagi untuk mengosongkan."}</p>
                </FormField>
                {/* The label sits beside the switch, not around it: a wrapping label would toggle it twice. */}
                <div className="flex items-start gap-3 text-sm text-foreground">
                    <Switch id="signal-wild-card" checked={wildCard} onCheckedChange={setWildCard} aria-describedby="signal-wild-card-hint" />
                    <span className="flex flex-col">
                        <label htmlFor="signal-wild-card" className="font-medium">{WILD_CARD.label}</label>
                        <span id="signal-wild-card-hint" className="text-xs text-muted-foreground">{WILD_CARD.hint}: kejadian yang bisa mengubah skenario secara mendadak.</span>
                    </span>
                </div>
            </div>
        </FormDialog>
    );
}
