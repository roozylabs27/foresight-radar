import { useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    ChartColumnIcon,
    ChevronDownIcon,
    CircleCheckIcon,
    ClockIcon,
    FileCheckIcon,
    GaugeIcon,
    GitCompareArrowsIcon,
    InboxIcon,
    LayersIcon,
    LayoutGridIcon,
    ListIcon,
    LogOutIcon,
    MenuIcon,
    PanelLeftCloseIcon,
    PanelLeftOpenIcon,
    RadarIcon,
    UserIcon,
    WifiIcon,
    ZapIcon,
} from "lucide-react";

import BrandMark from "@/Components/BrandMark";
import { ThemeMenuItem } from "@/Components/ThemeToggle";
import { Avatar, AvatarFallback } from "@/Components/ui/avatar";
import { Button } from "@/Components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetTitle } from "@/Components/ui/sheet";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/ui/tooltip";
import { useMediaQuery } from "@/hooks/use-media-query";
import { cn } from "@/lib/utils";

const COLLAPSED_KEY = "foresight-sidebar-collapsed";

const NAV_ITEMS = [
    { label: "Dashboard", route: "dashboard.", match: "/dashboard", icon: LayoutGridIcon, permission: "view-dashboard" },
    { label: "Signal Ingestion", route: "signals.index", match: "/signals", icon: WifiIcon, permission: "view-signal" },
    { label: "Driving Force", route: "driving-force.", match: "/driving-force", icon: ZapIcon, permission: "view-driving-force" },
    { label: "Time Horizon", route: "time-horizon.", match: "/time-horizon", icon: ClockIcon, permission: "view-time-horizon" },
    { label: "Rating of Urgency", route: "rating-urgency.", match: "/rating-urgency", icon: GaugeIcon, permission: "view-rating-urgency" },
    { label: "Status of Action", route: "status-action.", match: "/status-action", icon: CircleCheckIcon, permission: "view-status-action" },
    { label: "Approval Items", route: "approval-items.", match: "/approval-items", icon: FileCheckIcon, permission: "view-approval-items" },
    { label: "Closed Items", route: "closed-items.", match: "/closed-items", icon: InboxIcon, permission: "view-closed-items" },
    { label: "User Management", route: "user-management.user.", match: "/user-management", icon: UserIcon, permission: "view-user" },
    { label: "Prioritizing", route: "visualization.prioritizing.", match: "/visualization/prioritizing", icon: ChartColumnIcon, permission: "view-prioritizing" },
    { label: "Registered List", route: "visualization.registered-list.", match: "/visualization/registered-list", icon: ListIcon, permission: "view-registered-list" },
    { label: "Foresight Radar", route: "visualization.foresight-radar.", match: "/visualization/foresight-radar", icon: RadarIcon, permission: "view-foresight-radar" },
    { label: "Cross-Impact", route: "visualization.cross-impact.", match: "/visualization/cross-impact", icon: GitCompareArrowsIcon, permission: "view-cross-impact" },
    { label: "Scenarios", route: "scenarios.index", match: "/scenarios", icon: LayersIcon, permission: "view-scenario" },
];

function readCollapsed() {
    try {
        return window.localStorage.getItem(COLLAPSED_KEY) === "1";
    } catch {
        return false;
    }
}

function writeCollapsed(value) {
    try {
        window.localStorage.setItem(COLLAPSED_KEY, value ? "1" : "0");
    } catch {
        // Storage can be unavailable (private mode); the state still applies on this page.
    }
}

function isActive(url, match) {
    const path = url.split("?")[0];
    return path === match || path.startsWith(`${match}/`);
}

function Brand({ collapsed = false }) {
    return (
        <div className={cn("flex min-w-0 items-center gap-3", collapsed && "justify-center")}>
            <BrandMark />
            {/* A div, not a span: color-contrast.e2e reads the first span containing "Strategic Intel". */}
            <div className={cn("flex min-w-0 flex-col", collapsed && "sr-only")}>
                <span className="truncate text-[15px] leading-5 font-semibold text-sidebar-foreground">
                    Foresight Radar
                </span>
                <span className="text-[11px] leading-4 font-semibold tracking-[0.08em] text-subtle-foreground uppercase">
                    Strategic Intel
                </span>
            </div>
        </div>
    );
}

