import { Link, usePage } from "@inertiajs/react";
import {
    BrainCircuitIcon,
    KeyRoundIcon,
    SettingsIcon,
    UserIcon,
} from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { useTranslation } from "@/context/I18nContext";
import { cn } from "@/lib/utils";

const SETTINGS_NAV_CONFIG = [
    {
        key: "ai_engine",
        title: "Mesin AI & Reasoning",
        description: "Provider & model kecerdasan buatan untuk analisis",
        icon: BrainCircuitIcon,
        href: "/settings/llm",
        match: "/settings",
    },
    {
        key: "profile_account",
        title: "Profil & Akun",
        description: "Informasi identitas dan alamat surel",
        icon: UserIcon,
        href: "/profile",
        match: "/profile",
    },
];

export default function SettingsLayout({
    auth,
    title,
    subtitle,
    activeTab = "ai",
    children,
}) {
    const page = usePage();
    const { t } = useTranslation();
    const currentUrl = page.url || "";
    const resolvedTitle = title || t("settings.title", "Pengaturan");

    return (
        <AuthenticatedLayout
            auth={auth}
            breadcrumbs={[
                { label: t("settings.title", "Pengaturan"), href: route("settings.index") },
                { label: resolvedTitle },
            ]}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <SettingsIcon aria-hidden="true" className="size-5 text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{resolvedTitle}</h1>
                </div>
            }
        >
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 lg:px-8 lg:py-6">
                {/* Settings Layout Shell: Sidebar + Content */}
                <div className="flex flex-col gap-8 lg:flex-row lg:items-start">
                    {/* Settings Sub-Sidebar */}
                    <aside
                        aria-label="Menu Pengaturan"
                        className="w-full shrink-0 lg:w-64 lg:sticky lg:top-20"
                    >
                        <div className="mb-3 px-1">
                            <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                {t("settings.categories", "Kategori Pengaturan")}
                            </h2>
                        </div>
                        <nav className="flex flex-row gap-1.5 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0">
                            {SETTINGS_NAV_CONFIG.map((item) => {
                                const Icon = item.icon;
                                const isActive = currentUrl.startsWith(item.match);
                                const itemTitle = t(`settings.${item.key}`, item.title);
                                const itemDesc = t(`settings.${item.key}_desc`, item.description);

                                return (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        className={cn(
                                            "group flex items-start gap-3 rounded-lg border border-transparent p-3 text-left transition-colors",
                                            isActive
                                                ? "bg-accent/80 font-medium text-accent-foreground border-border/50 shadow-2xs"
                                                : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"
                                        )}
                                    >
                                        <div
                                            className={cn(
                                                "mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border",
                                                isActive
                                                    ? "border-primary/20 bg-primary/10 text-primary"
                                                    : "border-border/60 bg-background text-muted-foreground group-hover:text-foreground"
                                            )}
                                        >
                                            <Icon className="size-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div
                                                className={cn(
                                                    "text-sm",
                                                    isActive ? "font-semibold text-foreground" : "font-medium"
                                                )}
                                            >
                                                {itemTitle}
                                            </div>
                                            <p className="hidden text-xs text-muted-foreground/80 line-clamp-1 lg:block">
                                                {itemDesc}
                                            </p>
                                        </div>
                                    </Link>
                                );
                            })}
                        </nav>
                    </aside>

                    {/* Settings Content Area */}
                    <div className="flex min-w-0 flex-1 flex-col gap-6">
                        {children}
                    </div>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

