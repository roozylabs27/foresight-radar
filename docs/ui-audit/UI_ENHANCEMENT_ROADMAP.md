# UI/UX Prioritized Enhancement Roadmap — Foresight Radar

> **Role:** Staff Software Engineer, Design Systems Lead & Senior Product Designer  
> **Repository:** `roozylabs27/foresight-radar`  
> **Audit Date:** October 2026  
> **Structure:** 4 Execution Phases (P0 Critical Barriers &rarr; P1 Workflow Upgrades &rarr; P2 Design System Foundation &rarr; P3 Visual Polish)

---

## 1. Executive Roadmap Overview

This engineering roadmap translates the findings of the UI/UX Discovery Audit into a prioritized, actionable implementation plan. Tasks are sequenced to maximize user impact while maintaining strict regression safety across all automated tests and production builds.

```
+---------------------------------------------------------------------------------------+
| SPRINT 1 (P0: Critical Usability & Accessibility)                                      |
| -> Fix Mobile Sider Pinching (RESP-001)                                               |
| -> Fix Login Accessibility Labels (A11Y-001)                                          |
| -> Resolve Text Contrast Violations (A11Y-002)                                        |
| -> Remove Filter Remnant on User Management                                           |
+---------------------------------------------------------------------------------------+
                                           |
                                           v
+---------------------------------------------------------------------------------------+
| SPRINT 2 (P1: Major Workflow & IA Upgrades)                                           |
| -> Integrated 4-Step Assessment Stepper Drawer (UX-003)                               |
| -> Executive Approval Table Overhaul with Sticky Actions (UX-008)                     |
| -> One-Click Signal Graduation Modal (UX-001)                                         |
| -> Transparent Urgency Scoring Rubrics (UX-005, UX-006)                                |
+---------------------------------------------------------------------------------------+
                                           |
                                           v
+---------------------------------------------------------------------------------------+
| SPRINT 3 (P2: Design System & Reusable Components)                                    |
| -> Centralized Token Theme Provider in app.jsx                                        |
| -> Extract Shared <PageHeader>, <FilterToolbar>, <StatusBadge>                        |
| -> Deprecate Redundant Breeze Scaffolding Components                                  |
| -> Purge Unused / Override CSS in additional.css                                      |
+---------------------------------------------------------------------------------------+
                                           |
                                           v
+---------------------------------------------------------------------------------------+
| SPRINT 4 (P3: Visual Polish & Delight)                                                |
| -> Bidirectional Foresight Radar Point-to-List Sync (UX-009)                          |
| -> Excel Export Progress Bar (UX-010)                                                 |
| -> Responsive Radar Canvas ViewBox Auto-scaling (RESP-004)                            |
| -> Micro-animations and Optimistic Row Editing Feedback                               |
+---------------------------------------------------------------------------------------+
```

---

## 2. Phase P0: Critical Usability & Accessibility Barriers (Sprint 1)

*Focus: Eliminate blocking accessibility violations and catastrophic mobile layout breakage.*

### Ticket P0-1: Responsive Mobile Navigation Drawer (`RESP-001`)
* **Priority:** P0 (Critical Usability)
* **Objective:** Prevent the collapsed 64px Sider from pinching mobile screens to 311px width.
* **Affected Files:**
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
  - `resources/css/app.css`
* **Implementation Summary:**
  - Configure `Sider` with `collapsedWidth={0}` on viewports &le; `768px`.
  - Add an Ant Design `<Drawer placement="left" open={mobileMenuOpen} onClose={...}>` rendering `<Menu>` for mobile users.
  - Wire the header hamburger button to toggle `mobileMenuOpen` on small viewports and `collapsed` on desktop viewports.
