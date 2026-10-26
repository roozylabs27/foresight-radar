import { Link } from "@inertiajs/react";

import AuthCarousel from "@/Components/auth/AuthCarousel";
import BrandMark from "@/Components/BrandMark";
import LanguageSwitcher from "@/Components/LanguageSwitcher";
import ThemeToggle from "@/Components/ThemeToggle";
import { I18nProvider } from "@/context/I18nContext";

const COPYRIGHT = "© 2026 Foresight Radar. Hak cipta dilindungi.";

const HORIZON_PILLS = [
    { code: "H1", label: "Short Term", dotClass: "bg-primary dark:bg-primary" },
    { code: "H2", label: "Mid Term", dotClass: "bg-scan dark:bg-scan" },
    { code: "H3", label: "Long Term", dotClass: "bg-amber-600 dark:bg-amber-400" },
];

/**
 * Architectural polar horizon-scanning radar SVG backdrop.
 * Adapts stroke/fill opacities between light mode (cobalt/teal on soft horizon blue)
 * and dark mode (cyan/sky on deep night navy).
 */
function HorizonRadarBackdrop() {
    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden select-none">
            {/* Soft radial illumination */}
            <div className="absolute -top-28 -left-24 size-96 rounded-full bg-primary/12 blur-3xl dark:bg-primary/15" />
            <div className="absolute -right-24 -bottom-24 size-[440px] rounded-full bg-scan/12 blur-3xl dark:bg-scan/15" />

            {/* Subtle technical coordinate grid with radial fade mask */}
            <div
                className="absolute inset-0 opacity-45 dark:opacity-25"
                style={{
                    backgroundImage:
                        "linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px)",
                    backgroundSize: "40px 40px",
                    maskImage: "radial-gradient(ellipse 85% 80% at 70% 65%, #000 20%, transparent 80%)",
                    WebkitMaskImage: "radial-gradient(ellipse 85% 80% at 70% 65%, #000 20%, transparent 80%)",
                }}
            />

            {/* Polar Radar Geometry */}
            <svg
                viewBox="0 0 600 600"
                fill="none"
                className="absolute -right-28 -bottom-28 size-[420px] text-primary/25 sm:size-[480px] lg:-right-24 lg:-bottom-24 lg:size-[560px] dark:text-scan/25"
            >
                <defs>
                    <radialGradient id="auth-radar-core" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stopColor="var(--scan)" stopOpacity="0.22" />
                        <stop offset="55%" stopColor="var(--primary)" stopOpacity="0.08" />
                        <stop offset="100%" stopColor="var(--primary)" stopOpacity="0" />
                    </radialGradient>
                    <linearGradient id="auth-radar-sweep" x1="300" y1="300" x2="495" y2="105" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stopColor="var(--scan)" stopOpacity="0.28" />
                        <stop offset="100%" stopColor="var(--primary)" stopOpacity="0.02" />
                    </linearGradient>
                </defs>

                {/* Ambient core glow */}
                <circle cx="300" cy="300" r="260" fill="url(#auth-radar-core)" />

                {/* Radar 45-degree sweep sector */}
                <path
                    d="M300 300 L484 116 A260 260 0 0 1 560 300 Z"
                    fill="url(#auth-radar-sweep)"
                />

                {/* Outer bezel ring & Horizon 3 / 2 / 1 concentric rings */}
                <circle cx="300" cy="300" r="260" stroke="currentColor" strokeWidth="1.25" strokeDasharray="4 6" />
                <circle cx="300" cy="300" r="205" stroke="currentColor" strokeWidth="1.2" />
                <circle cx="300" cy="300" r="145" stroke="currentColor" strokeWidth="1.2" strokeDasharray="8 6" />
                <circle cx="300" cy="300" r="85" stroke="currentColor" strokeWidth="1.5" />
                <circle cx="300" cy="300" r="32" stroke="currentColor" strokeWidth="1.2" />

                {/* Crosshair & diagonal polar axes */}
                <line x1="40" y1="300" x2="560" y2="300" stroke="currentColor" strokeWidth="1" />
                <line x1="300" y1="40" x2="300" y2="560" stroke="currentColor" strokeWidth="1" />
                <line x1="116" y1="116" x2="484" y2="484" stroke="currentColor" strokeWidth="0.9" strokeDasharray="3 5" />
                <line x1="484" y1="116" x2="116" y2="484" stroke="currentColor" strokeWidth="0.9" strokeDasharray="3 5" />

                {/* Active radar sweep vector line */}
                <line x1="300" y1="300" x2="484" y2="116" stroke="var(--scan)" strokeWidth="2" strokeLinecap="round" />

                {/* Center origin node */}
                <circle cx="300" cy="300" r="5" fill="var(--scan)" />
                <circle cx="300" cy="300" r="11" stroke="var(--scan)" strokeWidth="1.2" strokeOpacity="0.6" />

                {/* Plotted foresight signal nodes across H1, H2, H3 */}
                {/* H1 Signal */}
                <g transform="translate(362 242)">
                    <circle r="10" fill="var(--scan)" fillOpacity="0.18" />
                    <circle r="4.5" fill="var(--scan)" />
                </g>
                {/* H2 Signal (along sweep) */}
                <g transform="translate(418 182)">
                    <circle r="13" stroke="var(--scan)" strokeWidth="1.25" strokeOpacity="0.55" />
                    <circle r="5" fill="var(--scan)" />
                </g>
                {/* H2 Signal (lower left quadrant) */}
                <g transform="translate(195 235)">
                    <circle r="9" fill="var(--primary)" fillOpacity="0.2" />
                    <circle r="4" fill="var(--primary)" />
                </g>
                {/* H3 Weak Signal (outer ring) */}
                <g transform="translate(235 112)">
                    <circle r="11" stroke="var(--primary)" strokeWidth="1.2" strokeDasharray="2 2" />
                    <circle r="4" fill="var(--primary)" />
                </g>
                {/* H3 Emerging Signal */}
                <g transform="translate(462 268)">
                    <circle r="8" fill="var(--scan)" fillOpacity="0.2" />
                    <circle r="3.5" fill="var(--scan)" />
                </g>
            </svg>
        </div>
    );
}

