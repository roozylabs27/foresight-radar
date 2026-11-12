# Production Workflow Matrix — Foresight Radar

Phase 2 of the [production readiness validation](production-readiness-report.md), 2026-11-12. Each journey is mapped from entry point to completion against the code on `main` (`9ae54b4`), which is what production runs.

**Validation status columns:**
- **HTTP test**: an automated PHPUnit test walks the path (SQLite, fake LLM).
- **Prod render**: the page or endpoint answered 200 on production, read-only, signed in as Developer (ProNext Hub). Checked 2026-11-12 on the test exercise "[SMOKE TEST] Elektrifikasi 2030" (`29629c2b-…`).
- **Prod E2E**: the journey was carried out by hand in the browser on production. This happens in Phase 4.

Roles: **coordinator** = `manage-scenario` plus the exercise policy (creator, owner or admin role); **contributor** = `view-scenario` plus participant role contributor (anyone before participants are set); **viewer** = `view-scenario` only (e.g. `bod`); **org admin** = `manage-organization` / `manage-members`; **operator** = platform role without an organisation, acting in a picked workspace.

## A. The nine-step exercise journey

| Step | Actor | Precondition (gate) | Main actions → state transition | Entities | Authorisation | Failure / edge cases handled | HTTP test | Prod render | Prod E2E |
|---|---|---|---|---|---|---|---|---|---|
| 1 Key focal issue | coordinator | none | create exercise (`draft`) → confirm → **G1**, status `active`; AI focal suggestion (proposal) | `scenario_planning_projects` | `manage-scenario` + project policy | editing a confirmed focal issue withdraws G1–G4 and flags the framework | yes | 200 (overview) | not yet |
| 2 Driving forces | coordinator | G1 | add library forces, mark predetermined, AI candidates (proposals) → agree list → **G2** | `project_driving_forces` → `driving_forces` (firm library) | as above | removing or changing an included force withdraws G3/G4/G5 | yes | 200 `/driving-forces` | not yet |
| 3 Scores and critical uncertainties | coordinator; contributors give own scores | G2 | own scores, consensus, AI score proposals → agree scores **G3**; propose uncertainties with rationale, approve as axis A and B → **G4** (2) | `force_assessments`, `critical_uncertainties` | as above; own score: contributor | strong cross-impact between axes needs acknowledgement; withdrawing an axis withdraws G5 and flags Steps 7–9 (U6) | yes | 200 `/assessment`, `/uncertainties` | not yet |
| 4 Framework | coordinator | G4 | create 2×2 from the two axes, edit poles (must differ), name quadrants, AI pole critique → approve **G5** | `scenarios` (framework), `scenario_quadrants` | as above | reopen withdraws G5 and flags Steps 7–9 (U6) | yes | 200 `/framework` | not yet |
| 5 Conditions | coordinator | G5 | ≥ 2 conditions per quadrant (pole expression or force behaviour), AI drafts → step done | `scenario_conditions` (+ force link) | as above | similar conditions across quadrants and predetermined candidates flagged; a change flags relying responses and the quadrant narrative | yes | 200 `/conditions` | not yet |
| 6 Narratives | coordinator | G5 | generate (AI, cites offered signals only; template without AI), edit, review, approve four quadrants → **G6** | `scenario_quadrants`, `scenario_citations`, `scenario_narrative_revisions` | as above (exercise policy since U3) | invalid citations reject the AI output; edits and regeneration keep a revision; regeneration flags that quadrant's implications | yes | 200 `/narratives` | not yet |
| 7 Challenges & opportunities | coordinator | G5 (G6 not required for drafting) | ≥ 1 opportunity and 1 challenge per quadrant, linked to forces and conditions; AI drafts → step done | `scenario_implications`, `implication_links` | as above | generic / similar items flagged; edits clear their own flag and flag relying responses | yes | 200 `/implications` | not yet |
| 8 Strategic responses | coordinator | G5 to draft; **G6 to approve** (U6) | response with rationale, fit per quadrant, links to implications and conditions; AI proposals → approve **G7** | `strategic_responses`, `response_implications`, `response_conditions`, `response_scenarios` | as above | rationale and four fits required; G6 required; flagged when implications, conditions, framework or a threshold change | yes | 200 `/responses` | not yet |
| 9 Early warning | coordinator (indicators); contributors (observations) | G5 | indicator with direction per quadrant and optional numeric threshold; observations (manual, AI match or World Bank, confirmed by a person); relevance per quadrant | `early_warning_indicators`, `indicator_scenarios`, `indicator_observations`, `indicator_threshold_events` | indicator: coordinator; observation: contributor | server decides numeric crossings (U4) → framework and responses flagged; relevance is a count, not a probability (U5) | yes | 200 `/indicators` | not yet |
| — Supporting: preferred future, backcasting, compare, report | coordinator / viewer | G5 | pick preferred future (PF decision), milestones; compare four futures; printable report | `preferred_futures`, `backcasting_milestones` | coordinator / view | report shows only agreed content | yes | 200 each | not yet |

