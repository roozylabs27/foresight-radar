# Foresight Radar: Domain Gap Analysis and Strategic Assessment

**Assessment date:** 2026-10-09  
**Auditor roles:** Strategic Foresight Systems Analyst, Product Architect  
**Status:** Strategic Domain Evaluation

---

## 1. Executive Summary

A critical prerequisite before introducing automated signal ingestion or AI-assisted synthesis into Foresight Radar is validating whether the application's underlying domain model can accurately represent strategic foresight concepts.

This analysis evaluates the current application against the 7 fundamental foresight entities (Signal, Trend, Driving Force, Assessment, Scenario, Strategic Implication, Action) and directly addresses the 10 architectural discovery questions required for product evolution.

---

## 2. Answers to the 10 Core Architectural Questions

### 1. Which concepts already exist in the database and application?
* **Driving Force:** Fully modeled via `driving_forces` table (keyword, description, dimension, PIC, status, timestamps).
* **Assessment:** Partially modeled via `driving_force_ratings` table (stores single `time_horizon_id`, `impact_analysis`, `uncertainty_analysis`, `priority_id`, `status_action_id`).
* **Time Horizon:** Seeded via `time_horizons` lookup table (Short, Medium, Long term).
* **Status Action:** Seeded via `status_actions` (Act, Monitor, Park) with historical notes in `action_reasons`.
* **Dimension & Environment:** Structured parent-child relationship via `environments` and `dimensions`.
* **Signal, Trend, Scenario, Implication:** **Not modeled.** Completely absent from the database schema and application code.

---

### 2. Are signals and driving forces currently represented by the same entity or different entities?
* **Answer: Same entity.**
* In the current implementation, `DrivingForce` represents both the initial emerging observation and the macro environmental force. 
* While the UI frequently uses the label "Signal" (e.g., `Radar.jsx` displays a side-table titled *"Signal of Changes"* and controller messages say *"Successfully create new signal ! "*), there is no distinct `Signal` table or model.

---

### 3. Can one signal be connected to multiple trends or driving forces?
* **Answer: No.**
* Because signals and driving forces are conflated into a single row in `driving_forces`, each record is isolated. 
* An emerging regulatory signal cannot be linked across both an "Energy Transition" driving force and an "Automotive Regulation" driving force.

---

### 4. Can multiple evidence sources support one assessment?
* **Answer: No.**
* The current database has no fields or tables for source URLs, publishers, document attachments, author credentials, or citations. 
* A driving force description is free text authored by a user, with zero relational backing to external empirical evidence.

---

### 5. Are assessment scores based on a defined methodology?
* **Answer: Partially implemented; lacks methodological rubric.**
* Scoring relies on raw integers (1–10) for Impact and Uncertainty, with hardcoded threshold branching in controllers (`impact >= 6 && uncertainty >= 6 => High`).
* There is no qualitative rubric, confidence rating, multi-criteria weight matrix, or assessor calibration guidance in the application.

---

### 6. Is the radar position derived from actual data or manually configured?
* **Answer: Derived from data with fixed angular mapping.**
* The angle $\theta$ is determined by matching `driving_force.dimension.name` to 8 hardcoded polar angles (Economy: 0°, Technology: 225°, Regulation: 270°, etc.).
* The radial distance $r$ is derived from `driving_force_rating.time_horizon_id`.
* Overlapping coordinates are separated using a client-side Euclidean anti-collision algorithm.

---

### 7. Are time horizons and uncertainty represented explicitly?
* **Answer: Yes.**
* Time horizon is explicitly linked via foreign key `time_horizon_id` to `time_horizons`.
* Uncertainty is explicitly stored as integer `uncertainty_analysis` (1–10) in `driving_force_ratings`.

---

### 8. Can an assessment be revised while preserving its history?
* **Answer: No.**
* `driving_force_ratings` has a strict 1:1 relationship with `driving_forces`.
* When an analyst updates an impact or uncertainty score, the prior values are overwritten in place. 
* The only historical tracking in the platform is `action_reasons`, which captures changes to the `status_action_id` (Act, Monitor, Park).

