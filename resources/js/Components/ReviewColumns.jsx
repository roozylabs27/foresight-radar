import DimensionChip from "@/Components/DimensionChip";
import PriorityBadge from "@/Components/PriorityBadge";
import StatusBadge, { statusBadge } from "@/Components/StatusBadge";
import { cn } from "@/lib/utils";

/** Status badge with the date it reached that status (`approved_at`, `closed_at`) underneath. */
export function StatusWithDate({ status, date }) {
    return (
        <div className="flex flex-col items-center gap-1">
            <StatusBadge status={status} />
            {date && <span className="text-xs whitespace-nowrap text-muted-foreground">{date}</span>}
        </div>
    );
}

/**
 * Columns shared by the Approval Items and Closed Items tables
 * (ApprovalItemsResource / ClosedItemsResource). `status` renders the Status cell.
 */
export function reviewColumns({ status }) {
    return [
        {
            accessorKey: "date_created",
            enableSorting: true,
            header: "Date Created",
            meta: { className: "whitespace-nowrap" },
        },
        {
            accessorKey: "dimension",
            enableSorting: true,
            header: "Dimension",
            cell: ({ getValue }) => <DimensionChip name={getValue()} />,
        },
        {
            accessorKey: "keyword",
            enableSorting: true,
            header: "Keyword",
            meta: { className: "min-w-[140px] font-medium text-foreground" },
        },
        {
            accessorKey: "description",
            header: "Description",
            meta: { className: "min-w-[240px] max-w-[360px] text-muted-foreground" },
        },
        {
            accessorKey: "pic",
            enableSorting: true,
            header: "Personal In Charge",
            meta: { align: "center" },
        },
        {
            accessorKey: "time_horizon",
            enableSorting: true,
            header: "Time Horizon",
            meta: { align: "center", className: "whitespace-nowrap" },
        },
        {
            accessorKey: "priority",
            enableSorting: true,
            header: "Priority",
            meta: { align: "center" },
            cell: ({ getValue }) => <PriorityBadge priority={getValue()} />,
        },
        {
            accessorKey: "uncertainty_analysis",
            enableSorting: true,
            header: "Uncertainty",
            meta: { align: "center", className: "tabular-nums" },
        },
        {
            accessorKey: "impact_analysis",
            enableSorting: true,
            header: "Impact",
            meta: { align: "center", className: "tabular-nums" },
        },
        {
            accessorKey: "status",
            enableSorting: true,
            header: "Status",
            meta: { align: "center" },
            cell: ({ row }) => status(row.original),
        },
        {
            accessorKey: "status_action",
            enableSorting: true,
            header: "Status Action",
            meta: { align: "center" },
            cell: ({ getValue }) =>
                getValue() ? <span className={cn(statusBadge(), "font-mono")}>{getValue()}</span> : "-",
        },
        {
            accessorKey: "reason",
            header: "Reason",
            meta: { className: "min-w-[220px] max-w-[320px] text-muted-foreground" },
            // Reasons run long; keep rows compact and leave the full text in the tooltip.
            cell: ({ getValue }) =>
                getValue() ? (
                    <span title={getValue()} className="line-clamp-3">
                        {getValue()}
                    </span>
                ) : (
                    "-"
                ),
        },
    ];
}