**Connection check (Steps 1→9 as one workflow):** each step reads the previous step's records (forces → scores → axes → framework → quadrants → conditions → narratives → implications → responses → indicators), gates G1–G7 are logged in `project_decisions`, and upstream changes flag downstream work with a reason (U6). Verified at HTTP level by `ForesightExerciseJourneyTest`; on production only read-only so far.

### Gaps found while mapping (to be confirmed in Phase 4)

| Ref | Observation | Where |
|---|---|---|
| W-1 | **No closing state.** The exercise statuses `monitoring` and `archived` exist (`ScenarioPlanningProject::STATUSES`) but no screen sets them; after Step 9 the overview offers no next state. | `ProjectForm.jsx` has no status field; `ScenarioPlanningProjectService::update` accepts `status` |
| W-2 | **Steps can complete out of order.** On the production test exercise the stepper shows Step 9 `done` while Steps 5, 7 and 8 are not, because Step 9 is done with one accepted indicator rated in four quadrants. A template skeleton alone also makes Step 6 `in_progress`. The "next action" correctly points to Step 5. | `ScenarioPlanningProjectService::steps()`; production overview of `29629c2b-…` |
| W-3 | **Two places for the same idea.** A quadrant narrative carries its own `implications` and `early_indicators` text, separate from the Step 7 items and the Step 9 indicators; the legacy `/scenarios` page can still create stand-alone scenarios outside any exercise (hidden from the menu since U3). | `scenario_quadrants.implications`, `.early_indicators`; `ScenarioController::store` |
| W-4 | **Narratives in the production test exercise came from the template**, although a Gemini key is active for the signed-in user (configured 2026-11-11 07:20). Possibly generated before the key existed; to be checked by generating with AI in Phase 4. | `/narratives` props: `provider: template` |

## B. Supporting workflows

| Journey | Actor | Entry | Steps → completion | Authorisation | Failure cases handled | HTTP test | Prod E2E |
|---|---|---|---|---|---|---|---|
| Sign in / sign out | any | `/login` | credentials → dashboard | rate limited | wrong password, suspended or deleted organisation → denied | yes | not yet |
| Password reset | guest | `/forgot-password` | request link → set new password | signed link | invalid email, expired token | yes | not yet |
| Invitation | org admin → invitee | Settings → Members | invite (email, role) → invitee accepts → member of the organisation | `manage-members`; signed, expiring link | expired, used or cancelled invitation; email already registered | yes | not yet |
| Members and roles | org admin | Settings → Members | change role, remove member | `manage-members`; cannot remove self | privileged roles protected | yes | not yet |
| Organisation and AI provider | org admin / user | Settings → Organisasi, Mesin AI | set organisation key or personal key, test, activate | `manage-organization` / own account | invalid key test fails without saving as active | yes | not yet |
| Dimensions and environments | org admin | Settings → Dimensi & Lingkungan | add, rename, reorder, archive; delete only when unused | `manage-organization` | in-use delete refused (row lock since U2) | yes | not yet |
| Signal ingestion and review | analyst | Signals | ingest source (AI only) → queued extraction → pending signals → review (accept/reject, classify, link to force) | `create-signal`, `view-signal` | no provider → refused; AI failure → source failed + retry; lost job → retryable (U2) | yes | not yet |
| Driving force library | analyst → approver | Driving Force, Approval Items, Closed Items | create force with rating → lifecycle stages → approve → close/reopen; merge duplicates | `*-driving-force`, `*-approval-items` | duplicates merged with staleness for exercises | yes | not yet |
| Reports | viewer | Dashboard, Prioritizing, Radar, Registered List (+ export), Cross-Impact, Knowledge Graph | FIRM or one Key Focal Issue (`?scope=`) | per-report permissions; scope needs `view-scenario` | other organisation's scope → 404 | yes | not yet |
| User management | operator (platform roles) | User Management | create user in the picked workspace, edit, delete | `*-user` (developer, super-admin) | no workspace → refused (AUD-14) | yes | not yet |
| Operator workspace | operator | header switcher | pick organisation → act inside it | platform role | no workspace → read nothing, write nothing | yes | not yet |
