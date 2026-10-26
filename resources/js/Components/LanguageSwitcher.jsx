import { CheckIcon, GlobeIcon, Loader2Icon } from "lucide-react";
import { useTranslation } from "@/context/I18nContext";
import { Button } from "@/Components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { cn } from "@/lib/utils";

/**
 * Accessible Language Switcher dropdown adhering to executive design standards.
 * @param {Object} props
 * @param {string} [props.className]
 * @param {"sm" | "default" | "icon"} [props.size]
 */
export default function LanguageSwitcher({ className, size = "default" }) {
    const { locale, supportedLocales, changeLanguage, isChanging, t } = useTranslation();

    const activeMeta = supportedLocales[locale] || { code: locale, name: locale.toUpperCase(), short: locale.toUpperCase() };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size={size === "icon" ? "icon" : "sm"}
                    disabled={isChanging}
                    aria-label={t("common.select_language")}
                    className={cn(
                        "inline-flex items-center gap-1.5 px-2 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring",
                        className
                    )}
                >
                    {isChanging ? (
                        <Loader2Icon aria-hidden="true" className="size-3.5 animate-spin text-primary" />
                    ) : (
                        <GlobeIcon aria-hidden="true" className="size-3.5" />
                    )}
                    {size !== "icon" && <span className="font-semibold uppercase tracking-wider">{activeMeta.short || activeMeta.code}</span>}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {Object.entries(supportedLocales).map(([code, meta]) => {
                    const isSelected = code === locale;
                    return (
                        <DropdownMenuItem
                            key={code}
                            disabled={isChanging || isSelected}
                            onClick={() => changeLanguage(code)}
                            className={cn(
                                "flex cursor-pointer items-center justify-between text-xs",
                                isSelected && "font-semibold text-primary"
                            )}
                        >
                            <span className="flex items-center gap-2">
                                <span className="w-5 text-[10px] font-bold text-muted-foreground uppercase">{meta.short || code}</span>
                                <span>{meta.name}</span>
                            </span>
                            {isSelected && <CheckIcon aria-hidden="true" className="size-3.5 text-primary" />}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
