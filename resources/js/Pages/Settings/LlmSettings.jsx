import { Head } from "@inertiajs/react";
import { BotIcon, InfoIcon, ShieldCheckIcon, SlidersIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ProviderCard from "@/Components/Settings/ProviderCard";

export default function LlmSettings({ auth, providers = [], title = "LLM Configuration" }) {
    const activeProvider = providers.find((p) => p.is_active);

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <SlidersIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-6 p-4 lg:px-8 lg:py-6 max-w-7xl mx-auto w-full">
                {/* Intro Hero Banner */}
                <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-5 shadow-xs">
                    <div className="flex items-center gap-2 text-primary">
                        <BotIcon className="size-5" />
                        <h2 className="text-base font-semibold text-foreground">Pengaturan Model &amp; Provider AI Personal</h2>
                    </div>
                    <p className="text-xs text-muted-foreground leading-relaxed max-w-3xl">
                        Atur API Key dan model pilihan Anda sendiri (Claude, ChatGPT, Gemini, atau Custom Gateway).
                        Setiap kali Anda menjalankan ekstraksi sinyal dokumen atau menghasilkan narasi skenario 2x2,
                        sistem akan menggunakan provider aktif yang Anda pilih di bawah ini.
                    </p>

                    <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-muted-foreground border-t border-border/60 pt-3">
                        <div className="flex items-center gap-1.5">
                            <ShieldCheckIcon className="size-4 text-emerald-600 dark:text-emerald-400" />
                            <span>API Key dienkripsi aman di database dengan AES-256</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <InfoIcon className="size-4 text-sky-600 dark:text-sky-400" />
                            <span>
                                Provider aktif saat ini:{" "}
                                <strong className="text-foreground font-semibold">
                                    {activeProvider ? activeProvider.label : "Default Sistem (.env)"}
                                </strong>
                            </span>
                        </div>
                    </div>
                </div>

                {/* Providers Grid */}
                <section aria-label="Daftar Provider LLM" className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-foreground">Daftar Provider LLM</h3>
                        <span className="text-xs text-muted-foreground">Hanya 1 provider yang aktif dalam satu waktu</span>
                    </div>

                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        {providers.map((providerData) => (
                            <ProviderCard key={providerData.provider} providerData={providerData} />
                        ))}
                    </div>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}

