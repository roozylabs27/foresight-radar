import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import dayjs from "dayjs";
import qs from "qs";
import { toast } from "sonner";
import { CheckIcon, FileSpreadsheetIcon, LoaderCircleIcon } from "lucide-react";

import { currentMonth } from "@/Components/AnalysisFilters";
import DataTable, { spanEqual, useServerTable } from "@/Components/DataTable";
import DimensionChip from "@/Components/DimensionChip";
import { statusBadge } from "@/Components/StatusBadge";
import { Button } from "@/Components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";
import { cn } from "@/lib/utils";

const VIEWS = [
    { value: "overall", label: "Overall" },
    { value: "detail", label: "Detail Info" },
];

// OverallStatusResource marks the matching column with "✔" (or the status code).
function CheckMark({ value, label }) {
    if (!value) return null;

    return (
        <span className="inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground">
            <CheckIcon aria-hidden="true" />
            <span className="sr-only">{label}</span>
        </span>
    );
}

/** Status code with the latest action reason in a tooltip (focusable for keyboard users). */
function StatusCode({ code, label, reason }) {
    if (!code) return null;

    const badge = (
        <span className={cn(statusBadge(), "font-mono")}>
            <span aria-hidden="true">{code}</span>
            <span className="sr-only">{label}</span>
        </span>
    );
    if (!reason) return badge;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    tabIndex={0}
                    className="inline-flex cursor-help rounded-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    {badge}
                </span>
            </TooltipTrigger>
            <TooltipContent side="left" className="max-w-xs">
                {reason}
            </TooltipContent>
        </Tooltip>
    );
}

function markColumns(keys) {
    return keys.map(([key, header]) => ({
        accessorKey: key,
        header,
        meta: { align: "center", width: "92px" },
        cell: ({ getValue }) => <CheckMark value={getValue()} label={header} />,
    }));
}

export function sanitizeSpreadsheetCell(value) {
    if (typeof value !== "string" || value.length === 0) {
        return value;
    }
    return /^[=+\-@\t\r]/.test(value) ? `'${value}` : value;
}

// The xlsx rows merge the Dimension cells of consecutive rows, like the table.
async function exportWorkbook(records) {
    const XLSX = await import("xlsx");
    const rows = records.map((record, index) => ({
        No: index + 1,
        Dimension: sanitizeSpreadsheetCell(record.dimension),
        Keyword: sanitizeSpreadsheetCell(record.keyword),
        Description: sanitizeSpreadsheetCell(record.description),
        "Short Term": record.short_term,
        "Mid Term": record.mid_term,
        "Long Term": record.long_term,
        High: record.high,
        Medium: record.medium,
        Low: record.low,
        "Decided Plan": record.decided_plan,
        Monitor: record.monitoring,
    }));

    const worksheet = XLSX.utils.json_to_sheet(rows);
    const merges = [];
    let start = 0;
    for (let index = 1; index <= rows.length; index++) {
        if (index === rows.length || rows[index].Dimension !== rows[start].Dimension) {
            if (index - start > 1) {
                // +1: row 0 is the header; column 1 is Dimension.
                merges.push({ s: { r: start + 1, c: 1 }, e: { r: index, c: 1 } });
            }
            start = index;
        }
    }
    worksheet["!merges"] = merges;

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Registered List");
    XLSX.writeFile(workbook, `${dayjs().format("YYYY-MM-DD")} - Registered List Data.xlsx`);
}

/**
 * Registered driving forces grouped by dimension, with an Overall and a
 * Detail Info view and an Excel export (`export-registered-list`).
 *
 * `date` is `{ date: [start, end] }` (defaults to this month) and `selectedData`
 * holds the extra filters (`dimension`, `time_horizon`, `priority`,
 * `status_action`); empty values are not sent. `setLoading` mirrors the
 * loading state for a parent that shows its own indicator.
 */
