import { useState } from "react";
import { BotIcon, LoaderCircleIcon, TriangleAlertIcon } from "lucide-react";

import ConfirmDialog from "@/Components/ConfirmDialog";
import { Button } from "@/Components/ui/button";

/**
 * Step 6: write (or rewrite) the four narratives in one call. Rewriting edited or reviewed
 * quadrants asks first; their current text is kept as a revision.
 */
export default function NarrativeGenerateBar({ framework, llm, busy, pending, onGenerate }) {
    const [confirm, setConfirm] = useState(false);
    const quadrants = framework.quadrants;
    const held = quadrants.filter((quadrant) => quadrant.edited || ["reviewed", "approved"].includes(quadrant.review_status)).length;
    const written = quadrants.some((quadrant) => quadrant.narrative);
    const failed = framework.generation?.status === "failed" && !pending;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <Button onClick={() => (held > 0 ? setConfirm(true) : onGenerate(false))} disabled={busy} aria-busy={pending || undefined}>
                    {pending ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <BotIcon aria-hidden="true" />}
                    {written ? "Buat ulang narasi" : "Buat narasi"}
                </Button>
                <span role="status" className="text-xs text-muted-foreground">
                    {pending
                        ? "Narasi sedang ditulis; biasanya 10–150 detik. Halaman ini boleh ditinggalkan."
                        : llm.available
                          ? `Ditulis oleh ${llm.label} (${llm.model}) dari sinyal ACCEPTED yang tertaut ke kedua sumbu dan kondisi.`
                          : "Tanpa layanan AI: tombol ini membuat kerangka tanpa AI untuk dilengkapi tim."}
                </span>
            </div>
            {failed && (
                <p role="alert" className="m-0 flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">
                    <TriangleAlertIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    {framework.generation.error}
                </p>
            )}
            <ConfirmDialog
                open={confirm}
                onOpenChange={setConfirm}
                title="Timpa kuadran yang sudah disunting?"
                description={`${held} kuadran sudah disunting atau ditinjau. Membuat ulang narasi akan menggantinya dan mengembalikan statusnya ke draf. Teks sekarang tetap tersimpan sebagai versi lama.`}
                confirmText="Timpa dan buat ulang"
                onConfirm={() => onGenerate(true)}
            />
        </div>
    );
}