---

### 9. Can users distinguish source evidence from AI-generated interpretation?
* **Answer: No.**
* There is currently zero AI integration in the repository. 
* There are no provenance flags (such as `is_ai_generated`, `ai_model`, `prompt_hash`, `confidence_score`), making it impossible to audit human vs. machine authorship.

---

### 10. Can an insight be traced to the business decision it influenced?
* **Answer: No.**
* The workflow terminates at `status = 'CLOSED'` and display on the radar/registered list. 
* There are no relational linkages between closed driving forces and executive strategic plans, departmental OKRs, risk registers, or capital allocations.

---

## 3. Comprehensive Domain Gap Matrix

| Domain Capability | Current Implementation | Target Strategic State | Architectural Gap | Recommended Milestone |
|---|---|---|---|---|
| **Source Evidence Tracking** | Zero source tracking | Verifiable links to URLs, papers, and filings | Missing `sources` table and evidence quote fields | **Stage 4 (POC)** |
| **Signal-Driving Force Decoupling** | Conflated in `driving_forces` | Granular signals aggregated into macro driving forces | Missing `signals` model and `signal_driving_force` pivot | **Stage 4 (POC)** |
| **AI Provenance & Human Review** | No AI tracking | Clear visual distinction of AI candidate signals | Missing `is_ai_generated` flag and review status workflow | **Stage 4 (POC)** |
| **Assessment Versioning** | Destructive in-place rating overwrites | Full audit history of rating revisions and rationales | Missing `revision_number` and assessment version log | **Stage 5 (Evolution)** |
| **Multi-Assessor Calibration** | Single rating record per driving force | Multi-stakeholder voting and consensus variance | 1:1 relationship blocks multiple assessor scores | **Stage 5 (Evolution)** |
| **Strategic Implications** | Free-text remarks on rejection only | Categorized organizational threats and opportunities | Missing `strategic_implications` entity | **Stage 5 (Evolution)** |
| **Scenario Planning** | Not present | 2x2 alternative future scenarios | Missing `scenarios` and scenario axis definitions | **Stage 5 (Evolution)** |
| **Decision Traceability** | None | Linkages to corporate initiatives and actions | Missing action item tracking and OKR linkages | **Stage 5 (Evolution)** |

---

## 4. Architectural Boundaries for Stage 4 Proof of Concept

To avoid overengineering the schema or disrupting the existing 6-stage lifecycle, the boundary for the Stage 4 Signal-to-Insight Proof of Concept is strictly defined:

```
┌────────────────────────────────────────────────────────┐
│ STAGE 4 POC BOUNDARY (Target Ingestion Pipeline)       │
│                                                        │
│  [Source Document / URL]                               │
│            │                                           │
│            ▼                                           │
│       ┌─────────┐                                      │
│       │ Sources │                                      │
│       └────┬────┘                                      │
│            │ extracts                                  │
│            ▼                                           │
│       ┌─────────┐                                      │
│       │ Signals │ (Review Workflow: Pending/Accepted)  │
│       └────┬────┘                                      │
└────────────┼───────────────────────────────────────────┘
             │ Analyst Accepts & Links / Creates
             ▼
┌────────────────────────────────────────────────────────┐
│ EXISTING REPOSITORY (Preserved Core Workflow)          │
│                                                        │
│  ┌────────────────┐                                    │
│  │ Driving Forces │ ◄── Existing 6-Stage Lifecycle     │
│  └───────┬────────┘                                    │
│          ▼                                             │
│  ┌────────────────┐                                    │
│  │ Ratings / Radar│ ◄── Existing Visualization Engine   │
│  └────────────────┘                                    │
└────────────────────────────────────────────────────────┘
```

By decoupling raw signal intake from the driving force repository, the system preserves existing business stability while introducing verifiable external intelligence.
