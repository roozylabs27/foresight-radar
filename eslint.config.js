import js from "@eslint/js";
import jsxA11y from "eslint-plugin-jsx-a11y";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";

/**
 * Audit 2026-11-11 AUD-18: lint for the JSX frontend (resources/js). `npm run lint` must report no
 * errors; warnings are known debt in older code, fixed when a file is touched.
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
            ...Object.fromEntries(Object.keys(jsxA11y.flatConfigs.recommended.rules).map((rule) => [rule, "warn"])),
            // Deprecated in favour of label-has-associated-control (above).
            "jsx-a11y/label-has-for": "off",
            "no-unused-vars": ["warn", { argsIgnorePattern: "^_", varsIgnorePattern: "^_", ignoreRestSiblings: true }],
        },
    },
];
