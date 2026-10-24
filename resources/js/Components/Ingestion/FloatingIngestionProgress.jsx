import { Link } from "@inertiajs/react";
import {
    AlertCircleIcon,
    ArrowUpRightIcon,
    BrainCircuitIcon,
    CheckCircle2Icon,
    ChevronDownIcon,
    ChevronUpIcon,
    ClockIcon,
    Loader2Icon,
    XIcon,
} from "lucide-react";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardFooter, CardHeader } from "@/Components/ui/card";
import { useIngestion } from "@/context/IngestionContext";
import { cn } from "@/lib/utils";

import { isPending } from "@/lib/llm";

export default function FloatingIngestionProgress() {
    const {
        activeExtractions,
        dismissExtraction,
        isWidgetMinimized,
        setIsWidgetMinimized,
    } = useIngestion();

    if (!activeExtractions || activeExtractions.length === 0) {
        return null;
    }

    const hasProcessing = activeExtractions.some((e) => isPending(e.status));

    // Minimized Pill view
    if (isWidgetMinimized) {
        return (
            <div className="fixed bottom-4 right-4 z-40 animate-in fade-in slide-in-from-bottom-3 duration-200">
                <button
                    type="button"
                    onClick={() => setIsWidgetMinimized(false)}
                    className="flex items-center gap-2.5 rounded-full border border-primary/30 bg-card/95 px-4 py-2 text-xs font-semibold text-foreground shadow-lg backdrop-blur-md transition-all hover:border-primary hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <div className="flex size-5 items-center justify-center rounded-full bg-primary/10 text-primary">
                        {hasProcessing ? (
                            <Loader2Icon className="size-3 animate-spin" />
                        ) : (
                            <CheckCircle2Icon className="size-3 text-emerald-600" />
                        )}
                    </div>
                    <span>{activeExtractions.length} Proses Ekstraksi</span>
                    <ChevronUpIcon className="size-3.5 text-muted-foreground" />
                </button>
            </div>
        );
    }

    // Expanded Floating Card view
    return (
        <aside
            aria-label="Proses Ekstraksi Sinyal Aktif"
            className="fixed bottom-4 right-4 z-40 w-[92vw] max-w-sm animate-in fade-in slide-in-from-bottom-4 duration-200 sm:w-96"
        >
            <Card className="border-border/80 bg-card/95 shadow-2xl backdrop-blur-md">
                <CardHeader className="flex flex-row items-center justify-between border-b border-border/50 p-3 px-4">
                    <div className="flex items-center gap-2 min-w-0">
                        <div className="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            {hasProcessing ? (
                                <BrainCircuitIcon className="size-3.5 animate-pulse" />
                            ) : (
                                <CheckCircle2Icon className="size-3.5 text-emerald-600" />
                            )}
                        </div>
                        <h3 className="text-xs font-semibold text-foreground truncate">
                            Ekstraksi Sinyal
                        </h3>
                        <Badge variant="secondary" className="px-1.5 py-0 text-[10px] font-mono shrink-0">
                            {activeExtractions.length} aktif
                        </Badge>
                    </div>

                    <div className="flex items-center gap-1 shrink-0">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => setIsWidgetMinimized(true)}
                            aria-label="Kecilkan widget ekstraksi"
                            className="size-6 text-muted-foreground hover:text-foreground"
                        >
                            <ChevronDownIcon className="size-3.5" />
                        </Button>
                    </div>
                </CardHeader>

                <CardContent className="max-h-60 overflow-y-auto p-3 flex flex-col gap-2.5">
                    {activeExtractions.map((item) => {
                        const isCompleted = item.status === "completed";
                        const isFailed = item.status === "failed";
                        const isProcessing = item.status === "processing";

                        return (
                            <div
                                key={item.id}
                                className={cn(
                                    "flex flex-col gap-1.5 rounded-lg border p-2.5 text-xs transition-colors",
                                    isCompleted
                                        ? "border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                                        : isFailed
                                        ? "border-rose-200 bg-rose-50/50 dark:border-rose-900/50 dark:bg-rose-950/20"
                                        : "border-border/60 bg-muted/30"
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-1.5 min-w-0 flex-1">
                                        {isProcessing ? (
                                            <Loader2Icon className="size-3.5 shrink-0 animate-spin text-primary" />
                                        ) : isCompleted ? (
                                            <CheckCircle2Icon className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        ) : isFailed ? (
                                            <AlertCircleIcon className="size-3.5 shrink-0 text-rose-600 dark:text-rose-400" />
                                        ) : (
                                            <ClockIcon className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                                        )}
                                        <span className="font-medium text-foreground truncate" title={item.title}>
                                            {item.title}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => dismissExtraction(item.id)}
                                        aria-label={`Tutup ${item.title}`}
                                        className="shrink-0 text-muted-foreground hover:text-foreground p-0.5"
                                    >
                                        <XIcon className="size-3" />
                                    </button>
                                </div>

                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>
                                        {isProcessing
                                            ? "AI sedang membaca dokumen & mengekstrak sinyal..."
                                            : isCompleted
                                            ? `${item.extraction?.signals ?? item.extraction?.returned ?? item.signalsCount ?? 0} sinyal berhasil ditemukan.`
                                            : isFailed
                                            ? "Ekstraksi gagal."
                                            : "Menunggu antrean pemrosesan..."}
                                    </span>
                                </div>

                                {isProcessing && (
                                    <div className="h-1 w-full overflow-hidden rounded-full bg-primary/20">
                                        <div className="h-full w-2/3 animate-pulse rounded-full bg-primary" />
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </CardContent>

                <CardFooter className="flex items-center justify-between border-t border-border/50 p-2.5 px-4 text-[11px] text-muted-foreground bg-muted/20">
                    <span className="truncate pr-2">Boleh tinggalkan halaman, proses tetap berjalan.</span>
                    <Link
                        href={route("signals.index")}
                        className="inline-flex shrink-0 items-center gap-1 font-semibold text-primary hover:underline"
                    >
                        <span>Lihat Sinyal</span>
                        <ArrowUpRightIcon className="size-3" />
                    </Link>
                </CardFooter>
            </Card>
        </aside>
    );
}
