# Foresight Radar: Performance Findings

**Assessment date:** 2026-10-09  
**Auditor roles:** Performance Engineer, Staff Software Engineer  
**Methodology:** Database schema inspection, ORM query analysis, build bundle analysis, automated streaming test verification

---

## 1. Executive Summary

This performance evaluation reviewed the data access tier, server memory consumption, caching architecture, and frontend asset delivery in Foresight Radar.

Key database bottlenecks previously identified have been addressed through dedicated composite indexes on `driving_forces` and `driving_force_ratings`, while memory exhaustion on dataset exports was resolved by transitioning to cursor-based streaming (`lazy(250)`). Ongoing performance concerns focus on unindexed `LIKE '%...'` wildcard searches across long text columns, lack of a caching tier for complex analytical aggregations, and large vendor JavaScript bundle weight.

---

## 2. Performance Findings Register

| ID | Severity | Category | Component | Title | Status |
|---|---|---|---|---|---|
| **PERF-001** | Medium | Memory / Throughput | `RegisteredListController` | Unbounded Memory in Dataset Export | Remediated (Verified) |
| **PERF-002** | Low | Frontend Bundling | `vite.config.js`, `manifest.json` | Vendor Chunking & Monolithic Dependencies | Partially Remediated |
| **PERF-003** | Medium | Database Indexing | Migrations, `DrivingForce` | Composite Performance Indexes | Remediated (Verified) |
| **PERF-004** | Medium | Query Execution | `DrivingForceService` | Full-Table Scans on Wildcard Description Searches | Unresolved |
| **PERF-005** | Medium | Caching | Visualization Controllers | Missing Response Cache on Analytics Endpoints | Unresolved |

---

## 3. Detailed Performance Analyses

### PERF-001: Memory-Safe Streaming for Bulk Data Export
* **Severity:** Medium
* **Target Files:**
  * [`app/Http/Controllers/RegisteredListController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php#L68-L108)
* **Initial Defect:** Calling `->get()` synchronously loaded all matching records and their relationships (`dimension`, `rating`, `user`) into PHP memory at once. At scale (thousands of driving forces), this resulted in `Allowed memory size exhausted` fatal crashes.
* **Current Remediation:**
  * Replaced synchronous memory hydration with a streamed JSON response using Eloquent's `lazy(250)` cursor chunking:
  ```php
  return response()->stream(function () use ($query) {
      echo '[';
      $first = true;
      foreach ($query->lazy(250) as $item) {
          if (!$first) { echo ','; }
          echo json_encode((new DrivingForceRatingResource($item))->resolve());
          $first = false;
      }
      echo ']';
  }, Response::HTTP_OK, ['Content-Type' => 'application/json']);
  ```
* **Verification Evidence:**
  * Test: `Tests\Feature\Performance\ExportStreamingTest`
  * Executed: `test_export_data_returns_streamed_json_array_compatible_with_resource` (PASS)
  * Executed: `test_export_data_returns_empty_json_array_when_no_records_exist` (PASS)
  * Executed: `test_export_data_streams_multiple_records_correctly` (PASS)

---

### PERF-002: Frontend Bundle Chunking and Asset Distribution
* **Severity:** Low
* **Target Files:**
  * [`vite.config.js`](file:///c:/laragon/www/foresight-radar/vite.config.js#L17-L23)
  * [`resources/js/app.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/app.jsx#L12)
* **Initial Defect:** Vite generated a single client bundle containing all vendor libraries (`antd`, `@ant-design/icons`, `echarts`, `react`, `dayjs`), exceeding 1 MB uncompressed and degrading First Contentful Paint (FCP).
* **Current Remediation:**
  * Added `manualChunks` in `vite.config.js` to isolate `echarts` and `zrender` into `_vendor-charts-DM4DWILg.js`.
  * Dynamic route loading via `resolvePageComponent` and `import.meta.glob('./Pages/**/*.jsx')` splits page components.
* **Residual Concern:** Ant Design and icon definitions remain in core shared chunks. Further vendor isolation (`vendor-react`, `vendor-antd`) should be introduced once circular dependency safeguards are established.

---

### PERF-003: Composite Database Indexes on High-Frequency Query Paths
* **Severity:** Medium
* **Target Files:**
  * Migration: [`database/migrations/2026_10_09_000002_add_performance_indexes_to_driving_forces_table.php`](file:///c:/laragon/www/foresight-radar/database/migrations/2026_10_09_000002_add_performance_indexes_to_driving_forces_table.php)
* **Initial Defect:** `driving_forces` lacked indexes on `status`, `created_at`, and `(dimension_id, status)`. Queries across all 6 service methods executed sequential scans and filesorts.
* **Current Remediation:**
  * Applied dedicated B-Tree indexes:
    * `idx_driving_forces_status` (`status`)
    * `idx_driving_forces_status_created` (`status`, `created_at`)
    * `idx_driving_forces_dim_status` (`dimension_id`, `status`)
    * `idx_driving_force_ratings_created_at` (`created_at`) on `driving_force_ratings`
* **Verification Evidence:**
  * Test: `Tests\Feature\Performance\DatabaseIndexTest`
  * Executed: `test_performance_indexes_exist_on_driving_forces_table` (PASS)
  * Executed: `test_performance_indexes_exist_on_driving_force_ratings_table` (PASS)

---

### PERF-004: Full-Table Scans on Wildcard Description Searches
* **Severity:** Medium
* **Target Files:**
  * [`app/Services/DrivingForceService.php`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L61-L62)
* **Defect Description:**
  * Search queries execute:
  ```php
  $sub->where('keyword', 'LIKE', $search . '%')
      ->orWhere('description', 'LIKE', $search . '%');
  ```
  * While `keyword` uses prefix matching (`LIKE $search . '%'`) which can leverage standard indexes, `description` is a `TEXT` column. Standard B-Tree indexes cannot index arbitrary text, forcing MySQL to read table rows sequentially.
* **Remediation:** Introduce a MySQL `FULLTEXT` index on `(keyword, description)` and use `WHERE MATCH(keyword, description) AGAINST(? IN BOOLEAN MODE)` for large datasets.

---

### PERF-005: Absence of Caching Tier on Strategic Analytics Endpoints
* **Severity:** Medium
* **Target Files:**
  * [`app/Http/Controllers/PrioritizingController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php#L30-L33)
  * [`app/Http/Controllers/ForesightRadarController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php#L30-L33)
* **Defect Description:**
  * Strategic visualizations query finalized driving forces (`status = 'CLOSED'`). Closed items change only when new driving forces are approved, but the endpoints recompute polar coordinates, priority groupings, and table collections on every browser visit.
  * `config/cache.php` uses `file` driver and lacks tagged caching keys for visualization data.
* **Remediation:**
  * Cache analytical responses keyed by filter parameters (`foresight_radar_{dimension}_{date_hash}`).
  * Clear visualization cache tags when a driving force is transitioned to `CLOSED` or deleted.
