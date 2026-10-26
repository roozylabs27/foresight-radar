import { createContext, useContext, useEffect, useRef, useState, useCallback } from "react";
import { usePage, router } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { CheckCircle2Icon, AlertCircleIcon } from "lucide-react";
import { isPending } from "@/lib/llm";

const IngestionContext = createContext(null);

const NOTIF_STORAGE_KEY = "foresight_ingestion_notifications";
const ACTIVE_STORAGE_KEY = "foresight_active_extractions";
const MAX_NOTIFICATIONS = 30;

function loadStoredNotifications() {
    try {
        const stored = localStorage.getItem(NOTIF_STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

function saveStoredNotifications(items) {
    try {
        localStorage.setItem(NOTIF_STORAGE_KEY, JSON.stringify(items.slice(0, MAX_NOTIFICATIONS)));
    } catch {
        // LocalStorage quota or unavailable
    }
}

function loadStoredActiveExtractions() {
    try {
        const stored = localStorage.getItem(ACTIVE_STORAGE_KEY);
        if (!stored) return [];
        const items = JSON.parse(stored);
        if (!Array.isArray(items)) return [];
        // Keep pending extractions indefinitely; keep recent finished items for 10 minutes
        const cutoff = Date.now() - 10 * 60 * 1000;
        return items.filter((item) => {
            if (!item || !item.id) return false;
            if (isPending(item.status)) return true;
            return item.updatedAt && item.updatedAt >= cutoff;
        });
    } catch {
        return [];
    }
}

function saveStoredActiveExtractions(items) {
    try {
        if (!Array.isArray(items)) return;
        localStorage.setItem(ACTIVE_STORAGE_KEY, JSON.stringify(items));
    } catch {
        // LocalStorage quota or unavailable
    }
}

function getInitialActiveExtractions(pageProps) {
    const fromStorage = loadStoredActiveExtractions();
    const fromProps = Array.isArray(pageProps?.active_extractions) ? pageProps.active_extractions : [];
    const fromPending = Array.isArray(pageProps?.pendingExtractions) ? pageProps.pendingExtractions : [];

    const map = new Map();
    // 1. Initial state from persistent storage so it renders immediately on page refresh
    for (const item of fromStorage) {
        if (item && item.id) {
            map.set(item.id, item);
        }
    }
    // 2. Hydrate / overwrite with fresher server props if present
    for (const item of [...fromProps, ...fromPending]) {
        if (item && item.id) {
            const existing = map.get(item.id);
            map.set(item.id, existing ? { ...existing, ...item } : item);
        }
    }
    return Array.from(map.values());
}

export function IngestionProvider({ children }) {
    const page = usePage();

    const [activeExtractions, setActiveExtractions] = useState(() =>
        getInitialActiveExtractions(page?.props)
    );
    const [notifications, setNotifications] = useState(loadStoredNotifications);
    const [isWidgetMinimized, setIsWidgetMinimized] = useState(false);

    // Keep activeExtractions synchronized when page props update on Inertia visits
    useEffect(() => {
        const fromProps = Array.isArray(page?.props?.active_extractions) ? page.props.active_extractions : [];
        const fromPending = Array.isArray(page?.props?.pendingExtractions) ? page.props.pendingExtractions : [];
        const combined = [...fromProps, ...fromPending];

        if (combined.length === 0) return;

        setActiveExtractions((current) => {
            const map = new Map(current.map((item) => [item.id, item]));
            let hasChanged = false;
            for (const item of combined) {
                if (!item || !item.id) continue;
                const existing = map.get(item.id);
                if (!existing) {
                    map.set(item.id, item);
                    hasChanged = true;
                } else if (existing.status !== item.status) {
                    map.set(item.id, { ...existing, ...item });
                    hasChanged = true;
                }
            }
            return hasChanged ? Array.from(map.values()) : current;
        });
    }, [page?.props?.active_extractions, page?.props?.pendingExtractions]);

    // Persist active extractions to localStorage whenever they change
    useEffect(() => {
        saveStoredActiveExtractions(activeExtractions);
    }, [activeExtractions]);

    // Persist notifications to localStorage
    useEffect(() => {
        saveStoredNotifications(notifications);
    }, [notifications]);

    // Re-verify with backend active extractions endpoint on mount & on window focus
    useEffect(() => {
        let isMounted = true;
        const syncActiveWithServer = async () => {
            try {
                const res = await axios.get(route("signals.active-extractions"));
                if (!isMounted || !Array.isArray(res.data)) return;

                setActiveExtractions((current) => {
                    const map = new Map(current.map((item) => [item.id, item]));
                    let hasChanged = false;
                    for (const item of res.data) {
                        if (!item || !item.id) continue;
                        const existing = map.get(item.id);
                        if (!existing) {
                            map.set(item.id, item);
                            hasChanged = true;
                        } else if (existing.status !== item.status) {
                            map.set(item.id, { ...existing, ...item });
                            hasChanged = true;
                        }
                    }
                    return hasChanged ? Array.from(map.values()) : current;
                });
            } catch {
                // Ignore transient network failure; background poller will retry
            }
        };

        syncActiveWithServer();

        const handleFocus = () => syncActiveWithServer();
        window.addEventListener("focus", handleFocus);
        return () => {
            isMounted = false;
            window.removeEventListener("focus", handleFocus);
        };
    }, []);

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
                            prev.map((e) =>
                                e.id === item.id
                                    ? { ...e, ...progress, updatedAt: Date.now() }
                                    : e
                            )
                        );

                        const isSuccess = progress.status === "completed";
                        const signalsFound = progress.extraction?.signals ?? progress.extraction?.returned ?? 0;

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
                            provider: progress.extraction?.label ?? progress.extraction?.provider,
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

                        // Auto-dismiss completed card from widget after 12 seconds
                        setTimeout(() => {
                            setActiveExtractions((prev) => prev.filter((e) => e.id !== item.id));
                        }, 12000);
                    }
                } catch {
                    // Ignore network polling glitches; will retry next interval
                }
            }
        }, 3000);

        return () => {
            clearInterval(pollInterval);
            controller.abort();
        };
    }, [activeExtractions, addNotification]);

    const startTracking = useCallback((source) => {
        if (!source || !source.id) return;
        setActiveExtractions((prev) => {
            const updatedItem = { ...source, updatedAt: Date.now() };
            if (prev.some((item) => item.id === source.id)) {
                return prev.map((item) => (item.id === source.id ? { ...item, ...updatedItem } : item));
            }
            return [updatedItem, ...prev];
        });
        setIsWidgetMinimized(false);
    }, []);

    const dismissExtraction = useCallback((id) => {
        setActiveExtractions((prev) => {
            const next = prev.filter((item) => item.id !== id);
            saveStoredActiveExtractions(next);
            return next;
        });
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
