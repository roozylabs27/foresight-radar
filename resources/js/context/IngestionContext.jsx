import { createContext, useContext, useEffect, useRef, useState, useCallback } from "react";
import { usePage, router } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { CheckCircle2Icon, AlertCircleIcon, BotIcon } from "lucide-react";
import { isPending } from "@/lib/llm";

const IngestionContext = createContext(null);

const STORAGE_KEY = "foresight_ingestion_notifications";
const MAX_NOTIFICATIONS = 30;

function loadStoredNotifications() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

function saveStoredNotifications(items) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, MAX_NOTIFICATIONS)));
    } catch {
        // LocalStorage quota or unavailable
    }
}

export function IngestionProvider({ children }) {
    const page = usePage();
    const initialActive = page?.props?.active_extractions || [];

    const [activeExtractions, setActiveExtractions] = useState(() => initialActive);
    const [notifications, setNotifications] = useState(loadStoredNotifications);
    const [isWidgetMinimized, setIsWidgetMinimized] = useState(false);

    // Keep activeExtractions synchronized when initialActive changes from Inertia navigation
    useEffect(() => {
        if (Array.isArray(page?.props?.active_extractions) && page.props.active_extractions.length > 0) {
            setActiveExtractions((current) => {
                const map = new Map(current.map((item) => [item.id, item]));
                for (const item of page.props.active_extractions) {
                    if (!map.has(item.id)) {
                        map.set(item.id, item);
                    }
                }
                return Array.from(map.values());
            });
        }
    }, [page?.props?.active_extractions]);

    // Persist notifications to localStorage
    useEffect(() => {
        saveStoredNotifications(notifications);
    }, [notifications]);

    const addNotification = useCallback((notif) => {
        setNotifications((prev) => [notif, ...prev.filter((n) => n.id !== notif.id)].slice(0, MAX_NOTIFICATIONS));
    }, []);

    // Polling logic for pending extractions
    const activeRef = useRef(activeExtractions);
    activeRef.current = activeExtractions;

    useEffect(() => {
        const pendingItems = activeExtractions.filter((item) => isPending(item.status));
        if (pendingItems.length === 0) return;

        const controller = new AbortController();
        const pollInterval = setInterval(async () => {
            const currentPending = activeRef.current.filter((item) => isPending(item.status));
            if (currentPending.length === 0) {
                clearInterval(pollInterval);
                return;
            }

            for (const item of currentPending) {
                try {
                    const res = await axios.get(route("signals.extraction", item.id), {
                        signal: controller.signal,
                    });
                    const progress = res.data;

                    if (!isPending(progress.status)) {
                        // Status changed to completed or failed!
                        setActiveExtractions((prev) =>
                            prev.map((e) => (e.id === item.id ? { ...e, ...progress } : e))
                        );

                        const isSuccess = progress.status === "completed";
                        const signalsFound = progress.extraction?.returned ?? 0;

                        // Create notification record
                        const notifRecord = {
                            id: `notif_${Date.now()}_${Math.random().toString(36).substring(2, 6)}`,
                            sourceId: item.id,
                            title: item.title,
                            status: progress.status,
                            message: progress.message || (isSuccess
                                ? `Ekstraksi sinyal untuk "${item.title}" telah selesai.`
                                : `Ekstraksi sinyal "${item.title}" gagal.`),
                            signalsCount: signalsFound,
                            fallback: progress.extraction?.fallback ?? false,
                            fallbackNotice: progress.extraction?.notice ?? null,
                            provider: progress.extraction?.provider,
                            model: progress.extraction?.model,
                            timestamp: Date.now(),
                            read: false,
                        };
                        addNotification(notifRecord);

                        // Fire toast alert
                        if (isSuccess) {
                            toast.success("Ekstraksi Sinyal Selesai", {
                                description: `${item.title}: ${signalsFound} sinyal ditemukan.`,
                                icon: <CheckCircle2Icon className="size-4 text-emerald-500" />,
                                action: {
                                    label: "Lihat Sinyal",
                                    onClick: () => router.visit(route("signals.index")),
                                },
                                duration: 8000,
                            });
                        } else {
                            toast.error("Ekstraksi Sinyal Gagal", {
                                description: progress.error || `Gagal memproses dokumen "${item.title}".`,
                                icon: <AlertCircleIcon className="size-4 text-rose-500" />,
                                duration: 8000,
                            });
                        }

                        // Schedule auto-dismiss from bottom-right active widget after 7 seconds
                        setTimeout(() => {
                            setActiveExtractions((prev) => prev.filter((e) => e.id !== item.id));
                        }, 7000);
                    }
                } catch {
                    // Ignore network polling glitches; will retry next interval
                }
            }
        }, 3500);

        return () => {
            clearInterval(pollInterval);
            controller.abort();
        };
    }, [activeExtractions, addNotification]);

    const startTracking = useCallback((source) => {
        if (!source || !source.id) return;
        setActiveExtractions((prev) => {
            if (prev.some((item) => item.id === source.id)) {
                return prev.map((item) => (item.id === source.id ? { ...item, ...source } : item));
            }
            return [source, ...prev];
        });
        setIsWidgetMinimized(false);
    }, []);

    const dismissExtraction = useCallback((id) => {
        setActiveExtractions((prev) => prev.filter((item) => item.id !== id));
    }, []);

    const markAsRead = useCallback((id) => {
        setNotifications((prev) =>
            prev.map((notif) => (notif.id === id ? { ...notif, read: true } : notif))
        );
    }, []);

    const markAllAsRead = useCallback(() => {
        setNotifications((prev) => prev.map((notif) => ({ ...notif, read: true })));
    }, []);

    const clearAllNotifications = useCallback(() => {
        setNotifications([]);
    }, []);

    const unreadCount = notifications.filter((n) => !n.read).length;

    return (
        <IngestionContext.Provider
            value={{
                activeExtractions,
                notifications,
                unreadCount,
                startTracking,
                dismissExtraction,
                markAsRead,
                markAllAsRead,
                clearAllNotifications,
                isWidgetMinimized,
                setIsWidgetMinimized,
            }}
        >
            {children}
        </IngestionContext.Provider>
    );
}

export function useIngestion() {
    const context = useContext(IngestionContext);
    if (!context) {
        return {
            activeExtractions: [],
            notifications: [],
            unreadCount: 0,
            startTracking: () => {},
            dismissExtraction: () => {},
            markAsRead: () => {},
            markAllAsRead: () => {},
            clearAllNotifications: () => {},
            isWidgetMinimized: false,
            setIsWidgetMinimized: () => {},
        };
    }
    return context;
}

