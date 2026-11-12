import HintTooltip from "@/Components/HintTooltip";
import { statusBadge } from "@/Components/StatusBadge";

/** An item flagged because upstream work changed (audit U6); the reason is in the tooltip. */
export default function StaleTag({ reason }) {
    if (!reason) return null;

    return (
        <HintTooltip content={reason}>
            <span tabIndex={0} className={statusBadge({ tone: "pending" })}>
                Perlu ditinjau
            </span>
        </HintTooltip>
    );
}
