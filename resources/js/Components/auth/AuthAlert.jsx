import { CircleAlertIcon, CircleCheckIcon, CircleXIcon } from "lucide-react";

import { cn } from "@/lib/utils";

const TONES = {
    error: { role: "alert", icon: CircleXIcon, className: "border-danger/40 bg-danger-soft text-danger" },
    warning: { role: "status", icon: CircleAlertIcon, className: "border-warning/40 bg-warning-soft text-warning" },
    success: { role: "status", icon: CircleCheckIcon, className: "border-success/40 bg-success-soft text-success" },
};

/** Inline message for auth/profile forms; errors use role="alert". */
export default function AuthAlert({ tone = "error", title, children, className }) {
    const { role, icon: Icon, className: toneClass } = TONES[tone] ?? TONES.error;

    return (
        <div role={role} className={cn("flex gap-2.5 rounded-md border border-solid p-3 text-sm", toneClass, className)}>
            <Icon aria-hidden="true" className="mt-0.5" />
            <div className="flex flex-col gap-1">
                {title && <p className="m-0 font-semibold">{title}</p>}
                {children && <div className={cn(title && "text-foreground")}>{children}</div>}
            </div>
        </div>
    );
}
