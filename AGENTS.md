# Foresight Radar — Agent Guidelines & Harness Engineering Standards

## 1. Project Purpose & Technology Stack

Foresight Radar is an executive intelligence and strategic foresight platform designed to ingest signals of change, evaluate driving forces, detect systems-dynamics causal loops, and simulate 2x2 scenario planning matrices.

- **Backend:** Laravel 11 (PHP 8.3), MySQL 8.0, Redis, Spatie Laravel Permission
- **Frontend:** React 19, Inertia.js 2.0, TypeScript, Tailwind CSS v4 (`@tailwindcss/vite`), Radix UI (`radix-ui`), Lucide React
- **Design System:** `shadcn/ui` patterns located in `resources/js/Components/ui/`
- **LLM Gateway:** Multi-provider (`anthropic`, OpenAI-compatible) with background queues and deterministic offline fallbacks

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
- **Persistence & Policies:** `app/Models/` (UUIDs, soft deletes), `app/Policies/` (`DrivingForcePolicy`, `ScenarioPolicy`, `UserPolicy`)
- **Background Jobs:** `app/Jobs/` (`ExtractSignals`, `GenerateScenarioNarrative` with `ShouldBeUnique` contract)
- **Frontend Views:** `resources/js/Pages/` (Inertia React pages), `resources/js/Components/ui/` (Radix UI / Tailwind primitives)
- **Detailed Documentation:** In-depth architecture specs are located in `docs/audit/` and `docs/product/`.

---

## 3. Harness Scripts & Standard Verification Commands

Standardized repository entry points for human developers and AI coding agents:

```bash
# 1. Safe Environment Initialization
./init.sh                     # Verifies runtime dependencies, Docker, .env, and baseline smoke check

# 2. Standard Quality Verification Engine
./verify.sh                   # Runs Pint, Larastan Level 5, PHPUnit, Vite Build, NPM Audit
./verify.sh --quick           # Runs Pint, Larastan, Vite Build, and NPM Audit (skips full PHPUnit suite)
./verify.sh --e2e             # Runs standard suite + Playwright headless E2E smoke tests

# 3. Direct Docker / Host Verification Shortcuts
docker exec laravel_app composer verify   # Unified contract (Pint, Larastan Level 5, PHPUnit)
npm run build                             # Production frontend bundle
npm audit --audit-level=critical          # Security quality gate
npm run test:e2e:smoke                    # Headless E2E smoke test
```

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
   - `source-driven-development`: Ground implementations in official documentation (Laravel 10, React 18, Radix UI, Tailwind v4).
2. **Phase 2: Build & Harden**:
   - `incremental-implementation`: Build in thin, vertical slices. Test each slice before proceeding.
   - `frontend-ui-engineering`: Maintain design system fidelity, WCAG 2.1 AA accessibility, and responsive layouts.
   - `security-and-hardening`: Enforce Spatie RBAC gates, sanitize inputs, parameterize queries, and guard against OWASP Top 10 risks.
   - `doubt-driven-development`: Adversarially review authorization checks and concurrency boundaries.
3. **Phase 3: Verify & Review**:
   - `test-driven-development`: Guard changes with regression tests (PHPUnit for domain logic, Playwright for E2E).
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
   - **Domain & Types (`resources/js/types/`, `resources/js/domain/`):** Pure TypeScript types, interfaces, and business logic calculations (zero React/DOM/Inertia imports).
   - **Application Hooks (`resources/js/hooks/`):** Custom workflow hooks managing user interactions and Inertia actions (`router.post`, `useForm`).
   - **Infrastructure Adapters (`resources/js/lib/`):** Utilities (`cn`, formatting, third-party adapters).
   - **Presentation (`resources/js/Components/`, `resources/js/Pages/`):** Murni presentasional.

2. **Atomic Design System:**
   - **Atoms (`Components/ui/`):** Indivisible shadcn/radix primitives (`Button`, `Input`, `Badge`, `Switch`, `Skeleton`).
   - **Molecules (`Components/molecules/` or composite primitives):** 2+ atoms combined (`FormField`, `ThemeToggle`, `SearchInput`).
   - **Organisms (`Components/{Feature}/`):** Complex UI blocks (`RadarChart`, `ScenarioMatrix`, `DataTable`).
   - **Templates (`Layouts/`):** Shell layouts (`AuthenticatedLayout`, `AuthSplitLayout`).
   - **Pages (`Pages/`):** Lean orchestrator components (< 150 lines). Dilarang menulis inline math/business logic di dalam Page.

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