export default function OverallStatus({ date, selectedData, permissions = [], setLoading }) {
    const [view, setView] = useState("overall");
    const [exporting, setExporting] = useState(false);

    const params = {
        date: date?.date ?? currentMonth(),
        ...Object.fromEntries(
            Object.entries(selectedData ?? {}).filter(([, value]) => value !== null && value !== undefined && value !== "")
        ),
    };

    const table = useServerTable({
        url: route("visualization.registered-list.get-data"),
        params,
        initialPageSize: 20,
    });

    useEffect(() => {
        setLoading?.(table.loading || exporting);
    }, [table.loading, exporting, setLoading]);

    const handleExport = async () => {
        setExporting(true);
        try {
            const response = await axios.get(
                `${route("visualization.registered-list.export-data")}?${qs.stringify(params)}`
            );
            if (!response.data?.length) {
                toast.info("Tidak ada data untuk diekspor.");
                return;
            }
            await exportWorkbook(response.data);
        } catch (error) {
            toast.error("Export gagal", { description: error.response?.data?.message ?? error.message });
        } finally {
            setExporting(false);
        }
    };

    const { pageIndex, pageSize } = table.pagination;

    const columns = useMemo(() => {
        const dimension = {
            accessorKey: "dimension",
            header: "DIMENSION",
            meta: { rowSpan: spanEqual("dimension"), className: "min-w-[150px]" },
            cell: ({ getValue }) => <DimensionChip name={getValue()} />,
        };
        const number = {
            id: "no",
            header: () => <span className="sr-only">No</span>,
            meta: { align: "center", width: "56px", className: "tabular-nums text-muted-foreground" },
            cell: ({ row }) => pageIndex * pageSize + row.index + 1,
        };

        if (view === "detail") {
            return [
                dimension,
                number,
                {
                    accessorKey: "description",
                    header: "Driving Force Detail",
                    meta: { className: "min-w-[320px]" },
                },
            ];
        }

        return [
            dimension,
            number,
            {
                accessorKey: "keyword",
                header: "DRIVING FORCE",
                meta: { className: "min-w-[180px] font-medium text-foreground" },
            },
            {
                id: "time_horizon",
                header: "TIME HORIZON",
                columns: markColumns([
                    ["short_term", "Short Term"],
                    ["mid_term", "Mid Term"],
                    ["long_term", "Long Term"],
                ]),
            },
            {
                id: "prioritizing",
                header: "PRIORITIZING",
                columns: markColumns([
                    ["high", "High"],
                    ["medium", "Medium"],
                    ["low", "Low"],
                ]),
            },
            {
                id: "status_of_action",
                header: "STATUS OF ACTION",
                columns: [
                    ["decided_plan", "Decided Plan"],
                    ["monitoring", "Monitor"],
                ].map(([key, header]) => ({
                    accessorKey: key,
                    header,
                    meta: { align: "center", width: "112px" },
                    cell: ({ row, getValue }) => (
                        <StatusCode code={getValue()} label={header} reason={row.original.action_reason} />
                    ),
                })),
            },
        ];
    }, [view, pageIndex, pageSize]);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={view}
                    onValueChange={(value) => value && setView(value)}
                    aria-label="Tampilan tabel"
                >
                    {VIEWS.map((item) => (
                        <ToggleGroupItem
                            key={item.value}
                            value={item.value}
                            className="px-4 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                        >
                            {item.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                {permissions.includes("export-registered-list") && (
                    <Button onClick={handleExport} disabled={exporting} aria-busy={exporting || undefined}>
                        {exporting ? <LoaderCircleIcon aria-hidden="true" className="animate-spin" /> : <FileSpreadsheetIcon aria-hidden="true" />}
                        Export to Excel
                    </Button>
                )}
            </div>

            <DataTable
                columns={columns}
                {...table.tableProps}
                getRowId={(row) => row.id}
                caption={view === "detail" ? "Detail driving force per dimensi" : "Status driving force per dimensi"}
            />
        </div>
    );
}
