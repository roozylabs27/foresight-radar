import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import {
    AlertCircleIcon,
    BellIcon,
    BellOffIcon,
    CheckCheckIcon,
    CheckCircle2Icon,
    ExternalLinkIcon,
    Trash2Icon,
} from "lucide-react";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import { useIngestion } from "@/context/IngestionContext";
import { cn } from "@/lib/utils";

function formatRelativeTime(timestamp) {
    if (!timestamp) return "";
    const diff = Math.floor((Date.now() - timestamp) / 1000);
    if (diff < 60) return "Baru saja";
    if (diff < 3600) return `${Math.floor(diff / 60)} mnt lalu`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
    return `${Math.floor(diff / 86400)} hari lalu`;
}

export default function NotificationBell({ className }) {
    const {
        notifications,
        unreadCount,
        markAsRead,
        markAllAsRead,
        clearAllNotifications,
    } = useIngestion();

    const [open, setOpen] = useState(false);

    const handleNotificationClick = (notif) => {
        markAsRead(notif.id);
        setOpen(false);
        router.visit(route("signals.index"));
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Notifikasi (${unreadCount} belum dibaca)`}
                    className={cn(
                        "relative size-9 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring",
                        className
                    )}
                >
                    <BellIcon aria-hidden="true" className="size-4.5" />
                    {unreadCount > 0 && (
                        <span className="absolute top-1.5 right-1.5 flex size-4 items-center justify-center rounded-full bg-rose-600 text-[10px] font-bold text-white shadow-xs animate-in zoom-in-50">
                            {unreadCount > 9 ? "9+" : unreadCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align="end"
                className="w-80 sm:w-96 p-0 border-border/80 bg-card shadow-xl"
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-border/60 p-3 px-4">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-foreground">Notifikasi</span>
                        {unreadCount > 0 && (
                            <Badge variant="secondary" className="px-1.5 py-0 text-[10px]">
                                {unreadCount} baru
                            </Badge>
                        )}
                    </div>
                    {unreadCount > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={markAllAsRead}
                            className="h-7 gap-1 px-2 text-[11px] text-muted-foreground hover:text-primary"
                        >
                            <CheckCheckIcon className="size-3.5" />
                            <span>Tandai dibaca</span>
                        </Button>
                    )}
                </div>

                {/* Notification List */}
                <div className="max-h-80 overflow-y-auto divide-y divide-border/40">
                    {notifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted/60 mb-2">
                                <BellOffIcon className="size-5 text-muted-foreground/60" />
                            </div>
                            <p className="text-xs font-medium">Belum ada notifikasi baru</p>
                            <p className="text-[11px] text-muted-foreground/80 mt-0.5">
                                Notifikasi hasil ekstraksi sinyal akan muncul di sini.
                            </p>
                        </div>
                    ) : (
                        notifications.map((notif) => {
                            const isSuccess = notif.status === "completed";
                            return (
                                <button
                                    key={notif.id}
                                    type="button"
                                    onClick={() => handleNotificationClick(notif)}
                                    className={cn(
                                        "flex w-full items-start gap-3 p-3.5 text-left transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:bg-muted/50",
                                        !notif.read && "bg-primary/5"
                                    )}
                                >
                                    <div
                                        className={cn(
                                            "mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full",
                                            isSuccess
                                                ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400"
                                                : "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400"
                                        )}
                                    >
                                        {isSuccess ? (
                                            <CheckCircle2Icon className="size-4" />
                                        ) : (
                                            <AlertCircleIcon className="size-4" />
                                        )}
                                    </div>

                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs font-semibold text-foreground truncate">
                                                {isSuccess ? "Ekstraksi Selesai" : "Ekstraksi Gagal"}
                                            </span>
                                            <span className="text-[10px] text-muted-foreground shrink-0">
                                                {formatRelativeTime(notif.timestamp)}
                                            </span>
                                        </div>

                                        <p className="text-xs text-foreground/90 font-medium truncate mt-0.5" title={notif.title}>
                                            {notif.title}
                                        </p>

                                        <p className="text-[11px] text-muted-foreground line-clamp-2 mt-0.5">
                                            {isSuccess
                                                ? `${notif.signalsCount} kandidat sinyal ditemukan dan siap direview.`
                                                : notif.message}
                                        </p>

                                        {notif.fallback && (
                                            <span className="inline-block mt-1 text-[10px] text-amber-600 dark:text-amber-400 font-medium">
                                                * Diekstrak menggunakan aturan kata kunci (fallback)
                                            </span>
                                        )}
                                    </div>

                                    {!notif.read && (
                                        <span className="size-2 shrink-0 rounded-full bg-primary mt-1.5" />
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>

                {/* Footer */}
                {notifications.length > 0 && (
                    <div className="flex items-center justify-between border-t border-border/60 p-2.5 px-4 bg-muted/20 text-xs">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clearAllNotifications}
                            className="h-7 gap-1 px-2 text-[11px] text-muted-foreground hover:text-destructive"
                        >
                            <Trash2Icon className="size-3" />
                            <span>Bersihkan riwayat</span>
                        </Button>
                        <Link
                            href={route("signals.index")}
                            onClick={() => setOpen(false)}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline"
                        >
                            <span>Buka Signal Review</span>
                            <ExternalLinkIcon className="size-3" />
                        </Link>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}

