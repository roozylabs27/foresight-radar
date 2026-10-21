import { useId, useMemo } from "react";
import { rowPaginationFeature, rowSortingFeature, tableFeatures, useTable } from "@tanstack/react-table";
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon, ChevronLeftIcon, ChevronRightIcon } from "lucide-react";

import { Button } from "@/Components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Skeleton } from "@/Components/ui/skeleton";
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { cn } from "@/lib/utils";

export { useServerTable } from "@/hooks/use-server-table";

const features = tableFeatures({ rowPaginationFeature, rowSortingFeature });
const ALIGN = { left: "text-left", center: "text-center", right: "text-right" };

/**
 * `meta.rowSpan` helper: merges consecutive rows with the same `key` value,
 * like antd's onCell rowSpan for a grouped first column.
 */
export function spanEqual(key) {
    return (index, records) => {
        const value = records[index]?.[key];
        if (index > 0 && records[index - 1]?.[key] === value) return 0;
        let span = 1;
        while (records[index + span]?.[key] === value) span += 1;
        return span;
    };
}

// Sorting is opt-in per column, grouped columns included.
function withSortingOff(columns) {
    return columns.map((column) => ({
        enableSorting: false,
        ...column,
        ...(column.columns && { columns: withSortingOff(column.columns) }),
    }));
}

function SortIcon({ direction }) {
    const Icon = direction === "asc" ? ArrowUpIcon : direction === "desc" ? ArrowDownIcon : ArrowUpDownIcon;
    return <Icon aria-hidden="true" className={cn("size-3.5", direction ? "text-primary" : "text-subtle-foreground")} />;
}

function HeaderContent({ table, header }) {
    const column = header.column;
    if (!column.getCanSort()) return <table.FlexRender header={header} />;

    const direction = column.getIsSorted();
    return (
        <button
            type="button"
            onClick={column.getToggleSortingHandler()}
            className="-mx-1 inline-flex items-center gap-1.5 rounded-sm px-1 font-semibold outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            <table.FlexRender header={header} />
            <SortIcon direction={direction} />
        </button>
    );
}

