# Foresight Radar — Audit 2026-11-11: Executive Summary

Scope: the full product, methodology, architecture, security and harness audit asked for on 2026-11-11, run against branch `development` at `1a89e0c` (PR #16 "per-organization dimensions and exercise report scoping" merged; `origin/main` at `ea80d76`). This is a stage of a staged remediation, not a rewrite. The goal it serves: **one organisation can carry one strategic foresight exercise from Step 1 to Step 9, with data, evidence, approval and monitoring connected.**

## Documents

| File | Content |
|---|---|
| [technical-audit.md](technical-audit.md) | Every finding (AUD-xx) with severity, category, evidence, remediation, acceptance criteria, verification |
| [methodology-alignment.md](methodology-alignment.md) | The nine steps: entities, links, gates, stale propagation, gaps; revalidation of previous findings A–E |
| [tenant-isolation-data-integrity.md](tenant-isolation-data-integrity.md) | Tenant ownership, IDOR and injection review, dimension migration risk assessment and safe deployment procedure |
| [feature-gap-matrix.md](feature-gap-matrix.md) | Implemented / partial / missing / disconnected / unverified, per capability and step |
| [remediation-backlog.md](remediation-backlog.md) | Prioritised units U1–U7 and the P2/P3 backlog with dependencies |
| [test-verification-report.md](test-verification-report.md) | Commands run, results, gaps, what was not run |

Earlier audits in [`docs/audit/`](../) (security, performance, reliability, capability matrix, scenario planning feature matrix) remain valid background; this audit references rather than repeats them.

## Current maturity

- **Methodology coverage:** all nine steps have entities, screens and gates (G1–G7) inside an exercise, except **Step 6**, whose narratives are still written on the older global `/scenarios` page. The links between steps exist (implications ↔ conditions/forces, responses ↔ implications/conditions, indicators ↔ uncertainties/forces), but some feedback loops are not closed.
- **Evidence and AI:** AI outputs are proposals that a person accepts; narrative citations are validated against offered signals; signal extraction is AI-only with failure and retry. Prompt version, provider and model are recorded per AI run.
- **Multi-tenancy:** no P0 found. All tenant data models are tenant-scoped, validation uses tenant-scoped rules, every queued job runs in its tenant's context; dimensions are now per organisation.
- **Verification baseline (on `1a89e0c`):** Pint PASS (463 files), Larastan no errors, PHPUnit 583 passed (6,678 assertions). No automated test walks one exercise from Step 1 to Step 9; the browser e2e suite covers none of the scenario planning pages.

**Assessment:** the platform is a capable, connected scenario planning workbench with sound tenancy, but it is **not yet a closed loop**: monitoring (Step 9) cannot trigger reassessment reliably because threshold crossings are asserted by the user, scenario relevance is a raw count, and upstream changes do not reach Steps 7–9. Production readiness also depends on operational items outside the code (AI provider configured per organisation, a staged run of the dimension migration, monitoring).

## Major risks (by priority)

1. **P1 — Step 6 outside the exercise (AUD-01..03).** Narratives are generated and approved on a global page, authorised by scenario creator instead of exercise coordinator, and keep no revision history. **Fixed in U3.**
2. **P1 — Early warning thresholds are not computed (AUD-04, 05).** The client ticks "crossed"; World Bank and AI-matched observations never cross; no event record.
3. **P1 — Monitoring and change do not close the loop (AUD-06, 07).** Relevance counts observations without recency or explanation; axis or implication changes do not flag implications, responses or indicators.
4. **P1 — Operational reliability (AUD-09, 11).** Sources and indicator fetches can stay QUEUED forever; the dimension migration's `down()` is unsafe. **Fixed in U2.**
5. **P2 — Harness truth (AUD-17..20).** `docs/` was not in git (fixed in this unit), AGENTS.md described the stack wrongly (fixed), typecheck covers nothing, no lint, two e2e specs targeted a route that does not exist (fixed), no end-to-end journey test.

## What this stage (U1) changed

Documentation and harness only: `docs/` tracked in git (binaries excluded), these audit documents, AGENTS.md corrected, PROGRESS.md header and `feature_list.json` refreshed, two broken e2e specs rewritten. No application behaviour changed. The remaining units U2–U7 are in [remediation-backlog.md](remediation-backlog.md); each stops for review.