function NavList({ items, url, collapsed = false, onNavigate }) {
    return (
        <ul className="flex flex-col gap-0.5">
            {items.map((item) => {
                const active = isActive(url, item.match);
                const Icon = item.icon;
                const link = (
                    <Link
                        href={route(item.route)}
                        onClick={onNavigate}
                        aria-current={active ? "page" : undefined}
                        aria-label={collapsed ? item.label : undefined}
                        className={cn(
                            "flex min-h-10 items-center gap-3 rounded-md px-3 text-sm outline-none transition-colors",
                            "focus-visible:ring-[3px] focus-visible:ring-sidebar-ring/50",
                            active
                                ? "bg-sidebar-accent font-semibold text-sidebar-accent-foreground"
                                : "font-medium text-muted-foreground hover:bg-muted hover:text-sidebar-foreground",
                            collapsed && "justify-center px-0"
                        )}
                    >
                        <Icon aria-hidden="true" className="shrink-0 text-base leading-none" />
                        {!collapsed && <span className="truncate">{item.label}</span>}
                    </Link>
                );

                return (
                    <li key={item.match}>
                        {collapsed ? (
                            <Tooltip>
                                <TooltipTrigger asChild>{link}</TooltipTrigger>
                                <TooltipContent side="right" sideOffset={8}>
                                    {item.label}
                                </TooltipContent>
                            </Tooltip>
                        ) : (
                            link
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function UserMenu({ user }) {
    const name = user?.name || "Pengguna";
    const initial = name.charAt(0).toUpperCase();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-11 gap-2.5 pr-2 pl-1">
                    <Avatar>
                        <AvatarFallback className="bg-accent font-semibold text-primary">
                            {initial}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-40 truncate font-medium sm:inline">{name}</span>
                    <ChevronDownIcon aria-hidden="true" className="text-[10px] text-subtle-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="flex flex-col gap-0.5">
                    <span className="truncate text-sm font-semibold">{name}</span>
                    {user?.email && (
                        <span className="truncate text-xs font-normal text-muted-foreground">
                            {user.email}
                        </span>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route("profile.edit")}>
                        <UserIcon aria-hidden="true" />
                        <span>Profil Pengguna</span>
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <ThemeMenuItem />
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" asChild>
                    <Link href={route("logout")} method="post" as="button" type="button" className="w-full">
                        <LogOutIcon aria-hidden="true" />
                        <span>Keluar (Logout)</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function Authenticated({ auth, user: propUser, header, children }) {
    const page = usePage();
    const effectiveAuth = auth || page.props.auth || {};
    const user = effectiveAuth.user || propUser;
    const permissions = effectiveAuth.permissions || [];
    const role = effectiveAuth.role;

    const items = NAV_ITEMS.filter(
        (item) =>
            !item.permission ||
            role === "developer" ||
            role === "super-admin" ||
            permissions.includes(item.permission)
    );

    const isDesktop = useMediaQuery("(min-width: 1024px)");
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [mobileOpen, setMobileOpen] = useState(false);

    const navOpen = isDesktop ? !collapsed : mobileOpen;
    const toggleNav = () => {
        if (isDesktop) {
            writeCollapsed(!collapsed);
            setCollapsed(!collapsed);
        } else {
            setMobileOpen(!mobileOpen);
        }
    };
    const ToggleIcon = !isDesktop ? MenuIcon : collapsed ? PanelLeftOpenIcon : PanelLeftCloseIcon;

    return (
        <TooltipProvider>
            <div className="flex min-h-svh bg-background font-sans text-foreground">
                {isDesktop && (
                    <aside
                        data-slot="sidebar"
                        aria-label="Navigasi utama"
                        className={cn(
                            "sticky top-0 z-20 flex h-svh shrink-0 flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-200 motion-reduce:transition-none",
                            collapsed ? "w-[72px]" : "w-60"
                        )}
                    >
                        <div
                            className={cn(
                                "flex h-16 shrink-0 items-center border-b border-sidebar-border",
                                collapsed ? "justify-center px-2" : "px-5"
                            )}
                        >
                            <Brand collapsed={collapsed} />
                        </div>
                        <nav className="flex-1 overflow-y-auto px-3 py-4">
                            <NavList items={items} url={page.url} collapsed={collapsed} />
                        </nav>
                    </aside>
                )}

                {!isDesktop && (
                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetContent
                            side="left"
                            showCloseButton={false}
                            className="w-[280px] gap-0 bg-sidebar sm:max-w-[280px]"
                        >
                            <SheetTitle className="sr-only">Navigasi utama</SheetTitle>
                            <SheetDescription className="sr-only">
                                Pilih halaman Foresight Radar yang ingin dibuka.
                            </SheetDescription>
                            <div className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border pr-3 pl-5">
                                <Brand />
                                <SheetClose asChild>
                                    <Button variant="ghost" size="icon" aria-label="Tutup menu navigasi">
                                        <PanelLeftCloseIcon aria-hidden="true" />
                                    </Button>
                                </SheetClose>
                            </div>
                            <nav aria-label="Navigasi utama" className="flex-1 overflow-y-auto px-3 py-4">
                                <NavList items={items} url={page.url} onNavigate={() => setMobileOpen(false)} />
                            </nav>
                        </SheetContent>
                    </Sheet>
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-solid border-border bg-card px-4 lg:px-5">
                        <div className="flex min-w-0 flex-1 items-center gap-3">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={toggleNav}
                                aria-expanded={navOpen}
                                aria-label={navOpen ? "Tutup menu navigasi" : "Buka menu navigasi"}
                                className="size-11 shrink-0 border-input bg-card"
                            >
                                <ToggleIcon aria-hidden="true" className="text-base" />
                            </Button>
                            {header && (
                                <div className="flex min-w-0 flex-1 items-center overflow-hidden whitespace-nowrap">
                                    {header}
                                </div>
                            )}
                        </div>
                        <UserMenu user={user} />
                    </header>

                    {children}

                    <footer className="mt-auto px-6 py-2.5 text-center text-xs text-subtle-foreground">
                        Foresight Radar © {new Date().getFullYear()} Strategic Horizon Scanning. All Rights Reserved.
                    </footer>
                </div>
            </div>
        </TooltipProvider>
    );
}
