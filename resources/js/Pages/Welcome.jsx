import { Head, Link } from "@inertiajs/react";
import {
    ArrowRightIcon,
    CalendarClockIcon,
    ChartScatterIcon,
    CompassIcon,
    GaugeIcon,
    LogInIcon,
    RadarIcon,
    ShieldCheckIcon,
    UserPlusIcon,
} from "lucide-react";

import BrandMark from "@/Components/BrandMark";
import ThemeToggle from "@/Components/ThemeToggle";
import { Button } from "@/Components/ui/button";

const FEATURES = [
    {
        icon: CalendarClockIcon,
        color: "var(--chart-1)",
        title: "3 Time Horizons",
        text: "Klasifikasi sinyal masa depan dalam Horizon 1 (Jangka Pendek), Horizon 2 (Menengah), dan Horizon 3 (Transformasi Jangka Panjang).",
    },
    {
        icon: RadarIcon,
        color: "var(--chart-2)",
        title: "Radar Multi-Dimensi",
        text: "Visualisasi polar 360° yang mencakup dimensi Sosial, Teknologi, Ekonomi, Regulasi, dan Lingkungan secara terintegrasi.",
    },
    {
        icon: ChartScatterIcon,
        color: "var(--chart-3)",
        title: "Prioritizing Matrix",
        text: "Korelasi terukur antara rating urgensi dan status tindakan untuk memastikan alokasi sumber daya tepat sasaran.",
    },
    {
        icon: ShieldCheckIcon,
        color: "var(--chart-4)",
        title: "Governance & Approval",
        text: "Alur verifikasi dan persetujuan terstruktur dari kontributor hingga strategic committee untuk kredibilitas data.",
    },
];

export default function Welcome({ auth, canRegister = true }) {
    const user = auth?.user;

    return (
        <div className="flex min-h-svh flex-col bg-background font-sans text-foreground">
            <Head title="Foresight Radar - Strategic Horizon Scanning" />

            <header className="sticky top-0 z-10 flex h-[68px] items-center justify-between gap-3 border-0 border-b border-solid border-border bg-card/90 px-4 backdrop-blur-sm sm:px-8">
                <div className="flex min-w-0 items-center gap-3">
                    <BrandMark />
                    <div className="flex min-w-0 flex-col">
                        <span className="text-base leading-tight font-bold tracking-[-0.01em] text-foreground">
                            Foresight Radar
                        </span>
                        <span className="hidden text-[10px] font-semibold tracking-[0.08em] text-subtle-foreground uppercase sm:block">
                            Strategic Horizon Scanning
                        </span>
                    </div>
                </div>

                <nav aria-label="Akun" className="flex shrink-0 items-center gap-2 sm:gap-3">
                    <ThemeToggle />
                    {user ? (
                        <Button asChild>
                            <Link href={route("dashboard.")}>
                                <GaugeIcon aria-hidden="true" />
                                Buka Dashboard
                            </Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="outline">
                                <Link href={route("login")}>
                                    <LogInIcon aria-hidden="true" />
                                    Masuk
                                </Link>
                            </Button>
                            {canRegister && (
                                <Button asChild>
                                    <Link href={route("register")}>
                                        <UserPlusIcon aria-hidden="true" />
                                        Daftar Akun
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </nav>
            </header>

            <main className="mx-auto w-full max-w-[1200px] flex-1 px-4 py-12 sm:px-6">
                <section className="mx-auto mb-14 flex max-w-[820px] flex-col items-center text-center">
                    <span className="mb-5 inline-flex items-center gap-1.5 rounded-full bg-accent px-3.5 py-1 text-xs font-semibold tracking-[0.04em] text-primary uppercase">
                        <CompassIcon aria-hidden="true" />
                        Sistem Intelijen Strategis Organisasi
                    </span>

                    <h1 className="m-0 mb-5 text-[clamp(28px,4vw,44px)] leading-tight font-extrabold tracking-[-0.02em] text-foreground">
                        Navigasi Ketidakpastian Masa Depan dengan Foresight Radar
                    </h1>

                    <p className="m-0 mb-8 max-w-[680px] text-base leading-relaxed text-muted-foreground">
                        Platform horizon scanning cerdas untuk mendeteksi sinyal lemah (weak signals), menganalisis
                        megatrend, dan memetakan driving forces ke dalam matriks prioritas aksi berbasis bukti.
                    </p>

                    <div className="flex flex-wrap justify-center gap-3">
                        {user ? (
                            <Button asChild size="lg" className="h-12 px-8 text-[15px]">
                                <Link href={route("dashboard.")}>
                                    <GaugeIcon aria-hidden="true" />
                                    Akses Dashboard Utama
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild size="lg" className="h-12 px-8 text-[15px]">
                                    <Link href={route("login")}>
                                        Mulai Sekarang
                                        <ArrowRightIcon aria-hidden="true" />
                                    </Link>
                                </Button>
                                {canRegister && (
                                    <Button asChild size="lg" variant="outline" className="h-12 px-7 text-[15px]">
                                        <Link href={route("register")}>Registrasi Akun Baru</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </section>

                <ul className="m-0 grid list-none gap-5 p-0 sm:grid-cols-2 lg:grid-cols-4">
                    {FEATURES.map(({ icon: Icon, color, title, text }) => (
                        <li
                            key={title}
                            className="flex flex-col rounded-lg border border-t-[3px] border-solid border-border bg-card p-6"
                            style={{ borderTopColor: color }}
                        >
                            <span
                                aria-hidden="true"
                                className="mb-4 flex size-11 items-center justify-center rounded-md text-[22px]"
                                style={{ color, backgroundColor: `color-mix(in srgb, ${color} 12%, transparent)` }}
                            >
                                <Icon />
                            </span>
                            <h2 className="m-0 mb-2 text-base font-semibold text-foreground">{title}</h2>
                            <p className="m-0 text-[13px] leading-normal text-muted-foreground">{text}</p>
                        </li>
                    ))}
                </ul>
            </main>

            <footer className="border-0 border-t border-solid border-border bg-card px-6 py-6 text-center text-[13px] text-muted-foreground">
                Foresight Radar © {new Date().getFullYear()} Strategic Horizon Scanning System. All Rights Reserved.
            </footer>
        </div>
    );
}
