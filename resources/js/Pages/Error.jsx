import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeftIcon,
    BanIcon,
    CircleAlertIcon,
    ClockIcon,
    FileSearchIcon,
    HouseIcon,
    LogInIcon,
    RotateCwIcon,
    TriangleAlertIcon,
    WrenchIcon,
} from "lucide-react";

import BrandMark from "@/Components/BrandMark";
import ThemeToggle from "@/Components/ThemeToggle";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";

const TONES = {
    warning: "bg-warning-soft text-warning",
    danger: "bg-danger-soft text-danger",
    info: "bg-accent text-primary",
};

const ERROR_DETAILS = {
    403: {
        title: "403 — Akses Ditolak",
        heading: "Hak Akses Tidak Memadai",
        description:
            "Anda tidak memiliki izin yang diperlukan untuk mengakses halaman atau sumber daya ini. Hubungi administrator jika Anda memerlukan akses.",
        icon: BanIcon,
        tone: "warning",
    },
    404: {
        title: "404 — Halaman Tidak Ditemukan",
        heading: "Halaman Tidak Ditemukan",
        description:
            "Tautan yang Anda tuju mungkin salah, telah dipindahkan, atau tidak lagi tersedia dalam sistem Foresight Radar.",
        icon: FileSearchIcon,
        tone: "danger",
    },
    419: {
        title: "419 — Sesi Kedaluwarsa",
        heading: "Sesi Autentikasi Kedaluwarsa",
        description:
            "Token keamanan atau sesi kerja Anda telah berakhir karena tidak ada aktivitas. Silakan muat ulang halaman atau masuk kembali.",
        icon: ClockIcon,
        tone: "warning",
    },
    500: {
        title: "500 — Kesalahan Server",
        heading: "Kendala Sistem Internal",
        description:
            "Terjadi kendala teknis tak terduga pada server kami saat memproses permintaan Anda. Tim kami telah mencatat insiden ini.",
        icon: TriangleAlertIcon,
        tone: "danger",
    },
    503: {
        title: "503 — Pemeliharaan Sistem",
        heading: "Layanan Sedang Dipelihara",
        description:
            "Platform Foresight Radar sedang menjalani peningkatan atau pemeliharaan berkala. Silakan kembali dalam beberapa saat.",
        icon: WrenchIcon,
        tone: "info",
    },
};

export default function ErrorPage({ status = 404, message: customMessage, auth }) {
    const error = ERROR_DETAILS[status] || {
        title: `${status} — Kesalahan Terjadi`,
        heading: "Terjadi Kendala",
        description:
            customMessage ||
            "Permintaan Anda tidak dapat diproses saat ini. Silakan coba kembali beberapa saat lagi.",
        icon: CircleAlertIcon,
        tone: "danger",
    };
    const StatusIcon = error.icon;

    const isAuthenticated = Boolean(auth?.user);
    const homeHref = isAuthenticated ? "/dashboard" : "/login";

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = homeHref;
        }
    };

    return (
        <div className="flex min-h-svh flex-col bg-background font-sans text-foreground">
            <Head title={error.title} />

            <header className="flex h-16 items-center justify-between gap-3 border-0 border-b border-solid border-border bg-card px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    <BrandMark />
                    <div className="flex min-w-0 flex-col">
                        <span className="text-[15px] leading-tight font-bold text-foreground">Foresight Radar</span>
                        <span className="text-[11px] font-medium tracking-[0.05em] text-subtle-foreground uppercase">
                            Strategic Intel
                        </span>
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    <ThemeToggle />
                    {isAuthenticated ? (
                        <Button asChild variant="outline">
                            <Link href="/dashboard">
                                <HouseIcon aria-hidden="true" />
                                Dashboard
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href="/login">
                                <LogInIcon aria-hidden="true" />
                                Masuk
                            </Link>
                        </Button>
                    )}
                </div>
            </header>

            <main className="flex flex-1 items-center justify-center p-4 sm:p-6">
                <div className="w-full max-w-[580px] rounded-lg border border-solid border-border bg-card px-6 pt-10 pb-6 text-center shadow-md sm:px-10">
                    <span
                        aria-hidden="true"
                        className={cn("mx-auto flex size-16 items-center justify-center rounded-full text-[30px]", TONES[error.tone])}
                    >
                        <StatusIcon />
                    </span>

                    <h1 className="mt-5 mb-0 text-2xl font-bold text-foreground">{error.heading}</h1>
                    <p className="mx-auto mt-3 mb-0 max-w-[460px] text-sm leading-relaxed text-muted-foreground">
                        {customMessage || error.description}
                    </p>

                    <div className="mt-8 flex flex-wrap justify-center gap-3">
                        <Button asChild size="lg" className="min-w-[150px]">
                            <Link href={homeHref}>
                                {isAuthenticated ? <HouseIcon aria-hidden="true" /> : <LogInIcon aria-hidden="true" />}
                                {isAuthenticated ? "Ke Dashboard" : "Masuk Akun"}
                            </Link>
                        </Button>
                        <Button size="lg" variant="outline" className="min-w-[150px]" onClick={goBack}>
                            <ArrowLeftIcon aria-hidden="true" />
                            Halaman Sebelumnya
                        </Button>
                        {status === 419 && (
                            <Button
                                size="lg"
                                variant="outline"
                                className="min-w-[150px]"
                                onClick={() => window.location.reload()}
                            >
                                <RotateCwIcon aria-hidden="true" />
                                Muat Ulang
                            </Button>
                        )}
                    </div>

                    <p className="mt-8 mb-0 border-0 border-t border-solid border-border pt-4 text-xs text-muted-foreground">
                        Kode Status HTTP:{" "}
                        <code className="rounded-sm bg-muted px-1.5 py-0.5 font-mono font-semibold text-foreground">
                            {status}
                        </code>{" "}
                        | Foresight Radar Strategic Intelligence System
                    </p>
                </div>
            </main>

            <footer className="border-0 border-t border-solid border-border bg-card px-6 py-4 text-center text-xs text-muted-foreground">
                Foresight Radar © {new Date().getFullYear()} Strategic Horizon Scanning. All Rights Reserved.
            </footer>
        </div>
    );
}
