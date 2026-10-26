import { Link } from "@inertiajs/react";
import { ChevronRightIcon, HouseIcon } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * Accessible Breadcrumb navigation adhering to W3C ARIA standards and Foresight Radar design system.
 * @param {Object} props
 * @param {Array<{ label: string, href?: string, icon?: any }>} props.items
 * @param {string} [props.className]
 */
export default function Breadcrumb({ items = [], className }) {
    if (!items || items.length === 0) return null;

    return (
        <nav aria-label="Breadcrumb" className={cn("flex items-center", className)}>
            <ol className="m-0 flex flex-wrap list-none items-center gap-1.5 p-0 text-xs sm:text-sm text-muted-foreground">
                <li className="inline-flex items-center">
                    <Link
                        href={route("dashboard.")}
                        className="inline-flex items-center gap-1.5 rounded-xs text-muted-foreground no-underline outline-none transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <HouseIcon aria-hidden="true" className="size-3.5" />
                        <span className="hidden sm:inline">Dashboard</span>
                    </Link>
                </li>
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;
                    return (
                        <li key={`${item.label}-${index}`} className="inline-flex items-center gap-1.5">
                            <span aria-hidden="true" className="text-muted-foreground/50">
                                <ChevronRightIcon className="size-3.5" />
                            </span>
                            {item.href && !isLast ? (
                                <Link
                                    href={item.href}
                                    className="inline-flex items-center gap-1 rounded-xs text-muted-foreground no-underline outline-none transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    {item.icon && <item.icon aria-hidden="true" className="size-3.5" />}
                                    <span>{item.label}</span>
                                </Link>
                            ) : (
                                <span
                                    aria-current={isLast ? "page" : undefined}
                                    className={cn(
                                        "inline-flex items-center gap-1",
                                        isLast ? "font-medium text-foreground" : "text-muted-foreground"
                                    )}
                                >
                                    {item.icon && <item.icon aria-hidden="true" className="size-3.5" />}
                                    <span>{item.label}</span>
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

