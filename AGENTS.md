# Foresight Radar — Agent Guidelines & Harness Engineering Standards

## 1. Project Purpose & Technology Stack

Foresight Radar is a multi-tenant strategic foresight and decision intelligence platform. It helps an organisation collect evidence of change (signals), assess driving forces, and run scenario planning exercises along a nine-step method: from the key focal issue to strategic responses and early warning monitoring.

- **Backend:** Laravel 11 (PHP 8.3), MySQL 8.0, Spatie Laravel Permission. The queue uses the `database` driver (worker container `laravel_queue`, scheduler container `laravel_scheduler`) and the cache uses `file`. Redis is configured in `.env.example` but has no service in `docker-compose.yml`.
- **Frontend:** React 19, Inertia.js 2.0, **JavaScript/JSX** (`resources/js` is `.jsx`/`.js`; `jsconfig.json` provides the `@/*` alias; there is no TypeScript source and no typecheck step since 2026-11-12), Tailwind CSS v4 (`@tailwindcss/vite`), Radix UI (`radix-ui`), Lucide React, Ziggy (`route()`). **ESLint 9** (`eslint.config.js`: JS recommended, React, rules of hooks as errors; hook dependencies and jsx-a11y as warnings) runs with `npm run lint`; it must report 0 errors.
- **Design System:** `shadcn/ui` patterns located in `resources/js/Components/ui/`
- **LLM Gateway:** Multi-provider (`anthropic`, OpenAI-compatible: OpenAI, Gemini, OpenRouter) with an organisation key and a personal key, background queues, and a run record per AI call (`AiGenerationRun`: purpose, provider, model, prompt version). AI output is always a proposal a person accepts. Signal extraction of external sources is AI-only. The keyword-rule extractor runs only with `SIGNAL_EXTRACTION_DRIVER=heuristic` (tests and offline work).

### Domain concepts (read before changing code)

- **Tenant / organisation:** every business table is tenant-owned through `App\Traits\BelongsToTenant` (global `TenantScope`). Validate related ids with `App\Support\TenantRule::exists()` (or `activeDimension()`), never a plain `exists:` rule. Queued jobs run through `RunInTenantContext`. A job's `failed()` handler runs outside that context, so it must not create rows.
- **Dimensions / environments:** each organisation owns them (Settings → Dimensi & Lingkungan). The default taxonomy (`App\Support\DimensionTaxonomy`: Macro and Mezzo, 11 categories) is seeded per organisation. A `code` never changes because prompts and seeds use it. A dimension in use is archived, not deleted.
- **Firm library vs exercise:** the firm library holds driving forces and their ratings (`DrivingForceRating`: horizon, impact, uncertainty, priority, status action), plus signals as evidence. An **exercise** (`ScenarioPlanningProject`, a Key Focal Issue) selects forces and scores them separately (`ForceAssessment` consensus).
  - The reports (Dashboard, Radar, Prioritizing, Registered List) show **FIRM** by default, or one **Key Focal Issue** via `?scope=<exercise uuid>` (`App\Support\ReportScope`).
- **Nine steps and gates:**
  1. Focal issue (G1)
  2. Driving forces (G2)
  3. Scores (G3) and critical uncertainties (G4)
  4. Framework (G5)
  5. Conditions
  6. Narratives (G6)
  7. Challenges & opportunities
  8. Strategic responses (G7)
  9. Early warning indicators and observations

  Gate decisions are logged in `project_decisions`. `StalenessService` flags downstream work with a reason when upstream changes; it never deletes.
- **Signals vs driving forces:** a signal is an observation with a verbatim quote from a source; a driving force is the broader influence. Signals support forces; they are not interchangeable.

> [!IMPORTANT]
> **No Ant Design:** The codebase was migrated from Ant Design v5 to `shadcn/ui` + `radix-ui` + `Tailwind CSS v4`. Never introduce Ant Design (`antd`) components, imports, or tokens.

---

## 2. Architecture Boundaries & Key Entry Points

The repository enforces strict module separation:

