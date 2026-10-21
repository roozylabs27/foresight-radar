<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Column sorting for the DataTable endpoints.
 *
 * The client sends `sortField` (the column id) and `sortOrder` ("ascend" |
 * "descend"). Only fields listed in `$sortable` are honoured, so request input
 * never reaches orderBy() as a raw column name. Each entry maps the column id
 * to a column name, or to a subquery builder for a related value (e.g. the
 * dimension name). Without a valid sort the query keeps its `$default` order.
 */
final class TableSort
{
    /**
     * @param  array<string, string|Builder>  $sortable
     * @param  callable(Builder): mixed  $default
     */
    public static function apply(Builder $query, array $params, array $sortable, callable $default): Builder
    {
        $field = $params['sortField'] ?? null;
        $order = $params['sortOrder'] ?? null;

        if (!is_string($field) || !array_key_exists($field, $sortable) || !in_array($order, ['ascend', 'descend'], true)) {
            $default($query);

            return $query;
        }

        $query->orderBy($sortable[$field], $order === 'descend' ? 'desc' : 'asc');

        // Stable pages: rows with equal values keep a fixed order across requests.
        $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');

        return $query;
    }
}