function AuthSplitContent({ slides, children }) {
    return (
        <div className="grid min-h-svh grid-rows-[auto_1fr] bg-background font-sans text-foreground lg:grid-cols-2 lg:grid-rows-none">
            <aside
                aria-label="Foresight Radar"
                className="relative flex flex-col justify-between gap-10 overflow-hidden border-b border-border bg-gradient-to-br from-[#eef4fc] via-[#e5effb] to-[#d8e6f8] px-6 py-5 text-foreground sm:px-10 lg:border-r lg:border-b-0 lg:px-12 lg:py-14 dark:bg-night dark:from-[#071326] dark:via-[#050b16] dark:to-[#0a1b34] dark:text-on-night"
            >
                <HorizonRadarBackdrop />

                <Link
                    href="/"
                    className="relative z-10 flex w-fit items-center gap-3 rounded-md text-foreground no-underline outline-none focus-visible:ring-[3px] focus-visible:ring-scan/60 dark:text-on-night"
                >
                    <BrandMark
                        className="size-11 bg-night text-scan shadow-xs ring-1 ring-primary/15 dark:bg-on-night/10 dark:ring-white/15"
                        iconClassName="size-6"
                    />
                    <span className="flex flex-col">
                        <span className="text-xl leading-tight font-bold">Foresight Radar</span>
                        <span className="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase dark:text-on-night/70">
                            Strategic Radar Platform
                        </span>
                    </span>
                </Link>

                <div className="relative z-10 hidden lg:flex lg:flex-col lg:gap-6">
                    <div className="flex flex-wrap items-center gap-2" aria-hidden="true">
                        {HORIZON_PILLS.map((horizon) => (
                            <span
                                key={horizon.code}
                                className="inline-flex items-center gap-1.5 rounded-full border border-primary/15 bg-card/75 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-foreground/85 shadow-2xs backdrop-blur-xs dark:border-white/10 dark:bg-white/6 dark:text-on-night/85"
                            >
                                <span className={`size-1.5 rounded-full ${horizon.dotClass}`} />
                                <span className="font-mono font-bold text-primary dark:text-scan">{horizon.code}</span>
                                <span className="text-muted-foreground dark:text-on-night/70">{horizon.label}</span>
                            </span>
                        ))}
                    </div>

                    <AuthCarousel slides={slides} />
                </div>

                <p className="relative z-10 m-0 hidden text-[13px] text-subtle-foreground lg:block dark:text-on-night/60">
                    {COPYRIGHT}
                </p>
            </aside>

            <main className="relative flex items-center justify-center px-6 py-12 sm:px-10">
                <div className="absolute top-3 right-3 flex items-center gap-1.5">
                    <LanguageSwitcher />
                    <ThemeToggle />
                </div>
                <div className="w-full max-w-[400px]">
                    {children}
                    <p className="mt-10 mb-0 text-center text-[13px] text-subtle-foreground lg:hidden">{COPYRIGHT}</p>
                </div>
            </main>
        </div>
    );
}

export default function AuthSplitLayout(props) {
    return (
        <I18nProvider>
            <AuthSplitContent {...props} />
        </I18nProvider>
    );
}
