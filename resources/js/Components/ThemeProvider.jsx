import { createContext, useContext, useLayoutEffect, useState } from "react";

const STORAGE_KEY = "foresight-theme";

const ThemeContext = createContext({ theme: "light", setTheme: () => {} });

function readStoredTheme(fallback) {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return stored === "dark" || stored === "light" ? stored : fallback;
    } catch {
        return fallback;
    }
}

/**
 * Light by default; the "Mode gelap" switch (ThemeToggle) turns dark on.
 * Toggles the `.dark` class and `color-scheme` on <html> for the design tokens.
 * The inline script in resources/views/partials/theme-script.blade.php applies
 * the saved choice before React mounts, so there is no light flash.
 */
export function ThemeProvider({ children, defaultTheme = "light" }) {
    const [theme, setThemeState] = useState(() => readStoredTheme(defaultTheme));

    useLayoutEffect(() => {
        const root = document.documentElement;
        root.classList.toggle("dark", theme === "dark");
        root.style.colorScheme = theme;
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