- **Routing & RBAC:** `routes/web.php` (Inertia & web endpoints with Spatie permission middleware), `routes/auth.php`
- **Controllers:** `app/Http/Controllers/` (lean controllers; delegate business logic to services; use `HandlesApiErrors` envelope)
- **Domain Services:** `app/Services/`
  - `SignalExtractionService` & `EvidenceVerifier`: Ingestion, background LLM extraction, verbatim quote verification
  - `DrivingForceService`: 6-stage lifecycle, 1-to-1 ratings, priority classification
  - `CausalLoopService` & `CrossImpactService`: Cross-impact matrix and cycle detection (reinforcing vs balancing)
  - `ScenarioNarrativeService`: 2x2 matrix generation, pessimistic queue locking (`lockForUpdate`), citation veracity
  - Scenario planning: `ScenarioPlanningProjectService` (steps, gates, decisions), `ProjectDrivingForceService`, `ProjectAssessmentService`, `CriticalUncertaintyService`, `ScenarioFrameworkService`, `ScenarioConditionService`, `ScenarioImplicationService`, `StrategicResponseService`, `EarlyWarningIndicatorService`, `ExternalIndicatorService`, `BackcastingService`, `StalenessService`
  - Reporting: `ForesightReportingService` (FIRM and Key Focal Issue datasets), `ReasoningEngineService`, `ScenarioSimulationService`; taxonomy: `DimensionService`
- **Persistence & Policies:** `app/Models/` (UUIDs, soft deletes), `app/Policies/` (`DrivingForcePolicy`, `ScenarioPlanningProjectPolicy`, `ScenarioPolicy`, `UserPolicy`)
- **Background Jobs:** `app/Jobs/` (`ExtractSignals`, `GenerateScenarioNarrative`, the `Suggest*` AI jobs, `MatchSignals`, `FetchIndicatorSource`; all with `RunInTenantContext` and `ShouldBeUnique`)
- **Frontend Views:** `resources/js/Pages/` (Inertia React pages), `resources/js/Components/ui/` (Radix UI / Tailwind primitives)
- **Detailed Documentation (tracked in git since 2026-11-11; decks, PDFs and screenshots stay local):**
  - `docs/product/`: methodology, feature specification, development roadmap
  - `docs/audit/`: audits
  - `docs/architecture/`
  - `docs/guidelines/`

  The latest audit and the remediation backlog are in `docs/audit/2026-11-11/` (start with its `README.md`).

---

## 3. Harness Scripts & Standard Verification Commands

Standardized repository entry points for human developers and AI coding agents:

```bash
# 1. Safe Environment Initialization
./init.sh                     # Verifies runtime dependencies, Docker, .env, and baseline smoke check

# 2. Standard Quality Verification Engine
./verify.sh                   # Pint --test, Larastan level 5, PHPUnit (all in laravel_app), ESLint, Vite build, npm audit
./verify.sh --quick           # Same without the full PHPUnit suite
./verify.sh --e2e             # Adds the browser e2e smoke test

# 3. Direct Docker / Host Verification Shortcuts
docker exec laravel_app composer verify   # Unified contract (Pint, Larastan Level 5, PHPUnit)
npm run build                             # Production frontend bundle
npm audit --audit-level=critical          # Security quality gate
npm run test:e2e:smoke                    # Browser e2e smoke (login page)
```

Notes from practice:
- **Reload after PHP changes:** reload PHP-FPM after changing PHP (`docker exec laravel_app sh -c 'kill -USR2 1'`). Otherwise OPcache keeps serving old routes, and Ziggy reports "route not in the route list". Restart `laravel_queue` after changing job or service code.
- **Run artisan in the container:** run artisan and tests inside `laravel_app`. Never run two PHPUnit processes at once, because they share the test database.
- **Browser e2e runner:** `e2e` / `@e2e-dev/web` (`e2e.config.ts`) is an agentic runner that drives the browser through a model. Running it costs AI tokens and needs `ADMIN_PASSWORD` / `DEV_PASSWORD`. It is not Playwright.
- **CI:** `.github/workflows/ci.yml` runs Pint, Larastan and PHPUnit on MySQL, then npm audit, ESLint and build, and only the e2e smoke test.

---

## 4. Mandatory Session Protocols (WalkingLabs Principles)

To maintain continuity across long-running tasks and agent context resets:

