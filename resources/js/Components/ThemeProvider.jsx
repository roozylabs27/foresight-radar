import { createContext, useContext, useEffect, useState } from "react";

const STORAGE_KEY = "foresight-theme";
const ThemeContext = createContext({ theme: "light", setTheme: () => {} });

function readStoredTheme(fallback) {
    try {
        return window.localStorage.getItem(STORAGE_KEY) || fallback;
    } catch {
        return fallback;
    }
}

function resolveTheme(theme) {
    if (theme !== "system") return theme;
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

/**
 * Toggles the `.dark` class on <html> for the shadcn/Tailwind tokens.
 * Defaults to light until the antd pages are migrated, since antd is not dark-aware.
 */
export function ThemeProvider({ children, defaultTheme = "light" }) {
    const [theme, setThemeState] = useState(() => readStoredTheme(defaultTheme));

    useEffect(() => {
        const apply = () => {
            document.documentElement.classList.toggle("dark", resolveTheme(theme) === "dark");
        };
        apply();

        if (theme !== "system") return undefined;
        const media = window.matchMedia("(prefers-color-scheme: dark)");
        media.addEventListener("change", apply);
        return () => media.removeEventListener("change", apply);
    }, [theme]);

    const setTheme = (next) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Storage can be unavailable (private mode); the theme still applies for this session.
        }
        setThemeState(next);
    };

    return <ThemeContext.Provider value={{ theme, setTheme }}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    return useContext(ThemeContext);
}
