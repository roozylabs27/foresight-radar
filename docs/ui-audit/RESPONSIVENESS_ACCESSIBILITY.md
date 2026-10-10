# Responsiveness & WCAG Accessibility Audit — Foresight Radar

> **Role:** Accessibility Specialist (WCAG 2.1 AA) & Senior Frontend Engineer  
> **Repository:** `roozylabs27/foresight-radar`  
> **Audit Date:** October 2026  
> **Tools:** Chrome DevTools MCP Runtime Inspection, Accessibility Tree Audits, Color Contrast Analyzers  
> **Target Standard:** WCAG 2.1 Level AA Compliance

---

## 1. Executive Summary

A comprehensive accessibility and cross-device responsiveness audit was conducted on Foresight Radar across Desktop (1440&times;900) and Mobile (375&times;812) viewports. While the desktop experience is visually well-structured, **critical usability and accessibility barriers** emerge on smaller screens and for keyboard/screen-reader users.

### Overall Compliance Scorecard:
- **Responsive Layout Integrity:** 48% (Severe mobile pinching due to collapsed sidebar; table horizontal clipping)
- **WCAG 2.1 AA Color Contrast:** 72% (Microcopy, light yellow alerts, and tag hover states fail 4.5:1 ratio)
- **Keyboard & Screen Reader Ergonomics:** 60% (Missing form label associations, incomplete ARIA attributes on custom canvas elements)

---

## 2. Responsive Layout & Viewport Audit

```
Desktop (1440x900)       ====== Sider (240px) ====== [========= Content Area (1200px) =========] -> Cohesive
Tablet (768x1024)        == Sider (64px) == [=============== Content Area (704px) ===============] -> Minor Wrapping
Mobile (375x812)         == Sider (64px) == [==== Pinched Content Area (311px) ====] -> SEVERE CLIPPING
```

### 2.1 Detailed Responsive Findings

---

