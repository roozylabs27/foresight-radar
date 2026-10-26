import { createContext, useCallback, useContext, useMemo, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import idMessages from "@/locales/id.json";
import enMessages from "@/locales/en.json";

const DICTIONARIES = {
    id: idMessages,
    en: enMessages,
};

const DEFAULT_SUPPORTED_LOCALES = {
    id: { code: "id", name: "Bahasa Indonesia", short: "ID" },
    en: { code: "en", name: "English", short: "EN" },
};

const I18nContext = createContext(null);

function resolveNestedKey(obj, path) {
    if (!obj || typeof obj !== "object") return undefined;
    const parts = path.split(".");
    let current = obj;
    for (const part of parts) {
        if (current == null || typeof current !== "object") {
            return undefined;
        }
        current = current[part];
    }
    return current;
}

function interpolate(template, params) {
    if (typeof template !== "string" || !params || typeof params !== "object") {
        return template;
    }
    return template.replace(/(?::|\{)([a-zA-Z0-9_]+)\}?/g, (match, key) => {
        return Object.prototype.hasOwnProperty.call(params, key) ? params[key] : match;
    });
}

/**
 * Lightweight, accessible I18nProvider for React 19 + Inertia.js 2.0.
 */
export function I18nProvider({ children }) {
    const parentContext = useContext(I18nContext);
    if (parentContext) {
        return children;
    }

    const page = usePage();
    const [isChanging, setIsChanging] = useState(false);

    const activeLocale = (page?.props?.locale && DICTIONARIES[page.props.locale])
        ? page.props.locale
        : "id";

    const supportedLocales = page?.props?.supported_locales || DEFAULT_SUPPORTED_LOCALES;

    const t = useCallback(
        (key, params = {}) => {
            const currentDict = DICTIONARIES[activeLocale] || DICTIONARIES.id;
            let value = resolveNestedKey(currentDict, key);

            // Fallback to Indonesian if key missing in current locale
            if (value === undefined && activeLocale !== "id") {
                value = resolveNestedKey(DICTIONARIES.id, key);
            }

            // Fallback to English if still missing
            if (value === undefined && activeLocale !== "en") {
                value = resolveNestedKey(DICTIONARIES.en, key);
            }

            if (value === undefined) {
                return typeof params === "string" ? params : key;
            }

            return interpolate(value, params);
        },
        [activeLocale]
    );

    const changeLanguage = useCallback(
        (targetLocale) => {
            if (!DICTIONARIES[targetLocale] || targetLocale === activeLocale) {
                return;
            }

            try {
                localStorage.setItem("app_locale", targetLocale);
                document.cookie = `app_locale=${targetLocale}; path=/; max-age=31536000; SameSite=Lax`;
            } catch (_) {
                // Ignore storage access restrictions
            }

            setIsChanging(true);
            router.post(
                route("locale.update"),
                { locale: targetLocale },
                {
                    preserveScroll: true,
                    preserveState: false,
                    onFinish: () => setIsChanging(false),
                }
            );
        },
        [activeLocale]
    );

    const contextValue = useMemo(
        () => ({
            locale: activeLocale,
            supportedLocales,
            t,
            changeLanguage,
            isChanging,
        }),
        [activeLocale, supportedLocales, t, changeLanguage, isChanging]
    );

    return <I18nContext.Provider value={contextValue}>{children}</I18nContext.Provider>;
}

/**
 * Hook to consume current translation dictionary and language switching action.
 * Returns safe fallback if invoked outside an I18nProvider to prevent uncaught runtime errors.
 */
export function useTranslation() {
    const context = useContext(I18nContext);
    if (!context) {
        return {
            locale: "id",
            supportedLocales: DEFAULT_SUPPORTED_LOCALES,
            t: (key) => key,
            changeLanguage: () => {},
            isChanging: false,
        };
    }
    return context;
}
