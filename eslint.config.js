import js from "@eslint/js";
import jsxA11y from "eslint-plugin-jsx-a11y";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";

/**
 * Audit 2026-11-11 AUD-18: lint for the JSX frontend (resources/js). `npm run lint` must report no
 * errors. Since 2026-11-13 it also reports no warnings: keep it that way (a dialog that resets only when
 * another item opens uses useEffectEvent, not a shortened dependency list).
 *
 * - Errors: JavaScript mistakes (undefined names, unreachable code), the rules of hooks, and React
 *   mistakes that break rendering (missing keys, duplicate props).
 * - Warnings: hook dependency lists and accessibility findings (jsx-a11y), until the older pages
 *   are cleaned up.
 */
export default [
    { ignores: ["public/**", "vendor/**", "node_modules/**", "storage/**", "bootstrap/**", "resources/js/ziggy.js"] },
    js.configs.recommended,
    {
        files: ["resources/js/**/*.{js,jsx}"],
        languageOptions: {
            ecmaVersion: "latest",
            sourceType: "module",
            parserOptions: { ecmaFeatures: { jsx: true } },
            globals: { ...globals.browser, route: "readonly" },
        },
        settings: { react: { version: "19.0" } },
        plugins: { react, "react-hooks": reactHooks, "jsx-a11y": jsxA11y },
        rules: {
            ...react.configs.flat.recommended.rules,
            ...react.configs.flat["jsx-runtime"].rules,
            // The codebase is JavaScript without PropTypes.
            "react/prop-types": "off",
            "react/no-unescaped-entities": "off",
            "react-hooks/rules-of-hooks": "error",
            "react-hooks/exhaustive-deps": "warn",
            // Every recommended jsx-a11y rule as a warning, keeping the plugin's recommended options (for
            // example control-has-associated-label ignores inputs, which their <label> names). Mapping a rule
            // to a bare "warn" dropped those options and reported correctly labelled checkboxes.
            ...Object.fromEntries(
                Object.entries(jsxA11y.flatConfigs.recommended.rules).map(([rule, setting]) => [rule, Array.isArray(setting) ? ["warn", ...setting.slice(1)] : "warn"]),
            ),
            // A checkbox row's text often sits a few elements deep inside its <label>; Textarea and Input are
            // the design system's form controls.
            "jsx-a11y/label-has-associated-control": ["warn", { depth: 4, controlComponents: ["Input", "Textarea"] }],
            // Recommended options, plus depth for header cells whose text sits inside a tooltip trigger.
            "jsx-a11y/control-has-associated-label": ["warn", { ...jsxA11y.flatConfigs.recommended.rules["jsx-a11y/control-has-associated-label"][1], depth: 4 }],
            // Deprecated in favour of label-has-associated-control (above).
            "jsx-a11y/label-has-for": "off",
            "no-unused-vars": ["warn", { argsIgnorePattern: "^_", varsIgnorePattern: "^_", ignoreRestSiblings: true }],
        },
    },
];
