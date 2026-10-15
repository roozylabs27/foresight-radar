import { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import qs from "qs";
import { toast } from "sonner";

/**
 * Server-side pagination, sorting and filtering for <DataTable>, matching the
 * existing `*.fetch-data` endpoints (Laravel ResourceCollection with `data` and
 * `meta.current_page/per_page/total`).
 *
 *   const table = useServerTable({
 *       url: route("driving-force.fetch-data"),
 *       params: { date, search, dimension },
 *   });
 *   <DataTable columns={columns} {...table.tableProps} />
 *
 * `params` are the page's filters; changing them goes back to page 1. The query
 * carries both `page`/`results` and the antd-era `pagination[current|pageSize]`
 * the services read, plus `sortField` and `sortOrder` ("ascend" | "descend").
 */
export function useServerTable({ url, params = {}, initialPageSize = 10, initialSorting = [] }) {
    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: initialPageSize });
    const [sorting, setSorting] = useState(initialSorting);
    const [rows, setRows] = useState([]);
    const [rowCount, setRowCount] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [reloadKey, setReloadKey] = useState(0);

    const paramsKey = qs.stringify(params);
    const lastParamsKey = useRef(paramsKey);
    const requestId = useRef(0);

    // A filter change resets to the first page before fetching.
    if (lastParamsKey.current !== paramsKey) {
        lastParamsKey.current = paramsKey;
        if (pagination.pageIndex !== 0) setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    }

    const sortKey = JSON.stringify(sorting);

    useEffect(() => {
        const id = ++requestId.current;
        const controller = new AbortController();
        const sort = sorting[0];
        const query = {
            ...params,
            page: pagination.pageIndex + 1,
            results: pagination.pageSize,
            pagination: { current: pagination.pageIndex + 1, pageSize: pagination.pageSize },
            ...(sort && { sortField: sort.id, sortOrder: sort.desc ? "descend" : "ascend" }),
        };

        setLoading(true);
        axios
            .get(`${url}?${qs.stringify(query)}`, { signal: controller.signal })
            .then((response) => {
                if (id !== requestId.current) return;
                const { data = [], meta = {} } = response.data ?? {};
                setRows(data);
                setRowCount(meta.total ?? data.length);
                setError(null);

                // The server clamps out-of-range pages; follow it so the pager stays honest.
                const serverIndex = (meta.current_page ?? pagination.pageIndex + 1) - 1;
                const serverSize = Number(meta.per_page ?? pagination.pageSize);
                if (serverIndex !== pagination.pageIndex || serverSize !== pagination.pageSize) {
                    setPagination({ pageIndex: serverIndex, pageSize: serverSize });
                }
            })
            .catch((err) => {
                if (axios.isCancel(err) || id !== requestId.current) return;
                setError(err);
                toast.error("Gagal memuat data tabel.", {
                    description: err.response?.data?.message ?? err.message,
                });
            })
            .finally(() => {
                if (id === requestId.current) setLoading(false);
            });

        return () => controller.abort();
        // params is tracked through paramsKey, sorting through sortKey.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, paramsKey, pagination.pageIndex, pagination.pageSize, sortKey, reloadKey]);

    const reload = useCallback(() => setReloadKey((key) => key + 1), []);

    const handleSortingChange = useCallback((updater) => {
        setSorting(updater);
        setPagination((prev) => (prev.pageIndex === 0 ? prev : { ...prev, pageIndex: 0 }));
    }, []);

    return {
        rows,
        rowCount,
        pagination,
        setPagination,
        sorting,
        setSorting: handleSortingChange,
        loading,
        error,
        reload,
        tableProps: {
            data: rows,
            rowCount,
            pagination,
            onPaginationChange: setPagination,
            sorting,
            onSortingChange: handleSortingChange,
            loading,
        },
    };
}
