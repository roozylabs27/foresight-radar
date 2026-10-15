import { Link } from "@inertiajs/react";

import BrandMark from "@/Components/BrandMark";

/** Centred card layout for the secondary auth pages (forgot / reset / confirm password, verify email). */
export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background px-4 py-8 font-sans text-foreground">
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

                <div className="w-full rounded-lg border border-solid border-border bg-card px-6 py-8 shadow-[0_1px_2px_0_rgb(11_27_51/0.05),0_1px_3px_0_rgb(11_27_51/0.04)] sm:px-7">
                    {children}
                </div>

                <p className="mt-6 mb-0 text-center text-[13px] text-subtle-foreground">
                    © {new Date().getFullYear()} Foresight Radar. Hak cipta dilindungi.
                </p>
            </main>
        </div>
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
