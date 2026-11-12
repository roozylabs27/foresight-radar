# Production Security & Tenant Isolation Report — Foresight Radar

Phase 3 of the [production readiness validation](production-readiness-report.md), 2026-11-12, against code `main` `9ae54b4`.

## Environments used and why

| Environment | What was tested | Limits |
|---|---|---|
| **Production** https://foresight-radar.id, signed in by the product owner as **Developer** (organisation ProNext Hub) | Requests that must be refused (CSRF, unknown ids), data exposure in page props and JSON, user list scope | Only one account and, apparently, one organisation exist (the user list holds 3 accounts). **Cross-organisation and role boundaries cannot be tested on production.** |
| **Local Docker** (same code; MySQL 8, nginx, queue), in the built-in browser | Cross-organisation attempts, viewer role, admin role, duplicate and concurrent submissions | Local QA accounts `qa-admin-a` (admin, org 1), `qa-bod-a` (viewer `bod`, org 1), `qa-admin-b` (admin, org 2), created and deleted in this phase; passwords generated and kept in a git-ignored file, removed afterwards |

The automated suites back this up: `TenantIsolationJourneyTest`, `tests/Feature/Security/*` (role escalation, tenant-aware exists rules, operator workspace, queued job tenant context, information disclosure, rate limiting), full suite 626 passed.

## Scenarios

| ID | Env | Role / org | Steps | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| SEC-01 | prod | Developer / ProNext | POST `/locale` without the XSRF header | refused before running | 419 | PASS |
| SEC-02 | prod | Developer / ProNext | GET unknown or malformed ids: exercise, narratives, indicators, signal source, report and export scopes | 404, no data, no error page | 404 on all 8 | PASS |
| SEC-03 | prod | Developer / ProNext | Scan props of 8 pages and the user list for password, token, key or secret fields | none, keys masked | none; AI key shown masked (`AQ.A••••Ha9w`); user rows: id, date, name, email, role | PASS |
| SEC-04 | prod | — | Session cookie readable by JavaScript? | no | only `XSRF-TOKEN` readable | PASS |
| SEC-05 | local | admin / org 1 | GET 14 pages of org 2's exercise (all steps, state, report, compare, backcasting) | 404 | 404 on all 14 | PASS |
| SEC-06 | local | admin / org 1 | Writes on org 2: update, confirm, delete exercise, framework, generate narratives, add participant, acknowledge, update dimension, delete environment, change member role, remove member | 404, nothing changed | 404 on all 11; org 2 data unchanged | PASS |
| SEC-07 | local | admin / org 1 | Report data and export scoped to org 2's exercise | 404 | 404 (dashboard, prioritizing, export) | PASS |
| SEC-08 | local | admin / org 1 | Create a driving force with org 2's dimension id | refused | 422 | PASS |
| SEC-09 | local | admin / org 1 | Add an org 2 user as participant / owner of an org 1 exercise | refused | 422 "Peserta / Pemilik yang dipilih tidak valid." | PASS |
| SEC-10 | local | viewer `bod` / org 1 | Open the exercise overview and all step pages | no management actions | no action buttons on the overview; `canManage` false on all 10 step pages | PASS |
| SEC-11 | local | viewer `bod` / org 1 | Send 11 management writes directly (edit, delete, reopen, generate, approve narrative, acknowledge, add response, ingest signal, add dimension, invite member, user list) | 403 | 403 on all 11 | PASS |
| SEC-12 | local | viewer `bod` / org 1 | Record an observation on Step 9 | product decision | **accepted** (200) and deletable by the viewer; while an exercise has no participants anyone with `view-scenario` may record (R2.3) | see PRD-10 |
| SEC-13 | local | admin / org 1 | Send "create exercise" twice at the same moment | one exercise | **two exercises** | FAIL → PRD-12 |
| SEC-14 | local | admin / org 1 | Send G1 confirm three times at the same moment | one decision | **"G1 approved" logged twice**; state consistent | FAIL → PRD-11 |
| SEC-15 | local | admin / org 1 | Send G1 confirm twice one after the other | one decision | one | PASS |
| SEC-16 | code | — | AI paths: tenant scope bypasses, key selection | none; only own or own-organisation key | no `withoutGlobalScopes` in AI/prompt code; `LlmClientResolver` uses the user's key or the user's organisation key; AI output stays a proposal (earlier audit, tests) | PASS (review) |
| SEC-17 | prod | — | Security headers | HSTS, nosniff, frame, referrer, CSP | CSP and Permissions-Policy missing; versions disclosed | FAIL → PRD-03 |

## Coverage of the brief's tenant-isolation areas

| Area | Evidence | Status |
|---|---|---|
| CRUD on exercises and every step | SEC-05, SEC-06, `TenantIsolationJourneyTest` | PASS (local + HTTP tests) |
| Search / filtering, dashboard aggregation, reports, exports | SEC-07, `ReportScopeTest` | PASS (local + HTTP tests) |
| Background jobs incl. failure handlers | `QueuedJobTenantContextTest` (12 failure handlers outside context) | PASS (HTTP/unit tests) |
| AI processing and extraction | SEC-16, `LlmSignalExtractionTest`, journeys with fake LLM | PASS (review + tests); live AI in Phase 4 |
| User management and invitations | `UserManagementTenantTest`, `TenantManagementTest`, SEC-06 (members) | PASS (tests + local) |
| Notifications | not reviewed in this phase | NOT TESTED |
| File storage / document access | the app stores no uploaded files (sources are text); not reviewed further | NOT TESTED |
| Production cross-organisation | needs a second organisation in production | BLOCKED |

## Residual risks

- Cross-organisation isolation is proven locally and by tests, **not on production** (one organisation there).
- Concurrency: gate decisions without row locks (PRD-11); no server-side duplicate guard on creates (PRD-12).
- Viewer write on Step 9 is by design but undecided as policy (PRD-10).