### At Session Start (Mandatory 6 Steps):
1. **Confirm Repository:** Verify the current directory is the Foresight Radar root with `pwd`.
2. **Read State:** Read [`PROGRESS.md`](file:///c:/laragon/www/foresight-radar/PROGRESS.md) to understand current verified state, blockers, and priorities.
3. **Review Context:** Check [`feature_list.json`](file:///c:/laragon/www/foresight-radar/feature_list.json) and relevant project specs in `docs/audit/`.
4. **Inspect Git Status:** Run `git status` and `git log -5 --oneline` to inspect recent commits and working-tree status.
5. **Run Baseline Verification:** Execute `./init.sh` to confirm containers and environment integrity.
6. **Resume Work:** Pick up the highest-priority unfinished task identified in `PROGRESS.md`. If a baseline check is failing, localize and fix the failure before starting new features.

### At Session Wrapup (Mandatory 5 Steps):
1. **Update `PROGRESS.md`:** Record completed work, in-progress items, and updated session log entry.
2. **Record Verification Evidence:** Record actual commands executed, durations, and outputs (never mark unexecuted checks as passed).
3. **Record Next Actions & Blockers:** Identify unresolved blockers, risks, and the single highest-priority next actionable task.
4. **Update Feature Register:** If feature status changed, update [`feature_list.json`](file:///c:/laragon/www/foresight-radar/feature_list.json) (`not_started`, `in_progress`, `blocked`, `passing`).
5. **Review Git Diff:** Inspect `git diff` and leave the repository in a clean, reviewable, resumable state with conventional commits.

---

## 5. Engineering Standards & Agent Skills

This repository uses workflow skills from `.agents/skills/` following a 3-phase methodology:

1. **Phase 1: Define & Plan**:
   - `spec-driven-development` & `planning-and-task-breakdown`: Define explicit acceptance criteria before modifying code.
   - `interview-me`: Clarify underspecified requirements rather than making silent assumptions.
   - `source-driven-development`: Ground implementations in official documentation (Laravel 11, React 19, Inertia 2, Radix UI, Tailwind v4).
2. **Phase 2: Build & Harden**:
   - `incremental-implementation`: Build in thin, vertical slices. Test each slice before proceeding.
   - `frontend-ui-engineering`: Maintain design system fidelity, WCAG 2.1 AA accessibility, and responsive layouts.
   - `security-and-hardening`: Enforce Spatie RBAC gates, sanitize inputs, parameterize queries, and guard against OWASP Top 10 risks.
   - `doubt-driven-development`: Adversarially review authorization checks and concurrency boundaries.
3. **Phase 3: Verify & Review**:
   - `test-driven-development`: Guard changes with regression tests (PHPUnit for domain logic and HTTP flows; the agentic `e2e` runner for browser journeys).
   - `debugging-and-error-recovery`: Reproduce -> Localize -> Fix -> Guard.
   - `code-review-and-quality`: Validate changes against Pint and Larastan Level 5.
   - `code-simplification`: Remove dead code and unnecessary complexity without altering verified behavior.

---

## 6. Security, Git Safety & Scope Discipline

- **Data Security:** Never log or commit `.env` credentials, API keys, or production data. Always use `FakeLlmClient` fixtures for automated tests.
- **Git Branching:** Work strictly on the designated feature or `development` branch. Never switch branches or push to remotes without explicit user instruction.
- **Atomic Commits:** Use Conventional Commits (`feat(...)`, `fix(...)`, `chore(...)`, `test(...)`, `ci(...)`). Keep commits atomic and focused.
- **Working Tree:** Preserve existing uncommitted user files. Never run `git reset --hard` or `git clean -fd` without authorization.

---

## 7. Definition of Done (DoD)

A task is complete only when:
1. **Unified Quality Contract:** `docker exec laravel_app composer verify` passes cleanly:
   - 0 Laravel Pint code style issues.
   - 0 Larastan Level 5 static analysis errors.
   - 100% PHPUnit test suite passing.
2. **Frontend Build:** `npm run build` succeeds cleanly without bundle or Rollup resolution errors.
3. **Security Quality Gate:** `npm audit --audit-level=critical` reports 0 critical vulnerabilities.
4. **Regression Guard:** Any bug fix or behavioral change includes an automated test verifying reproduction and edge cases.
5. **State Persisted:** `PROGRESS.md` and `feature_list.json` reflect the updated state, verification evidence, and next steps.
6. **Clean Working Tree:** `git status` shows all changes are committed cleanly.

---

## 8. Frontend Engineering Standards: Clean Architecture & Atomic Design

All frontend development strictly adheres to the combined **Clean Architecture** and **Atomic Design** system documented in `docs/architecture/frontend-clean-atomic-architecture.md`:

1. **Clean Architecture Separation:**
   - **Domain (`resources/js/domain/`):** Pure JavaScript data and business calculations (zero React/DOM/Inertia imports).
   - **Application Hooks (`resources/js/hooks/`):** Custom workflow hooks managing user interactions and Inertia actions (`router.post`, `useForm`).
   - **Infrastructure Adapters (`resources/js/lib/`):** Utilities (`cn`, formatting, third-party adapters).
   - **Presentation (`resources/js/Components/`, `resources/js/Pages/`):** Murni presentasional.

2. **Atomic Design System:**
   - **Atoms (`Components/ui/`):** Indivisible shadcn/radix primitives (`Button`, `Input`, `Badge`, `Switch`, `Skeleton`).
   - **Molecules (`Components/molecules/` or composite primitives):** 2+ atoms combined (`FormField`, `ThemeToggle`, `SearchInput`).
   - **Organisms (`Components/{Feature}/`):** Complex UI blocks (`RadarChart`, `ScenarioMatrix`, `DataTable`).
   - **Templates (`Layouts/`):** Shell layouts (`AuthenticatedLayout`, `AuthSplitLayout`).
   - **Pages (`Pages/`):** Lean orchestrator components (< 150 lines). Dilarang menulis inline math/business logic di dalam Page. Several older pages are well above this limit (e.g. `Signals/Index.jsx`, `Visualization/KnowledgeGraph.jsx`). New pages must meet the rule, and an older page should be split when it is substantially changed (backlog AUD-24).

---

## 9. Iconography Standards: Anti-AI Slop (Zero Sparkles Policy)

To preserve executive credibility and strategic intelligence rigor:

- **Strictly Prohibit `SparklesIcon` / `Sparkles`:** Never introduce `SparklesIcon`, `Sparkles`, or magic wand symbols anywhere in the platform.
- **Enforce Semantic Domain Iconography:**
  - `CpuIcon` for machine intelligence, processor engine badges, and active AI model state.
  - `BotIcon` for synthetic narrative generation and automated assistant workflows.
  - `SlidersHorizontalIcon` for AI settings, model selection, and parameter configuration.
  - `ShieldCheckIcon` for resilience metrics and governance indicators.
  - `TrendingUpIcon` for growth, market acceleration, and trend indicators.
  - `AlertCircleIcon` for operational failures, connection errors, and validation alerts.
- **Rule Reference:** Follow `docs/guidelines/iconography-standards.md` on all future feature and UI iterations.

---

## 10. Backend Coding Standards: Explicit `Auth` Facade (Zero `auth()` Helper Policy)

To ensure static analysis clarity (Larastan Level 5), deterministic IDE auto-completion, and clean refactoring:

- **Strictly Prohibit `auth()->user()`, `auth()->id()`, `auth()->check()`:** Never use the global `auth()` helper function anywhere in the backend codebase.
- **Enforce Explicit `Illuminate\Support\Facades\Auth`:**
  - Always use `Auth::user()`, `Auth::id()`, `Auth::check()`.
  - In controller actions or middleware where `Request $request` is injected, `$request->user()` is also permitted.
- **Rule Scope:** Controllers, Services, Models, Middleware, Policies, Observers, Jobs, and Feature/Unit Tests.

---

## 11. Verification Standards: Chrome DevTools MCP over `curl` (Zero `curl` Verification Policy)

To ensure genuine browser rendering, real JavaScript runtime execution, DOM state verification, and console error detection:

- **Strictly Prohibit `curl` for Web / UI Verification:** Never use `curl`, `Invoke-WebRequest`, or raw terminal HTTP scrapers to test web routes, user journeys, UI behavior, or page rendering.
- **Enforce a real browser:**
  - Use Chrome DevTools MCP (`list_pages`, `new_page`, `navigate_page`, `evaluate_script`, `take_screenshot`, `list_console_messages`, `list_network_requests`), or the agent host's built-in browser tools with the same capabilities.
  - Verify live DOM elements, evaluate runtime state, ensure 0 console errors or warnings, inspect network responses, and capture visual screenshots for proof of work.
- **Rule Scope:** All developer and AI coding agent workflows testing UI, layouts, interactive graphs, forms, settings, and web endpoints.