* **Effort:** 3 Story Points (4 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. On viewports &le; 768px, content area occupies 100% width.
  2. Clicking the hamburger button opens a full-height overlay drawer.
  3. No horizontal page scrolling occurs on 375px screens.

---

### Ticket P0-2: Form Label Accessibility Remediation (`A11Y-001`)
* **Priority:** P0 (Accessibility Compliance)
* **Objective:** Resolve WCAG 1.3.1 violations and eliminate DevTools console errors.
* **Affected Files:**
  - `resources/js/Pages/Auth/Login.jsx`
  - `resources/js/Pages/Auth/Register.jsx`
  - `resources/js/Pages/UserManagement/User/Form.jsx`
* **Implementation Summary:**
  - Refactor all `<Text>` label wrappers to use Ant Design's native `<Form.Item label="..." name="..." htmlFor="...">`.
  - Ensure input fields contain matching `id` attributes.
* **Effort:** 2 Story Points (2 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Zero `Incorrect use of <label for=FORM_ELEMENT>` warnings in browser console.
  2. Screen readers announce input names on focus.

---

### Ticket P0-3: WCAG 2.1 AA Color Contrast Remediation (`A11Y-002`)
* **Priority:** P0 (Accessibility Compliance)
* **Objective:** Ensure all text and interactive controls meet the 4.5:1 contrast threshold.
* **Affected Files:**
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
  - `resources/css/additional.css`
* **Implementation Summary:**
  - Darken secondary subtitle text from `#8c8c8c` to `#595959` (achieving 5.14:1 ratio).
  - Darken `.custom-green-button` background to `#2e7d32` (achieving 4.54:1 ratio against white text).
  - Darken `.custom-tag:hover` background to `#b25900`.
* **Effort:** 1 Story Point (2 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. All text elements pass automated axe-core / Lighthouse contrast audits.

---

### Ticket P0-4: Remove Filter Anomaly on User Management (`DESIGN-003`)
* **Priority:** P0 (Information Architecture)
* **Objective:** Remove irrelevant "Filter Analisis" date picker from the User Management admin page.
* **Affected Files:**
  - `resources/js/Pages/UserManagement/User/Table.jsx`
* **Implementation Summary:**
  - Remove `<DatePicker.RangePicker>` and associated date filter props.
  - Retain only the Search User input and Role Filter select.
* **Effort:** 1 Story Point (1 Hour)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. User Management table displays only relevant administrative search and role filters.

---

## 3. Phase P1: Major Workflow & Information Architecture Upgrades (Sprint 2)

*Focus: Maximize analyst throughput and simplify executive decision-making.*

### Ticket P1-1: Integrated 4-Step Assessment Stepper (`UX-003`)
* **Priority:** P1 (Core Workflow)
* **Objective:** Allow single-session completion of Time Horizon, Urgency Scoring, and Status Action.
* **Affected Files:**
  - `resources/js/Pages/DrivingForce/Partials/AssessmentStepperDrawer.jsx` (New)
  - `resources/js/Pages/DrivingForce/Table.jsx`
  - `app/Http/Controllers/DrivingForceRatingController.php`
* **Implementation Summary:**
  - Build an Ant Design `<Drawer width={720}>` with `<Steps>`:
    - Step 1: Time Horizon Selection (Short / Mid / Long).
    - Step 2: Rating of Urgency (Uncertainty & Impact with dynamic priority calculation).
    - Step 3: Status of Action (Monitoring vs Decided Plan with rationale).
    - Step 4: Review & Submit.
  - Add "Lanjutkan Asesmen" button to Driving Force table rows.
* **Effort:** 8 Story Points (12 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Analysts can fully assess a Driving Force in under 2 minutes without visiting 4 separate pages.
  2. Assessments persist cleanly to backend API.

---

### Ticket P1-2: Executive Approval Table Overhaul (`UX-008`)
* **Priority:** P1 (Executive Ergonomics)
* **Objective:** Prevent decision fatigue by eliminating 12-column horizontal scrolling on approval items.
* **Affected Files:**
  - `resources/js/Pages/Approval/Table.jsx`
* **Implementation Summary:**
  - Pin the "Aksi Persetujuan" (Approve / Reject) column to the right (`fixed: 'right'`).
  - Collapse secondary columns (Full Description, Extended Rationale, Signals Provenance) into an expandable row (`expandable={{ expandedRowRender }}`).
  - Add batch "Approve Selected" toolbar button with confirmation.
* **Effort:** 5 Story Points (8 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Table fits within 1440px viewport without horizontal scrolling.
  2. Action buttons are permanently visible on screen.

---

### Ticket P1-3: One-Click Signal Graduation Modal (`UX-001`)
* **Priority:** P1 (Scanning Workflow)
* **Objective:** Enable seamless promotion of candidate signals into formal Driving Forces.
* **Affected Files:**
  - `resources/js/Pages/Signals/Index.jsx`
  - `resources/js/Pages/DrivingForce/Form.jsx`
* **Implementation Summary:**
  - Add "Promote to Driving Force" action on Candidate Review table rows in `/signals`.
  - Pass candidate signal ID, title, dimension, and summary to pre-fill the creation modal.
  - Link the candidate signal to the created driving force via pivot table.
* **Effort:** 5 Story Points (6 Hours)
* **Dependencies:** `signal_driving_force` database table.
* **Acceptance Criteria:**
  1. Clicking "Promote" opens pre-filled modal.
  2. Submitting immediately creates driving force and updates candidate status to "GRADUATED".

---

### Ticket P1-4: Transparent Urgency Scoring Rubrics (`UX-005`, `UX-006`)
* **Priority:** P1 (Domain Methodology)
* **Objective:** Demystify the 1-10 Uncertainty and Impact scoring and show live Priority calculation.
* **Affected Files:**
  - `resources/js/Pages/RatingUrgency/Table.jsx`
  - `resources/js/Utils/scoring.js` (New)
* **Implementation Summary:**
  - Create shared client-side calculation utility matching backend logic.
  - Display dynamic live Priority badge (`High` / `Medium` / `Low`) next to input sliders.
  - Add popover tooltips with qualitative anchor rubrics for levels 1 through 10.
* **Effort:** 3 Story Points (4 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Priority badge changes color immediately as user adjusts Uncertainty/Impact values.
  2. Rubric tooltips explain score criteria.

---

## 4. Phase P2: Design System & Reusable Components (Sprint 3)

*Focus: Unify codebase architecture, purge dead CSS, and eliminate duplicated filter toolbars.*

### Ticket P2-1: Centralized Theme Provider & Token Dictionary (`DS-001`)
* **Priority:** P2 (Maintainability)
* **Objective:** Establish global token governance at Inertia root.
* **Affected Files:**
  - `resources/js/Theme/theme.js` (New)
  - `resources/js/app.jsx`
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
  - `resources/js/Pages/Auth/Login.jsx`
* **Implementation Summary:**
  - Create centralized `theme.js` containing color tokens, typography, and component overrides.
  - Mount single `<ConfigProvider theme={appTheme}>` in `app.jsx`.
  - Remove duplicate ConfigProvider declarations in `AuthenticatedLayout.jsx` and `Login.jsx`.
* **Effort:** 3 Story Points (4 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Global theme tokens control all component styling across unauthenticated and authenticated states.

---

### Ticket P2-2: Extract Shared Domain Components (`DS-002`)
* **Priority:** P2 (Code Reusability)
* **Objective:** Eliminate code duplication across all 15 page templates.
* **Affected Files:**
  - `resources/js/Components/Common/PageHeader.jsx` (New)
  - `resources/js/Components/Common/FilterToolbar.jsx` (New)
  - `resources/js/Components/Common/StatusBadge.jsx` (New)
  - Refactor all 8 analytical page views to consume shared components.
* **Implementation Summary:**
  - Extract reusable `<PageHeader>`, `<FilterToolbar>`, and `<StatusBadge>`.
  - Replace copy-paste filter blocks across Dashboard, Driving Force, Time Horizon, Urgency, Status, Approval, and Radar.
  - Standardize ASCII status symbols (`∆`, `O`) into `<StatusBadge>`.
* **Effort:** 8 Story Points (12 Hours)
* **Dependencies:** Ticket P2-1.
* **Acceptance Criteria:**
  1. ~800 lines of duplicated JSX removed across page templates.
  2. Filter bar behavior is 100% consistent across all pages.

---

### Ticket P2-3: Deprecate Redundant Breeze Scaffolding (`DS-003`)
* **Priority:** P2 (Tech Debt)
* **Objective:** Remove unused boilerplate components that duplicate native Ant Design components.
* **Affected Files:**
  - `resources/js/Components/DangerButton.jsx`
  - `resources/js/Components/PrimaryButton.jsx`
  - `resources/js/Components/TextInput.jsx`
  - `resources/js/Components/InputLabel.jsx`
  - `resources/js/Components/InputError.jsx`
  - `resources/js/Components/Checkbox.jsx`
  - `resources/js/Components/Navbar.jsx`
  - `resources/js/Components/Sidebar.jsx`
* **Implementation Summary:**
  - Replace any remaining references with native Ant Design components.
  - Safely delete redundant files.
* **Effort:** 2 Story Points (2 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Zero broken imports; test suite remains 100% green.

---

## 5. Phase P3: Visual Polish & Delight (Sprint 4)

*Focus: Elevate presentation polish, interactive responsiveness, and delightful micro-interactions.*

### Ticket P3-1: Bidirectional Foresight Radar Interactive Synchronization (`UX-009`)
* **Priority:** P3 (Executive Delight)
* **Objective:** Enable clicking radar canvas blips to auto-scroll and highlight corresponding table items.
* **Affected Files:**
  - `resources/js/Components/Radar.jsx`
  - `resources/js/Pages/Report/ForesightRadar.jsx`
* **Implementation Summary:**
  - Add canvas coordinate hit-testing on click events.
  - When a user clicks a radar blip, dispatch an `onSelectSignal(signalId)` callback.
  - Smoothly scroll the left signal list to the active item and open its detail drawer.
* **Effort:** 5 Story Points (8 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Clicking a blip scrolls and highlights the corresponding list row.
  2. Hovering displays rich tooltip metadata.

---

### Ticket P3-2: Responsive Radar Canvas ViewBox Auto-Scaling (`RESP-004`)
* **Priority:** P3 (Cross-Device Polish)
* **Objective:** Make the 360° circular radar scale fluidly on tablet and mobile viewports.
* **Affected Files:**
  - `resources/js/Components/Radar.jsx`
* **Implementation Summary:**
  - Replace fixed 600&times;600 canvas sizing with dynamic width measuring via `ResizeObserver`.
  - Recalculate radius $R = \frac{width - padding}{2}$ dynamically.
* **Effort:** 3 Story Points (5 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Radar scales smoothly from 300px to 1400px width without coordinate distortion.

---

### Ticket P3-3: Bulk Excel Export Progress Feedback (`UX-010`)
* **Priority:** P3 (Feedback State)
* **Objective:** Provide visual progress indicator during large strategic ledger exports.
* **Affected Files:**
  - `resources/js/Pages/Report/RegisteredList.jsx`
* **Implementation Summary:**
  - Add an Ant Design `<Progress percent={...} status="active" />` notification during export generation.
* **Effort:** 2 Story Points (2 Hours)
* **Dependencies:** None.
* **Acceptance Criteria:**
  1. Users receive immediate feedback during export.
  2. Automatic notification dismissal upon browser file download initiation.

---

## 6. Implementation Schedule & Resource Allocation

| Sprint | Phase | Scope | Total Story Points | Estimated Effort | Key Deliverable |
| :---: | :---: | :--- | :---: | :---: | :--- |
| **Sprint 1** | **P0** | Mobile Sider, Labels, Contrast, Admin Filter | 7 SP | 9 Hours | WCAG AA compliant auth; usable mobile shell |
| **Sprint 2** | **P1** | Stepper Drawer, Approval Overhaul, Signal Graduation | 21 SP | 30 Hours | 10x faster analyst assessment throughput |
| **Sprint 3** | **P2** | Central Theme, Shared Toolbar, Component Cleanup | 13 SP | 18 Hours | Clean design system architecture; zero duplication |
| **Sprint 4** | **P3** | Radar Blip Sync, Responsive Canvas, Export Progress | 10 SP | 15 Hours | Presentation-grade interactive visual radar |
| **Total** | | | **51 SP** | **72 Hours** | **Production-grade enterprise UI/UX** |