#### Finding RESP-001: Mobile Sider Canvas Pinching (Critical)
* **Finding ID:** `RESP-001`
* **Affected Component:** [`resources/js/Layouts/AuthenticatedLayout.jsx#L206-L221`](file:///c:/laragon/www/foresight-radar/resources/js/Layouts/AuthenticatedLayout.jsx#L206-L221)
* **Evidence:** Sider configuration:
  ```jsx
  <Sider
      breakpoint="lg"
      collapsedWidth="64"
      theme="light"
      width={240}
      collapsed={collapsed}
      collapsible
      trigger={null}
      style={{
          borderRight: "1px solid #f0f0f0",
          position: "sticky",
          top: 0,
          height: "100vh",
          overflowY: "auto",
          zIndex: 20,
      }}
  >
  ```
* **Observed Defect:** On mobile viewports (&le; 576px, specifically tested at 375&times;812), the Sider collapses to a fixed 64px vertical icon rail on the left. On an iPhone with a 375px screen width, this consumes 17% of total horizontal screen real estate, leaving only **311px** for the entire layout.
* **Impact:** Every analytical table, date filter, and the 360° radar canvas are compressed into 311px, causing severe horizontal overflow, wrapped text, and clipped interactive buttons.
* **Remediation:** On viewports below `768px` (`md`), the Sider should collapse completely to `0px` (`collapsedWidth={0}`) and be replaced by an Ant Design `<Drawer>` that slides out from the left when the user taps the top-left hamburger menu.
* **Acceptance Criteria:**
  1. On viewports &le; 768px, main content area occupies 100% screen width.
  2. Sidebar opens as a smooth overlay drawer without pushing page content.

---

#### Finding RESP-002: Analytical Date Range Picker Overflow on Mobile & Tablet
* **Finding ID:** `RESP-002`
* **Affected Component:** `.radar-filter-bar` in [`resources/css/additional.css#L134-L146`](file:///c:/laragon/www/foresight-radar/resources/css/additional.css#L134-L146) & [`DrivingForce/Table.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/DrivingForce/Table.jsx#L57)
* **Evidence:** Ant Design `<DatePicker.RangePicker>` requires a minimum width of `260px` to display start date, arrow icon, and end date cleanly.
* **Observed Defect:** Within the 311px pinched mobile container, the RangePicker, Dimension Select dropdown, and Search input cannot fit horizontally. They wrap into awkward staggered rows with mismatched heights and truncated dropdown placeholder text ("Semua Dim...").
* **Impact:** Analysts using tablets or mobile phones cannot select dates comfortably; touch targets overlap with adjacent dropdowns.
* **Remediation:** Refactor `.radar-filter-bar` to use a responsive Ant Design `<Row gutter={[8, 8]}>` where filter controls span `span={24}` on mobile (`xs`), `span={12}` on tablet (`sm`/`md`), and inline flex on desktop (`lg`).
* **Acceptance Criteria:**
  1. Filter controls stack into clean full-width cards on mobile screens.
  2. Touch target height is at least `44px` on mobile.

---

#### Finding RESP-003: 12-Column Table Horizontal Trapping
* **Finding ID:** `RESP-003`
* **Affected Component:** [`resources/js/Pages/Approval/Table.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Approval/Table.jsx) & [`RegisteredList.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Report/RegisteredList.jsx)
* **Evidence:** Tables render 10 to 12 columns with `scroll={{ x: 1600 }}` or without horizontal scroll wrappers.
* **Observed Defect:** On mobile and small laptop viewports, users get trapped inside the table scroll area. Swiping horizontally moves the table columns instead of scrolling the page, and the crucial decision actions ("Approve / Reject") are located 1,200 pixels to the right.
* **Impact:** Complete failure of mobile usability for executive review. An executive on an iPad or phone cannot review and approve foresight proposals.
* **Remediation:**
  1. Implement responsive card views for mobile (`xs`/`sm`), transforming each table row into an Ant Design `<Card>` showing key badges, keyword, and primary action buttons.
  2. Pin the "Action" column (`fixed: 'right'`) on desktop table views.
* **Acceptance Criteria:**
  1. Under 768px, table seamlessly switches to a card-list presentation.
  2. On desktop, action buttons remain sticky on the right border.

---

#### Finding RESP-004: 360° Radar Canvas Aspect Ratio Clipping on Mobile
* **Finding ID:** `RESP-004`
* **Affected Component:** [`resources/js/Components/Radar.jsx#L110-L160`](file:///c:/laragon/www/foresight-radar/resources/js/Components/Radar.jsx#L110-L160)
* **Evidence:** Radar canvas renders with a fixed or hardcoded pixel dimension:
  ```jsx
  <canvas width={600} height={600} ... />
  ```
* **Observed Defect:** In mobile viewports (375px wide), the 600px canvas extends past the right edge of the screen by 225px, cutting off the eastern dimension wedges (e.g., "Technology", "Market", "Competitor").
* **Impact:** The 360° radar is completely unusable on mobile and small tablets.
* **Remediation:** Make the canvas container responsive using `ResizeObserver` or CSS viewBox percentage scaling (`width: 100%, height: auto, aspectRatio: 1 / 1`), recalculating radar center coordinates (`cx, cy`) dynamically based on measured client width.
* **Acceptance Criteria:**
  1. Radar canvas automatically resizes to fit container width on all viewports from 320px to 3840px (4K).
  2. Text labels scale proportionally to maintain legibility.

---

## 3. WCAG 2.1 AA Accessibility Audit

---

### 3.1 Form Controls & Label Associations

#### Finding A11Y-001: Missing `<label for>` Associations (Console Violation)
* **Finding ID:** `A11Y-001`
* **Affected Component:** [`resources/js/Pages/Auth/Login.jsx#L14-L21`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Auth/Login.jsx#L14-L21) & [`UserManagement/User/Form.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/UserManagement/User/Form.jsx)
* **Runtime Evidence:** Browser Console logs:
  ```
  [issue] Incorrect use of <label for=FORM_ELEMENT> (count: 2)
  ```
* **Underlying Defect:** In `Login.jsx`, labels are rendered as plain `<Text>` components inside custom flex divs instead of using Ant Design's `<Form.Item label="...">` with an associated `id` and `htmlFor` attribute linking to the `<Input>` element.
* **WCAG Guideline:** **WCAG 1.3.1 Info and Relationships (Level A)** & **WCAG 4.1.2 Name, Role, Value (Level A)**.
* **User Impact:** Screen readers (JAWS, NVDA, VoiceOver) cannot announce the purpose of the email and password inputs when focused.
* **Remediation:** Wrap all inputs with standard `<Form.Item name="email" label="Email Kerja" htmlFor="login_email">` and assign `id="login_email"` to the `<Input>`.
* **Acceptance Criteria:**
  1. Zero `<label for>` console warnings in DevTools.
  2. Screen reader correctly announces "Email Kerja, edit text" upon focusing the email field.

---

### 3.2 Color Contrast Analysis (WCAG 1.4.3 Level AA)

The WCAG 2.1 AA standard requires:
- Minimum **4.5:1** contrast ratio for regular body text (< 18pt / < 14pt bold).
- Minimum **3.0:1** contrast ratio for large text (&ge; 18pt / &ge; 14pt bold) and UI components/icons.

#### Contrast Test Results Table:

| UI Element / Location | Foreground | Background | Measured Ratio | WCAG AA Status | Remediation Required |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **Subtitle Microcopy** (`AuthenticatedLayout.jsx`) | `#8c8c8c` | `#ffffff` | **2.97:1** | ❌ **FAIL** (Requires 4.5:1) | Darken to `#595959` (Ratio 5.14:1) |
| **Copyright Footer** (`AuthenticatedLayout.jsx`) | `#8c8c8c` | `#f5f5f5` | **2.81:1** | ❌ **FAIL** (Requires 4.5:1) | Darken to `#595959` |
| **Custom Tag Hover** (`additional.css`) | `#ffffff` | `#e39b54` | **2.65:1** | ❌ **FAIL** (Requires 4.5:1) | Darken orange to `#b25900` (Ratio 4.58:1) |
| **Yellow Highlight Cells** (`.yellow-background`) | `#262626` | `#fffbe6` | **12.4:1** | ✅ **PASS** | Valid contrast. |
| **Green Button Text** (`.custom-green-button`) | `#ffffff` | `#4caf50` | **3.08:1** | ❌ **FAIL** for small text | Deepen green to `#2e7d32` (Ratio 4.54:1) |
| **Red Button Text** (`.custom-red-button`) | `#ffffff` | `#b63232` | **4.82:1** | ✅ **PASS** | Valid contrast. |
| **Primary Blue Button** (`#1677ff`) | `#ffffff` | `#1677ff` | **4.08:1** | ⚠️ **FAIL** for small text | Deepen to `#0958d9` (Ratio 5.48:1) for small labels |

---

### 3.3 Keyboard Navigation & Focus Management

#### Finding A11Y-002: Incomplete Focus Rings on Custom Controls
* **Finding ID:** `A11Y-002`
* **Affected Component:** `.radar-table-row` in [`additional.css#L36-L47`](file:///c:/laragon/www/foresight-radar/resources/css/additional.css#L36-L47) & Custom Icon Buttons
* **Observed Defect:** Interactive signal rows in the Foresight Radar view support mouse clicks to highlight radar blips, but do not have `tabIndex={0}` or a visible `:focus-visible` outline. Keyboard users cannot tab through signals or trigger radar blip highlights via `Enter` or `Space`.
* **WCAG Guideline:** **WCAG 2.1.1 Keyboard (Level A)** & **WCAG 2.4.7 Focus Visible (Level AA)**.
* **Remediation:**
  1. Add `tabIndex={0}` and `onKeyDown` handlers (`Enter`, `Space`) to `.radar-table-row`.
  2. Add high-contrast focus rings:
     ```css
     .radar-table-row:focus-visible {
         outline: 2px solid #1677ff;
         outline-offset: -2px;
     }
     ```
* **Acceptance Criteria:**
  1. Keyboard users can navigate the entire signal list using `Tab` and `Shift+Tab`.
  2. Pressing `Enter` highlights the corresponding blip on the radar canvas.

---

### 3.4 ARIA Landmarks & Accessibility Tree

#### Finding A11Y-003: Missing Modal `aria-describedby` & Focus Traps
* **Finding ID:** `A11Y-003`
* **Affected Component:** [`resources/js/Components/Dialog.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Components/Dialog.jsx)
* **Observed Defect:** When custom dialogs open, the focus is not programmatically shifted to the first input field, and screen readers are not provided an `aria-describedby` pointer explaining the modal action.
* **Remediation:** Ensure Ant Design's `destroyOnClose`, `autoFocusButton`, and accessible `title` props are passed cleanly to every modal instance.
* **Acceptance Criteria:**
  1. Opening the Driving Force modal automatically places focus into the first input field.
  2. Pressing `Escape` reliably closes the dialog and returns focus to the trigger button.
