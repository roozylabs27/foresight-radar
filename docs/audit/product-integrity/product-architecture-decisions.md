# Product Architecture Decisions — Foresight Radar

Decisions taken by the product owner on 2026-11-13 after the [product integrity audit](product-feature-integrity-report.md). Each one updates the [roadmap](product-gap-roadmap.md). Nothing is implemented yet: implementation is the next stage ("targeted remediation"), with acceptance criteria and tests.

| # | Topic | Decision | Consequence for the roadmap | Residual risk / follow-up |
|---|---|---|---|---|
| PAD-1 | FIRM report period (PI-07) | **"Rated in the period"**: a force appears when it was assessed during the selected period | Roadmap #8 becomes a requirement. The filter must use the latest rating activity (`driving_force_ratings.updated_at` or the newest rating history entry), not `created_at`: a re-rating updates the same row (`RatingUrgencyController::create` :63-71). Each report states "dinilai dalam periode" | Firm forces not re-assessed within the period drop out, by design. The page label must make that clear |
| PAD-2 | Legacy `/scenarios` (PI-08) | **Read-only archive** | Roadmap #5: remove `scenarios.store`, `generate` and the write routes; keep viewing existing stand-alone scenarios; move `tests/e2e/scenarios.e2e.ts` to the exercise | Existing stand-alone scenarios stay visible but cannot change |
| PAD-3 | Dashboard tabs duplicating Prioritizing, Radar and Registered List (C-04, C-05) | **Keep as they are** | Removed from roadmap #15. The duplication is accepted | Tabs and pages use different permissions (`view-dashboard` vs `view-prioritizing` etc.), so a dashboard-only user may meet a refused tab. Check the role permissions when the next change touches the Dashboard |
| PAD-4 | Five governance pages for the firm force lifecycle (A-09) | **One lifecycle board** with stage filters; the actions per stage stay the same | Roadmap #15 (governance part): the Governance menu becomes one item (plus User Management for operators). Approver/closer history (#22) fits naturally on the board | Bigger frontend change (M–L). Existing e2e lifecycle specs need updating |
| PAD-5 | Knowledge Graph (A-14) | **Simplify**: a status filter (default accepted signals and approved forces), FIRM or exercise scope, a period filter, its own permission, the page split into components | New roadmap item #24 (M). Follows the 150-line page rule (AUD-24) | A new permission needs a migration and role assignment; it starts with the roles that hold `view-foresight-radar` today, so nobody loses access |

## Still open

Decisions still to take before or during remediation:
- **Participant roles (PI-12):** should a coordinator be able to run gates?
- **Firm forces in an exercise (PI-06):** only APPROVED, or any status with a reason?
- **Analyst tenant role (PI-16).**
- **Organisation provisioning (PI-16):** operator screen or command.
