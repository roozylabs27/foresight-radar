import { Link } from "@inertiajs/react";

import AuthCarousel from "@/Components/auth/AuthCarousel";
import BrandMark from "@/Components/BrandMark";

const COPYRIGHT = "© 2026 Foresight Radar. Hak cipta dilindungi.";

/**
 * Login / register screen: brand panel with rotating highlights beside the form.
 * Below lg the panel shrinks to a brand bar so the form stays above the fold.
 */
export default function AuthSplitLayout({ slides, children }) {
    return (
        <div className="grid min-h-svh grid-rows-[auto_1fr] bg-background font-sans text-foreground lg:grid-cols-2 lg:grid-rows-none">
            <aside
                aria-label="Foresight Radar"
                className="flex flex-col justify-between gap-10 bg-night px-6 py-5 text-on-night sm:px-10 lg:px-12 lg:py-14"
            >
                <Link
                    href="/"
                    className="flex w-fit items-center gap-3 rounded-md text-on-night no-underline outline-none focus-visible:ring-[3px] focus-visible:ring-scan/60"
                >
                    <BrandMark className="size-11 bg-on-night/10" iconClassName="size-6" />
                    <span className="flex flex-col">
                        <span className="text-xl leading-tight font-bold">Foresight Radar</span>
                        <span className="text-xs font-semibold tracking-[0.08em] text-on-night/70 uppercase">
                            Strategic Radar Platform
                        </span>
                    </span>
                </Link>

                <div className="hidden lg:block">
                    <AuthCarousel slides={slides} />
                </div>

                <p className="m-0 hidden text-[13px] text-on-night/60 lg:block">{COPYRIGHT}</p>
            </aside>

            <main className="flex items-center justify-center px-6 py-12 sm:px-10">
                <div className="w-full max-w-[400px]">
                    {children}
                    <p className="mt-10 mb-0 text-center text-[13px] text-subtle-foreground lg:hidden">{COPYRIGHT}</p>
                </div>
            </main>
        </div>
    );
}