function Pagination({ table, rowCount, pageSizeOptions, loading }) {
    const { pageIndex, pageSize } = table.state.pagination;
    const pageCount = Math.max(table.getPageCount(), 1);
    const first = rowCount === 0 ? 0 : pageIndex * pageSize + 1;
    const last = Math.min((pageIndex + 1) * pageSize, rowCount);
    const pageSizeLabelId = useId();

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm text-muted-foreground">
            <p aria-live="polite">
                Menampilkan <span className="font-medium text-foreground">{first}–{last}</span> dari{" "}
                <span className="font-medium text-foreground">{rowCount}</span>
            </p>
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2">
                    <span id={pageSizeLabelId}>Baris per halaman</span>
                    <Select
                        value={String(pageSize)}
                        onValueChange={(value) => table.setPageSize(Number(value))}
                    >
                        <SelectTrigger size="sm" aria-labelledby={pageSizeLabelId} className="w-[76px] border-input">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="end">
                            {pageSizeOptions.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <span className="tabular-nums">
                    Halaman {pageIndex + 1} dari {pageCount}
                </span>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon-sm"
                        className="border-input"
                        aria-label="Halaman sebelumnya"
                        disabled={loading || !table.getCanPreviousPage()}
                        onClick={() => table.previousPage()}
                    >
                        <ChevronLeftIcon aria-hidden="true" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon-sm"
                        className="border-input"
                        aria-label="Halaman berikutnya"
                        disabled={loading || !table.getCanNextPage()}
                        onClick={() => table.nextPage()}
                    >
                        <ChevronRightIcon aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

/**
 * Server-driven table on TanStack Table v9. Pair with `useServerTable` for the
 * axios + qs + route() fetching, or control `pagination`/`sorting` yourself.
 *
 * Column defs are TanStack's (`accessorKey`, `header`, `cell`). Sorting runs on
 * the server and is off unless a column sets `enableSorting: true`; the column
 * id must also be whitelisted for its endpoint (app/Support/TableSort.php).
 * Extra per-column options go in `meta`:
 * `align` ("left" | "center" | "right"), `width` (CSS value), `className`,
 * `headerClassName`, `rowSpan` (see `spanEqual`). Nested `columns` render as
 * grouped header rows.
 * `onRowHover(record | null)` and `rowClassName(record)` support linked views
 * (e.g. highlighting a chart marker for the hovered row).
 */
export default function DataTable({
    columns,
    data,
    rowCount = data?.length ?? 0,
    pagination,
    onPaginationChange,
    sorting = [],
    onSortingChange,
    loading = false,
    getRowId,
    caption,
    emptyMessage = "Tidak ada data.",
    pageSizeOptions = [10, 20, 50, 100],
    onRowClick,
    onRowHover,
    rowClassName,
    className,
}) {
    const tableColumns = useMemo(() => withSortingOff(columns), [columns]);

    const table = useTable({
        features,
        columns: tableColumns,
        data,
        rowCount,
        getRowId,
        state: { pagination, sorting },
        onPaginationChange,
        onSortingChange,
        manualPagination: true,
        manualSorting: true,
        // antd order: ascending, descending, then off.
        sortDescFirst: false,
        enableSortingRemoval: true,
    });

    const headerGroups = table.getHeaderGroups();
    const leafHeaders = headerGroups[headerGroups.length - 1]?.headers ?? [];
    const spanned = new Set();

    const rows = table.getRowModel().rows;
    const records = rows.map((row) => row.original);
    const leafCount = table.getAllLeafColumns().length;
    const showSkeleton = loading && rows.length === 0;

    return (
        <div className={cn("overflow-hidden rounded-lg border border-solid border-border bg-card", className)}>
            <Table aria-busy={loading || undefined} className={cn(loading && rows.length > 0 && "opacity-60")}>
                {caption && <TableCaption className="sr-only">{caption}</TableCaption>}
                <TableHeader className="bg-muted/60">
                    {headerGroups.map((group, groupIndex) => (
                        <TableRow key={group.id} className="hover:bg-transparent">
                            {group.headers.map((header) => {
                                const columnId = header.column.id;
                                if (spanned.has(columnId)) return null;

                                // Grouped headers: an ungrouped column spans every header row, like antd.
                                let rowSpan;
                                let source = header;
                                if (header.isPlaceholder) {
                                    spanned.add(columnId);
                                    rowSpan = headerGroups.length - groupIndex;
                                    source = leafHeaders.find((leaf) => leaf.column.id === columnId) ?? header;
                                }

                                const meta = source.column.columnDef.meta ?? {};
                                const direction = source.column.getIsSorted();
                                const isGroup = source.subHeaders.length > 0;
                                return (
                                    <TableHead
                                        key={header.id}
                                        colSpan={header.colSpan}
                                        rowSpan={rowSpan}
                                        aria-sort={
                                            source.column.getCanSort()
                                                ? direction === "asc"
                                                    ? "ascending"
                                                    : direction === "desc"
                                                      ? "descending"
                                                      : "none"
                                                : undefined
                                        }
                                        style={meta.width ? { width: meta.width } : undefined}
                                        className={cn(
                                            "h-11 px-4 text-xs font-semibold text-muted-foreground",
                                            ALIGN[meta.align ?? (isGroup ? "center" : undefined)],
                                            meta.headerClassName
                                        )}
                                    >
                                        <HeaderContent table={table} header={source} />
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {showSkeleton &&
                        Array.from({ length: Math.min(pagination?.pageSize ?? 5, 5) }, (_, index) => (
                            <TableRow key={`skeleton-${index}`} className="hover:bg-transparent">
                                {Array.from({ length: leafCount }, (_, cellIndex) => (
                                    <TableCell key={cellIndex} className="px-4 py-3">
                                        <Skeleton className="h-4 w-full max-w-40" />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}

                    {!showSkeleton && rows.length === 0 && (
                        <TableRow className="hover:bg-transparent">
                            <TableCell colSpan={leafCount} className="h-28 text-center text-muted-foreground">
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}

                    {rows.map((row) => (
                        <TableRow
                            key={row.id}
                            onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                            onMouseEnter={onRowHover ? () => onRowHover(row.original) : undefined}
                            onMouseLeave={onRowHover ? () => onRowHover(null) : undefined}
                            className={cn(onRowClick && "cursor-pointer", rowClassName?.(row.original))}
                        >
                            {row.getAllCells().map((cell) => {
                                const meta = cell.column.columnDef.meta ?? {};
                                // meta.rowSpan(index, records) merges cells down; 0 means covered by the row above.
                                const rowSpan = meta.rowSpan?.(row.index, records);
                                if (rowSpan === 0) return null;
                                return (
                                    <TableCell
                                        key={cell.id}
                                        rowSpan={rowSpan > 1 ? rowSpan : undefined}
                                        className={cn(
                                            "px-4 py-3 whitespace-normal",
                                            rowSpan > 1 && "border-r align-top",
                                            ALIGN[meta.align],
                                            meta.className
                                        )}
                                    >
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {pagination && (
                <Pagination table={table} rowCount={rowCount} pageSizeOptions={pageSizeOptions} loading={loading} />
            )}
        </div>
    );
}
