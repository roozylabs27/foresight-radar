import { MoonIcon } from "lucide-react";
import { DropdownMenu as DropdownMenuPrimitive } from "radix-ui";

import { useTheme } from "@/Components/ThemeProvider";
import { Switch, SwitchIndicator } from "@/Components/ui/switch";
import { cn } from "@/lib/utils";

/**
 * "Mode gelap" switch row for an existing dropdown menu (the user menu). It is a
 * menuitemcheckbox that keeps the menu open, so the change is visible at once.
 */
export function ThemeMenuItem() {
    const { theme, setTheme } = useTheme();
    const dark = theme === "dark";

    return (
        <DropdownMenuPrimitive.CheckboxItem
            data-slot="dropdown-menu-checkbox-item"
            checked={dark}
            onCheckedChange={(checked) => setTheme(checked ? "dark" : "light")}
            onSelect={(event) => event.preventDefault()}
            className="relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none focus:bg-accent focus:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
        >
            <MoonIcon aria-hidden="true" className="text-muted-foreground" />
            <span className="flex-1">Mode gelap</span>
            <SwitchIndicator checked={dark} />
        </DropdownMenuPrimitive.CheckboxItem>
    );
}

/** Standalone dark-mode switch for pages without the user menu (login, error...). */
export default function ThemeToggle({ className }) {
    const { theme, setTheme } = useTheme();

    return (
        <div className={cn("flex shrink-0 items-center gap-2 text-muted-foreground", className)}>
            <MoonIcon aria-hidden="true" />
            <Switch
                checked={theme === "dark"}
                onCheckedChange={(checked) => setTheme(checked ? "dark" : "light")}
                aria-label="Mode gelap"
            />
        </div>
    );
}
