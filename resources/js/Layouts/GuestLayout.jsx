import { Link } from "@inertiajs/react";

import BrandMark from "@/Components/BrandMark";
import LanguageSwitcher from "@/Components/LanguageSwitcher";
import ThemeToggle from "@/Components/ThemeToggle";
import { I18nProvider } from "@/context/I18nContext";

function GuestLayoutContent({ children }) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-background px-4 py-8 font-sans text-foreground">
            <div className="absolute top-3 right-3 flex items-center gap-1.5">
                <LanguageSwitcher />
                <ThemeToggle />
            </div>
            <main className="flex w-full max-w-[440px] flex-col items-center">
                <Link
                    href="/"
                    className="mb-7 flex flex-col items-center gap-3 rounded-md text-center no-underline outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <BrandMark className="size-11" iconClassName="size-6" />
                    <span className="flex flex-col items-center">
                        <span className="text-[22px] leading-7 font-bold tracking-[-0.01em] text-foreground">
                            Foresight Radar
                        </span>
                        <span className="mt-1 text-[11px] leading-4 font-semibold tracking-[0.08em] text-subtle-foreground uppercase">
                            Strategic Radar Platform
                        </span>
                    </span>
                </Link>

                <div className="w-full rounded-lg border border-solid border-border bg-card px-6 py-8 shadow-sm sm:px-7">
                    {children}
                </div>

                <p className="mt-6 mb-0 text-center text-[13px] text-subtle-foreground">
                    © {new Date().getFullYear()} Foresight Radar. Hak cipta dilindungi.
                </p>
            </main>
        </div>
    );
}

/** Centred card layout for secondary auth pages. */
export default function GuestLayout({ children }) {
    return (
        <I18nProvider>
            <GuestLayoutContent>{children}</GuestLayoutContent>
        </I18nProvider>
    );
}

/** Title and intro for a GuestLayout card. */
export function GuestHeading({ title, children }) {
    return (
        <div className="mb-6 flex flex-col gap-2">
            <h1 className="m-0 text-xl font-bold text-foreground">{title}</h1>
            {children && <p className="m-0 text-sm text-muted-foreground">{children}</p>}
        </div>
    );
}
