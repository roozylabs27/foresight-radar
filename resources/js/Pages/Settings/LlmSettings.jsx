import { Head } from "@inertiajs/react";
import { BrainCircuitIcon, InfoIcon, ShieldCheckIcon } from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
import ProviderCard from "@/Components/Settings/ProviderCard";

export default function LlmSettings({ auth, providers = [], title = "Mesin AI & Reasoning" }) {
    const activeProvider = providers.find((p) => p.is_active);

    return (
        <SettingsLayout
            auth={auth}
            title={title}
            activeTab="ai"
        >
            <Head title={title} />

            {/* Intro Hero Banner */}
            <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-5 shadow-2xs">
                <div className="flex items-center gap-2 text-primary">
                    <BrainCircuitIcon className="size-5" />
                    <h2 className="text-base font-semibold text-foreground">Mesin AI &amp; Reasoning</h2>
                </div>
                <p className="text-xs text-muted-foreground leading-relaxed max-w-3xl">
                    Pilih dan atur mesin kecerdasan buatan (Claude, ChatGPT, Gemini, atau Custom AI Gateway)
                    yang akan digunakan untuk analisis otomatis. Mesin aktif ini akan memproses ekstraksi
                    sinyal strategis dari dokumen serta mensintesis narasi skenario foresight 2x2.
                </p>

                <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-muted-foreground border-t border-border/60 pt-3">
                    <div className="flex items-center gap-1.5">
                        <ShieldCheckIcon className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Kredensial API dienkripsi aman di database (AES-256)</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <InfoIcon className="size-4 text-sky-600 dark:text-sky-400" />
                        <span>
                            Mesin aktif saat ini:{" "}
                            <strong className="text-foreground font-semibold">
                                {activeProvider ? activeProvider.label : "Default Sistem (.env)"}
                            </strong>
                        </span>
                    </div>
                </div>
            </div>

            {/* Providers Grid */}
            <section aria-label="Daftar Penyedia Model AI" className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-foreground">Pilihan Penyedia Model AI</h3>
                    <span className="text-xs text-muted-foreground">Tepat 1 mesin aktif per pengguna</span>
                </div>

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    {providers.map((providerData) => (
                        <ProviderCard key={providerData.provider} providerData={providerData} />
                    ))}
                </div>
            </section>
        </SettingsLayout>
    );
}
