# UI/UX Discovery and Enhancement Audit Implementation Plan — Foresight Radar

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Conduct an empirical, evidence-based UI/UX Discovery and Enhancement Audit of the Foresight Radar web application across design tokens, page inventory, rendered desktop/mobile UI, user workflows, and accessibility, generating 7 comprehensive audit documents in `docs/ui-audit/`.

**Architecture:** Systematic multi-phase audit inspecting Laravel 10 / Inertia.js / React 18 / Ant Design 5 frontend code and running real browser inspections via Chrome DevTools MCP. Document findings in 7 dedicated markdown reports and establish a prioritized enhancement roadmap.

**Tech Stack:** React 18, Inertia.js, Ant Design v5 (`antd` 5.13.2), ECharts, Canvas/SVG, Vite 5, Tailwind CSS / custom CSS, Chrome DevTools MCP.

## Global Constraints

- Audit phase is strictly read-only regarding production application code: do not introduce arbitrary breaking design changes or refactor functional code during the audit.
- Every finding must be grounded in empirical evidence: exact file paths, line numbers, CSS selectors, or DevTools runtime measurements.
- Follow conventional commits format with author `roozylabs <roozylabs@gmail.com>` when committing documentation deliverables.
- Deliverables must be saved in `docs/ui-audit/`.
- All automated tests (`php artisan test`) and Vite builds (`npm run build`) must remain 100% passing.

---

### Task 1: Inspect the Existing Design System (`CURRENT_DESIGN_SYSTEM.md`)

**Files:**
- Create: `docs/ui-audit/CURRENT_DESIGN_SYSTEM.md`
- Inspect:
  - `resources/js/Layouts/AuthenticatedLayout.jsx:195-204`
  - `resources/js/Pages/Auth/Login.jsx:87-95`
  - `resources/css/app.css:1-22`
  - `resources/css/additional.css:1-169`
  - `resources/js/Components/`

**Interfaces:**
- Produces: Design system baseline documentation, token inventory, and CSS inheritance hierarchy.

- [ ] **Step 1: Extract and audit theme tokens from ConfigProvider and CSS**
  Analyze `colorPrimary`, `borderRadius`, `fontFamily`, and hardcoded color literals (`#1677ff`, `#4caf50`, `#b63232`, `#e39b54`, `#f6ffed`, `#fffbe6`, `#fff1f0`).
- [ ] **Step 2: Inspect typography, spacing, shadows, and component overrides**
  Evaluate heading hierarchy, body typography, `.radar-card`, `.radar-table-row`, and Ant Design component style layers.
- [ ] **Step 3: Author `docs/ui-audit/CURRENT_DESIGN_SYSTEM.md`**
  Write detailed teardown covering Tokens, Typography, Colors, Components, and Style Architecture.
- [ ] **Step 4: Commit deliverable**
  `git add docs/ui-audit/CURRENT_DESIGN_SYSTEM.md && git commit -m "docs(ui-audit): add current design system architecture audit"`

---

### Task 2: Build Complete Page Inventory (`PAGE_INVENTORY.md`)

**Files:**
- Create: `docs/ui-audit/PAGE_INVENTORY.md`
- Inspect:
  - All 15 page routes in `resources/js/Pages/` and `routes/web.php`.

**Interfaces:**
- Consumes: Task 1 Design System Tokens.
- Produces: Complete inventory covering persona, user goals, critical information, primary/secondary actions, usability risks, and responsive considerations for all 15 routes.

- [ ] **Step 1: Analyze routes, controllers, and page components**
  Map out Login, Register, Dashboard, Signals, Driving Force, Time Horizon, Rating of Urgency, Status of Action, Approval Items, Closed Items, Foresight Radar, Prioritizing, Registered List, User Management, and Error states.
- [ ] **Step 2: Document user personas and core tasks per page**
  Define strategic analyst vs decision maker vs administrator interaction patterns.
- [ ] **Step 3: Author `docs/ui-audit/PAGE_INVENTORY.md`**
  Write structured Markdown tables and narrative profiles for each page.
- [ ] **Step 4: Commit deliverable**
  `git add docs/ui-audit/PAGE_INVENTORY.md && git commit -m "docs(ui-audit): add complete 15-page UI inventory and workflow profiles"`

---

### Task 3: Rendered UI & Workflow Audit (`UX_FINDINGS.md`)

**Files:**
- Create: `docs/ui-audit/UX_FINDINGS.md`
- Inspect:
  - Live browser rendering via `chrome-devtools-mcp` on `http://localhost:8080`.
  - Driving forces, ratings, approvals, and report workflows.

**Interfaces:**
- Consumes: Task 2 Page Inventory.
- Produces: Concrete UX findings categorized with Finding ID, affected component, evidence, impact, and recommended improvements.

- [ ] **Step 1: Execute DevTools browser inspections across core workflows**
  Navigate through `/dashboard`, `/driving-force`, `/rating-urgency`, `/approval-items`, and `/visualization/foresight-radar`.
- [ ] **Step 2: Evaluate information hierarchy, feedback loops, and cognitive load**
  Assess table readability, modal form steppers, data density, and empty/loading states.
- [ ] **Step 3: Author `docs/ui-audit/UX_FINDINGS.md`**
  Structure findings with IDs (`UX-001`, `UX-002`, etc.) including evidence and severity rankings.
