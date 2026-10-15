/**
 * antd theme mapped to the Foresight Radar light tokens in resources/css/app.css.
 * antd cannot read CSS variables at runtime, so the hex values are duplicated
 * here until the remaining antd pages are migrated.
 */
export const antdTheme = {
    token: {
        colorPrimary: "#1a5fd6",
        colorLink: "#1a5fd6",
        colorInfo: "#1a5fd6",
        colorSuccess: "#1f7a3a",
        colorWarning: "#a65300",
        colorError: "#c42b2b",
        colorText: "#0b1b33",
        colorTextSecondary: "#4a5872",
        colorTextTertiary: "#5f6d84",
        colorBorder: "#7b889c",
        colorBorderSecondary: "#e1e7ef",
        colorBgLayout: "#f5f7fb",
        colorBgContainer: "#ffffff",
        borderRadius: 8,
        borderRadiusSM: 4,
        borderRadiusLG: 12,
        fontFamily:
            "Figtree, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
        fontFamilyCode: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace",
    },
};
