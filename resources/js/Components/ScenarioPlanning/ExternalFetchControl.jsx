import { useEffect } from "react";
import { router } from "@inertiajs/react";
import { AlertCircleIcon, LoaderCircleIcon, RefreshCwIcon } from "lucide-react";

import { Button } from "@/Components/ui/button";
import { EXTERNAL_SOURCES } from "@/domain/scenarioPlanning";

const PENDING = ["queued", "processing"];

/**
 * Roadmap R3.6: where an external indicator gets its values, when they were last fetched, and a
 * button to fetch now. While the fetch runs the page reloads the indicators every few seconds.
 */
export default function ExternalFetchControl({ indicator, canManage, busy, onFetch }) {
    const pending = PENDING.includes(indicator.fetch_status);
    const source = EXTERNAL_SOURCES[indicator.external_source];

    useEffect(() => {
        if (!pending) return undefined;
        const timer = setInterval(() => router.reload({ only: ["indicators"] }), 5000);
        return () => clearInterval(timer);
    }, [pending]);

    if (!source) {
        return <p className="m-0 text-xs text-muted-foreground">Sumber eksternal belum diatur. Ubah indikator untuk memilih penyedia data.</p>;
    }

    return (
        <div className="flex flex-col gap-2 rounded-md bg-muted/40 p-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs text-muted-foreground">
                    {source.label} · seri {indicator.external_series} · wilayah {indicator.external_area}
                    {" · "}
                    {indicator.last_fetched_at ? `terakhir diambil ${new Date(indicator.last_fetched_at).toLocaleString("id-ID")}` : "belum pernah diambil"}
                </span>
                {canManage && indicator.status === "active" && (
                    <Button size="sm" variant="outline" disabled={pending || busy} onClick={onFetch}>
                        {pending ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <RefreshCwIcon aria-hidden="true" />}
                        {pending ? "Mengambil data…" : "Ambil data"}
                    </Button>
                )}
            </div>
            {indicator.fetch_status === "failed" && indicator.fetch_error && (
                <p role="alert" className="m-0 flex items-start gap-1.5 rounded-md bg-danger-soft p-2 text-xs text-danger">
                    <AlertCircleIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    {indicator.fetch_error}
                </p>
            )}
        </div>
    );
}
