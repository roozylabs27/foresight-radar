import HintTooltip from "@/Components/HintTooltip";
import { statusBadge } from "@/Components/StatusBadge";

/** An item flagged because upstream work changed (audit U6); the reason is in the tooltip. */
export default function StaleTag({ reason }) {
    if (!reason) return null;

    return (
        <HintTooltip content={reason}>
            <button type="button" className={statusBadge({ tone: "pending" })}>
                Perlu ditinjau
            </button>
        </HintTooltip>
    );
}
