# Feature Gap Matrix (2026-11-11)

Status: **Implemented** (end to end, tested) · **Partial** · **Missing** · **Disconnected** (works but not tied into the workflow) · **Unverified** (exists, no test or runtime check). "Tested" means PHPUnit feature tests; browser e2e covers none of these unless stated.

## Strategic foresight (the exercise)

| Feature | Purpose / user | Input → output | Consumed by | Status | Gap |
|---|---|---|---|---|---|
| Key Focal Issue (Step 1) | Coordinator frames the question | focal question, purpose, target year, scope → exercise, G1 | every later step | Implemented | — |
| AI focal critique | Coordinator improves the question | exercise → critique + alternatives (proposal) | edit dialog | Implemented | — |
| Exercise driving forces (Step 2) | Team selects forces from the firm library | library forces → included forces, coverage, G2 | Steps 3, 5, 7, 9 | Implemented | — |
| AI candidate forces | Find forces in accepted signals | signals → candidates (proposal) | Step 2 | Implemented | — |
| Assessment & consensus (Step 3) | Score impact/uncertainty per exercise | individual scores → consensus, spread, G3 | uncertainties, KFI reports | Implemented | — |
| Critical uncertainties (Step 3) | Pick the two axes | forces, rationale → approved axes, G4 | framework, indicators | Implemented | — |
| Scenario framework (Step 4) | 2×2 with poles and names | axes → quadrants, G5 | Steps 5–9 | Implemented | no versioning beyond decision log |
| Pole rule checks + AI critique | Distinct, opposing poles | poles → warnings / critique (proposal) | framework | Implemented | — |
| Conditions (Step 5) | Describe each quadrant | quadrant, forces → conditions | narratives, responses | Implemented | — |
| Consistency review | Check a quadrant hangs together | quadrant → review (proposal) | compare | Implemented | — |
| Narratives (Step 6) | Story per quadrant with evidence | conditions, signals → narrative + citations, G6 | report, compare | **Implemented (U3)** | Step 6 page inside the exercise, exercise rights, revision history (AUD-01..03 fixed); stale propagation into Steps 7–9 still open (U6) |
| Challenges & opportunities (Step 7) | Implications per quadrant | quadrant, forces, conditions → implications | responses | Partial | no stale flag on upstream change (AUD-07) |
| Strategic responses (Step 8) | Actions with cross-scenario fit | implications, conditions → responses, fit, G7 | backcasting, report | Partial | not flagged when implications change; G7 independent of G6 (AUD-07, 08) |
| Early warning indicators (Step 9) | Monitor which future is unfolding | uncertainty/force, directions → indicators | relevance, report | Partial | thresholds not computed (AUD-04, 05) |
| Observations (manual, AI-matched, World Bank) | Evidence for indicators | values → observations (proposed / accepted) | relevance, staleness | Partial | crossing asserted by user; external never crosses |
| Scenario relevance | Which scenario is strengthening | accepted observations → per-quadrant count | indicators page | Partial | no recency, no explanation (AUD-06) |
| Sensing panel (weak signals, wild cards) | Faint signals beside indicators | classified accepted signals → list | indicators page | Implemented | — |
| Preferred future & backcasting | Plan back from the chosen future | quadrant, milestones → path | report | Implemented | — |
| Comparison & report | Read the exercise as a whole | everything → views, printable report | stakeholders | Implemented | report reads narratives from the same quadrants |
| Staleness banners | Show what needs review after change | upstream change → flags | every step page | Partial | does not reach Steps 7–9 (AUD-07) |

## Environmental intelligence

| Feature | Status | Gap |
|---|---|---|
| Source ingestion → AI signal extraction (verbatim quotes checked) | Implemented | AI-only since 2026-11-11; lost QUEUED/PROCESSING becomes retryable and the claim is atomic since U2 (AUD-09, AUD-10) |
| Signal review (accept / link / reject), classification (horizon, wild card, AI proposal) | Implemented | — |
| Firm driving force library + rating workflow (time horizon, urgency, status action, approval, closed items) | Implemented | Separate "firm" track, by design (FIRM reports) |
| Per-organisation dimensions & environments (settings) | Implemented | migration `down()` refuses lossy merges and delete locks the row since U2 (AUD-11, AUD-12) |
| Cross-impact matrix, knowledge graph | Implemented | firm-wide only, not per exercise (by design today) |

## Decision support & reporting

| Feature | Status | Gap |
|---|---|---|
| Dashboard Overview (growth/resilience indices, EDA) | Implemented | FIRM or Key Focal Issue (2026-11-11); month trends firm-only by design |
| What-if simulation | Implemented | scoped by FIRM / KFI |
| Prioritizing, Foresight Radar, Registered List (+ export) | Implemented | duplicated as Dashboard tabs (AUD-21, decision) |
| Legacy global Scenarios page | Disconnected | hidden from the sidebar in U3, reachable by URL (AUD-25) |

## Platform

| Feature | Status | Gap |
|---|---|---|
| Multi-tenancy, invitations, members, organisation AI key & quota | Implemented | `User` not tenant-scoped for platform roles (AUD-14) |
| User management page | Disconnected | no navigation entry (AUD-23) |
| Settings → Mesin AI (`settings.llm.*`) | Implemented | duplicate `settings.ai.*` routes unused (AUD-22) |

## Unverified or untested paths

- Browser e2e: none of the scenario planning pages, Signals, Settings (AUD-19).
- Whole Step 1→9 journey: no automated test (AUD-20).
- Dimension migration on a production-like multi-tenant dataset: only the two-organisation dev database (AUD-11).
- Frontend: no lint; typecheck checks no JSX (AUD-18).