- [ ] **Step 4: Commit deliverable**
  `git add docs/ui-audit/UX_FINDINGS.md && git commit -m "docs(ui-audit): add empirical UX and workflow findings audit"`

---

### Task 4: Responsive Design & Accessibility Audit (`RESPONSIVENESS_ACCESSIBILITY.md`)

**Files:**
- Create: `docs/ui-audit/RESPONSIVENESS_ACCESSIBILITY.md`
- Inspect:
  - Emulated Desktop (1440x900) and Mobile (375x812) viewports via `chrome-devtools-mcp`.
  - DOM accessibility trees, color contrast, keyboard focus, and ARIA attributes.

**Interfaces:**
- Consumes: Task 3 Rendered UI observations.
- Produces: Responsive breakpoint matrix, WCAG 2.1 AA accessibility evaluation, and keyboard navigation audit.

- [ ] **Step 1: Audit responsive layout behavior across breakpoints**
  Evaluate Sider collapse, table horizontal scrolling, modal fitting, and chart canvas scaling.
- [ ] **Step 2: Measure color contrast and focus rings**
  Check text vs background contrast for tags (`.custom-tag`, badges, warning alerts) and keyboard navigation (`tabindex`, focus visible).
- [ ] **Step 3: Author `docs/ui-audit/RESPONSIVENESS_ACCESSIBILITY.md`**
  Document responsive issues (`RESP-001`...) and accessibility violations (`A11Y-001`...).
- [ ] **Step 4: Commit deliverable**
  `git add docs/ui-audit/RESPONSIVENESS_ACCESSIBILITY.md && git commit -m "docs(ui-audit): add responsiveness and WCAG accessibility audit"`

---

### Task 5: Design System Inconsistency Audit (`DESIGN_CONSISTENCY.md`)

**Files:**
- Create: `docs/ui-audit/DESIGN_CONSISTENCY.md`
- Inspect:
  - Cross-component styles in `resources/js/Pages/**/*.jsx` and `resources/js/Components/*.jsx`.

**Interfaces:**
- Consumes: Task 1 Design System Tokens & Task 3 UX Findings.
- Produces: Discrepancy analysis matrix across buttons, tables, filters, confirmations, and alerts.

- [ ] **Step 1: Map all button variants and inline style overrides**
  Identify split usage between standard Antd buttons, `.custom-green-button`, and hardcoded inline styles.
- [ ] **Step 2: Compare table pagination, density, and filter implementations**
  Highlight differences between Driving Force table, Registered List table, and User Management table.
- [ ] **Step 3: Author `docs/ui-audit/DESIGN_CONSISTENCY.md`**
  Detail inconsistencies with recommendations for unified components.
- [ ] **Step 4: Commit deliverable**
  `git add docs/ui-audit/DESIGN_CONSISTENCY.md && git commit -m "docs(ui-audit): add design system consistency and discrepancy audit"`

---

### Task 6: Strategic UI/UX Architecture Recommendations (`REDESIGN_RECOMMENDATIONS.md`)

**Files:**
- Create: `docs/ui-audit/REDESIGN_RECOMMENDATIONS.md`

**Interfaces:**
- Consumes: Tasks 1-5 findings and inconsistency analyses.
- Produces: Architectural frontend specifications for unified layouts, shared domain components, and streamlined foresight workflows.

- [ ] **Step 1: Formulate centralized theme and component abstraction models**
  Specify design token structure, shared `<DataTableWrapper>`, `<StatusBadge>`, and `<FilterToolbar>`.
- [ ] **Step 2: Redesign key workflow touchpoints**
  Detail signal triage streamlining, multi-step rating stepper UX, and interactive radar drilldowns.
- [ ] **Step 3: Author `docs/ui-audit/REDESIGN_RECOMMENDATIONS.md`**
  Include structural mockups, component API contracts, and user journey enhancements.
- [ ] **Step 4: Commit deliverable**
  `git add docs/ui-audit/REDESIGN_RECOMMENDATIONS.md && git commit -m "docs(ui-audit): add comprehensive UI/UX redesign and architecture recommendations"`

---

### Task 7: Prioritized UI Enhancement Roadmap (`UI_ENHANCEMENT_ROADMAP.md`)

**Files:**
- Create: `docs/ui-audit/UI_ENHANCEMENT_ROADMAP.md`

**Interfaces:**
- Consumes: Tasks 1-6 deliverables.
- Produces: Multi-sprint phased implementation roadmap with sizing, priority, and acceptance criteria.

- [ ] **Step 1: Prioritize enhancements into 4 actionable phases**
  - Phase 1: Quick Wins & Foundation Fixes (Tokens, contrast, button cleanup).
  - Phase 2: Workflow & Usability Improvements (Steppers, table ergonomics, filter bar).
  - Phase 3: Mobile & Responsive Polish (Drawers, responsive charts, touch targets).
  - Phase 4: Advanced Visualizations & Analytics (Interactive drill-downs, export polish).
- [ ] **Step 2: Author `docs/ui-audit/UI_ENHANCEMENT_ROADMAP.md`**
  Define each ticket with Objective, Affected Files, Effort (Story Points), and Acceptance Criteria.
- [ ] **Step 3: Commit deliverable**
  `git add docs/ui-audit/UI_ENHANCEMENT_ROADMAP.md && git commit -m "docs(ui-audit): add phased UI/UX enhancement roadmap"`
- [ ] **Step 4: Verify test suite and build stability**
  Ensure working tree is clean and `npm run build` succeeds.
