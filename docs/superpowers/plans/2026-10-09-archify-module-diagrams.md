# Archify Module Feature Diagrams Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Author and generate interactive, verified standalone HTML diagrams using Archify for each core module/feature of Foresight Radar, backed by repository code evidence, and save them in `.archify/` (untracked by git).

**Architecture:** Each module will be visualized using the optimal Archify diagram mode (`lifecycle`, `sequence`, `dataflow`, `workflow`) matching its operational characteristics. Every diagram will be authored as typed JSON with concrete repository evidence (`evidence.sources`), validated against schemas, rendered to standalone HTML via `bin/archify.mjs finalize`, and validated through automated browser and provenance checks.

**Tech Stack:** Node.js, Archify CLI v3.0, Laravel 11, Inertia.js / React 18, Ant Design 5, MySQL.

## Global Constraints

- All generated JSON candidates and HTML outputs MUST reside under `.archify/module-<feature-name>/`.
- No `.archify/`, `.agents/`, or temporary diagram outputs may be committed to git (kept strictly untracked via `.gitignore`).
- Every diagram must pass `node .agents/skills/archify/bin/archify.mjs finalize <type> <candidate.json> <output.html> --repo-root . --quality showcase --json`.
- Evidence sources must link to real repository file paths and line ranges.
- Git working tree must remain clean (`nothing to commit, working tree clean`).

---

### Task 1: Driving Force Management & Review Lifecycle Diagram

**Diagram Mode:** `lifecycle`  
**Files:**
- Create: `.archify/module-driving-force/candidate.json`
- Output: `.archify/module-driving-force/driving-force-lifecycle.html`
- Source Evidence:
  - `app/Models/DrivingForce.php`
  - `app/Http/Controllers/DrivingForceController.php`
  - `app/Http/Controllers/StatusActionController.php`
  - `app/Http/Controllers/ApprovalController.php`
  - `app/Http/Controllers/ClosedItemsController.php`
  - `app/Models/ActionReason.php`

**Interfaces:**
- Consumes: Database schema for `driving_forces` status enum (`PENDING`, `REVIEWED`, `APPROVED`, `REJECTED`, `CLOSED`).
- Produces: Interactive HTML lifecycle diagram depicting state transitions, guard conditions, actor actions (Creator, PIC, Admin/BOD), and terminal states.

- [ ] **Step 1: Inspect DrivingForce model and status action controllers**
- [ ] **Step 2: Author `.archify/module-driving-force/candidate.json` with state definitions and transitions**
- [ ] **Step 3: Run `finalize` command to validate, deliver, and browser-check the lifecycle diagram**
- [ ] **Step 4: Verify receipt and output HTML visual fidelity**

---

### Task 2: Rating Urgency Assessment & Priority Calculation Sequence Diagram

**Diagram Mode:** `sequence`  
**Files:**
- Create: `.archify/module-rating-urgency/candidate.json`
- Output: `.archify/module-rating-urgency/rating-urgency-sequence.html`
- Source Evidence:
  - `app/Http/Controllers/RatingUrgencyController.php`
  - `app/Http/Controllers/TimeHorizonController.php`
  - `app/Models/DrivingForceRating.php`
  - `app/Models/Priority.php`
  - `app/Policies/RatingUrgencyPolicy.php`
  - `resources/js/Pages/Module/RatingUrgency/RegisteredList.jsx`

**Interfaces:**
- Consumes: Rating matrix logic (Impact 1-10, Uncertainty 1-10, Time Horizons 1-4, Priority High/Medium/Low thresholds >= 6).
- Produces: Interactive HTML sequence diagram showing the request-response lifecycle from React/Inertia through Policies, Controller, Model calculations, and DB updates.

- [ ] **Step 1: Inspect `RatingUrgencyController.php` and `RatingUrgencyTest.php`**
- [ ] **Step 2: Author `.archify/module-rating-urgency/candidate.json` with actors, participants, and message flows**
- [ ] **Step 3: Run `finalize` command to validate and deliver the sequence diagram**
- [ ] **Step 4: Verify receipt and output HTML visual fidelity**

---

### Task 3: Foresight Radar Visualization & Polar Anti-Collision Dataflow Diagram

**Diagram Mode:** `dataflow`  
**Files:**
- Create: `.archify/module-foresight-radar/candidate.json`
- Output: `.archify/module-foresight-radar/foresight-radar-dataflow.html`
- Source Evidence:
  - `app/Http/Controllers/ForesightRadarController.php`
  - `resources/js/Pages/Report/ForesightRadar.jsx`
  - `resources/js/Components/Radar.jsx`
  - `app/Models/Dimension.php`
  - `app/Models/Environment.php`

**Interfaces:**
- Consumes: Driving force rating data aggregation, horizon filters, dimension groupings, and polar coordinate transformations.
- Produces: Interactive HTML dataflow diagram showing data stages: Raw DB Records ➔ Filtering Pipeline ➔ Polar Angle/Radius Mapping ➔ Anti-Collision Radial Adjustment ➔ SVG Canvas Rendering.

- [ ] **Step 1: Inspect `ForesightRadarController.php` and `Radar.jsx` coordinate algorithms**
- [ ] **Step 2: Author `.archify/module-foresight-radar/candidate.json` with pipeline stages and transformations**
- [ ] **Step 3: Run `finalize` command to validate and deliver the dataflow diagram**
- [ ] **Step 4: Verify receipt and output HTML visual fidelity**

---

### Task 4: User Authentication & Role-Based Access Control (RBAC) Workflow Diagram

**Diagram Mode:** `workflow`  
**Files:**
- Create: `.archify/module-rbac-auth/candidate.json`
- Output: `.archify/module-rbac-auth/rbac-auth-workflow.html`
- Source Evidence:
  - `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
  - `app/Http/Controllers/Auth/RegisteredUserController.php`
  - `app/Http/Controllers/UserController.php`
  - `routes/web.php`
  - `app/Models/User.php`

**Interfaces:**
- Consumes: Spatie roles (`admin`, `bod`, `user`), middleware checks (`auth`, `verified`), and login throttle security.
- Produces: Interactive HTML workflow diagram illustrating user registration, authentication gates, role assignment, and route permission dispatching.

- [ ] **Step 1: Inspect auth controllers, middleware, and route gates**
- [ ] **Step 2: Author `.archify/module-rbac-auth/candidate.json` with workflow steps, gates, and decisions**
- [ ] **Step 3: Run `finalize` command to validate and deliver the workflow diagram**
- [ ] **Step 4: Verify receipt and output HTML visual fidelity**

---

### Task 5: Central Index Dashboard & Verification

**Files:**
- Create: `.archify/index.html` (Central hub linking all 4 module diagrams and the system architecture diagram)
- Verify: Full test suite (`php artisan test`) and git cleanliness (`git status`)

- [ ] **Step 1: Create an interactive index landing page linking to each generated module diagram**
- [ ] **Step 2: Run all Laravel tests to ensure zero regressions**
- [ ] **Step 3: Verify git status confirms working tree is clean and untracked artifacts remain ignored**
