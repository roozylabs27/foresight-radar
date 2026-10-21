# Foresight Radar — Agent Guidelines & Engineering Standards

## 1. Technology Stack & UI Architecture

This application is built with:
- **Backend:** Laravel 10 (PHP 8.2), MySQL 8.0, Redis, Spatie Laravel Permission
- **Frontend:** React 18, Inertia.js, Tailwind CSS v4 (`@tailwindcss/vite`), Radix UI (`radix-ui`), Lucide React
- **Design System:** `shadcn/ui` component patterns located in `resources/js/Components/ui/`

> [!IMPORTANT]
> **No Ant Design:** The codebase was migrated from Ant Design v5 to `shadcn/ui` + `radix-ui` + `Tailwind CSS v4`. Never introduce Ant Design (`antd`) components, imports, or tokens.

## 2. Repository Entry Points

- **Routes:** `routes/web.php` (web & Inertia endpoints), `routes/auth.php` (authentication)
- **Controllers:** `app/Http/Controllers/`
- **Services:** `app/Services/` (core domain reasoning, causal loops, LLM integrations)
- **Policies:** `app/Policies/` (`DrivingForcePolicy`, `ScenarioPolicy`, `UserPolicy`)
- **Models:** `app/Models/` (Eloquent entities with soft deletes & UUID identifiers)
- **Frontend Pages:** `resources/js/Pages/` (Inertia React page components)
- **UI Components:** `resources/js/Components/ui/` (Radix UI / Tailwind primitives)
- **Seeders & Data:** `database/seeders/` and `database/data/*.json` (synthetic domain data)

## 3. Verification Commands & Contracts

Every change must be validated against the standard quality contract before claiming completion:

```bash
# 1. Unified Verification Contract (Pint, Larastan Level 5, PHPUnit)
docker exec laravel_app composer verify

# 2. Frontend Production Build (host machine)
npm run build

# 3. Security Quality Gate
npm audit --audit-level=critical
```

## 4. Engineering Standards & Agent Skills

This repository integrates engineering skills from [Addy Osmani's agent-skills](https://skills.addy.ie/) located in `.agents/skills/` and `.agents/references/`.

For every implementation task, agents must follow the corresponding workflow skill:

1. **Phase 1: Define & Plan**:
   - `spec-driven-development` & `planning-and-task-breakdown`: Define explicit acceptance criteria and break work into verifiable units before coding.
   - `interview-me`: Clarify underspecified requirements with the user before assuming.
   - `source-driven-development`: Ground decisions in authoritative documentation (Laravel 10, Inertia.js, React 18, Radix UI, Tailwind v4).
   - `using-agent-skills`: Surface assumptions early, manage confusion actively, resist sycophancy, and maintain strict scope discipline.

2. **Phase 2: Build & Harden**:
   - `incremental-implementation`: Build in thin, vertical slices. Test and verify each slice before moving to the next.
   - `frontend-ui-engineering`: Maintain design system fidelity, WCAG 2.1 AA accessibility, responsive layouts, and token-based theming.
   - `api-and-interface-design`: Enforce typed/validated contracts, clean error structures, and predictable responses.
   - `security-and-hardening`: Enforce RBAC authorization gates, sanitize inputs, parameterize queries, and follow OWASP Top 10 guidelines.
   - `doubt-driven-development`: Adversarially cross-examine non-trivial architectural and security decisions.

3. **Phase 3: Verify & Review**:
   - `test-driven-development`: Verify behavior using automated tests before claiming completion.
   - `debugging-and-error-recovery`: Reproduce -> Localize -> Fix -> Guard with regression tests.
   - `code-review-and-quality`: Conduct multi-axis verification before proposing PRs or marking tasks done.
   - `code-simplification`: Remove accidental complexity and dead branches while strictly preserving behavior.

## 5. Definition of Done (DoD)

A task is complete only when:
1. **Quality Contract:** `composer verify` passes in `laravel_app` (0 Pint style issues, 0 Larastan Level 5 errors, 0 test failures).
2. **Build:** `npm run build` succeeds cleanly without warnings treated as errors or failed assets.
3. **Security:** No critical security vulnerabilities introduced; authorization policies enforced on models.
4. **Regression Guard:** Any fixed defect includes an automated regression test covering reproduction and edge cases.
5. **Scope Discipline:** Working tree diff contains only intentional, reviewable changes related to the task.
