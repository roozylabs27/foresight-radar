import { useState } from "react";
import { router } from "@inertiajs/react";
import { Building2Icon } from "lucide-react";

import { Select, SelectContent, SelectItem, SelectSeparator, SelectTrigger, SelectValue } from "@/Components/ui/select";

const NONE = "__none__";

/**
 * Workspace picker for platform operators, who have no tenant of their own.
 * Without a pick they see no tenant data and cannot write any (TENANT-007).
 *
 * @param {{ operator: { workspaces: Array<{ uuid: string, name: string, status: string }>, current: string | null } }} props
 */
export default function OperatorWorkspaceSwitcher({ operator }) {
    const [saving, setSaving] = useState(false);
    const current = operator.current ?? NONE;

    const choose = (value) => {
        if (value === current) return;
        setSaving(true);
        router.post(
            route("operator.workspace"),
            { tenant: value === NONE ? null : value },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <Select value={current} onValueChange={choose} disabled={saving}>
            <SelectTrigger
                size="sm"
                aria-label="Workspace operator"
                className={current === NONE ? "max-w-48 border-warning text-warning" : "max-w-48"}
            >
                <Building2Icon aria-hidden="true" className="size-3.5" />
                <SelectValue placeholder="Pilih workspace" />
            </SelectTrigger>
            <SelectContent position="popper" align="end">
                <SelectItem value={NONE}>Tanpa workspace</SelectItem>
                <SelectSeparator />
                {operator.workspaces.map((workspace) => (
                    <SelectItem key={workspace.uuid} value={workspace.uuid}>
                        {workspace.name}
                        {workspace.status !== "active" && <span className="text-xs text-muted-foreground"> ({workspace.status})</span>}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
