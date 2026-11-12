# Foresight Radar: Assessment and Scoring Methodology

**Methodology date:** 2026-10-09  
**Auditor roles:** Strategic Foresight Systems Analyst, Quantitative Methods Specialist  
**Status:** Methodological Specification & Evaluation Guide

---

## 1. Overview and Analytical Philosophy

Strategic foresight assessment evaluates emerging phenomena across quantitative and qualitative dimensions to answer three critical executive questions:
1. **Magnitude:** How severely will this change affect the organization's business model? (Impact)
2. **Predictability:** How uncertain are the trajectory, timing, and nature of the change? (Uncertainty)
3. **Velocity:** When will the consequences materialize? (Time Horizon)

In the current Foresight Radar application, these assessments determine an entity's priority classification, action posture (Act, Monitor, Park), and visual placement on the Foresight Radar and Prioritizing Matrix.

---

## 2. Current Implementation Analysis

### 2.1 The Two-Axis Scoring Matrix
* **Dimensions:**
  * `impact_analysis` (Integer, 1–10)
  * `uncertainty_analysis` (Integer, 1–10)
* **Storage:** Columns in table `driving_force_ratings`.
* **Input Interface:** Interactive numeric tags in `RatingUrgency/Table.jsx`.

### 2.2 Priority Derivation Logic
In [`RatingUrgencyController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php#L61-L69) and [`StatusActionController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php#L66-L74), priority is derived from threshold comparisons:

```
┌─────────────────────────────────────────────────────────────┐
│ High Priority   │ impact >= 6 AND uncertainty >= 6          │
│ Medium Priority │ impact >= 6 OR uncertainty >= 6           │
│ Low Priority    │ impact < 6 AND uncertainty < 6            │
└─────────────────────────────────────────────────────────────┘
```

#### Methodological Limitations of Current Formula:
1. **Asymmetric Sensitivity:** An item with Impact = 10 and Uncertainty = 1 receives "Medium Priority", while an item with Impact = 6 and Uncertainty = 6 receives "High Priority". A catastrophic disruption with high certainty is ranked lower than a moderate disruption with moderate uncertainty.
2. **Absence of Scoring Rubric:** Analysts currently select 1–10 without standard definitions. One analyst may consider a $1M revenue exposure as Impact 8, while another considers it Impact 3.
3. **Lack of Confidence Calibration:** The model does not capture how confident the assessor is in their evaluation, conflating lack of data with low uncertainty.

---

## 3. Standardized Foresight Scoring Rubric

To eliminate subjective variance, the platform requires standard scoring anchors:

### 3.1 Impact Scale (1–10)

| Score Range | Classification | Strategic Definition | Operational Benchmark |
|---|---|---|---|
| **1 – 2** | Negligible | Localized, incremental operational noise | < 1% budget/revenue variance; standard SOP handles |
| **3 – 4** | Minor | Modest efficiency or departmental impact | 1% – 5% operational impact; team-level adjustment |
| **5 – 6** | Moderate | Notable shift requiring tactical reprioritization | 5% – 15% revenue/cost impact; product roadmap revision |
| **7 – 8** | Significant | Core business model or market share disruption | 15% – 30% financial/operational exposure; executive escalation |
| **9 – 10** | Existential | Transformational industry obsolescence or regulatory shutdown | > 30% revenue risk or existential threat to organizational continuity |

---

### 3.2 Uncertainty Scale (1–10)

| Score Range | Classification | Epistemic Definition | Evidence Availability |
|---|---|---|---|
| **1 – 2** | Deterministic | Trend trajectory, timeline, and outcome are clear | Peer-reviewed data, enacted legislation, industry consensus |
| **3 – 4** | Low | High probability direction with minor timing variance | Multiple corroborating signals; commercial pilots underway |
| **5 – 6** | Moderate | Competing standards or bifurcated outcomes | Multiple credible scenarios; regulatory debate active |
| **7 – 8** | High | Volatile trajectory; dependent on breakthrough events | Early lab signals, conflicting expert opinions, high volatility |
| **9 – 10** | Radical | Black swan / emergent novelty; unknown unknowns | Isolated weak signals; zero empirical historical precedent |

---

## 4. Prioritizing Matrix and Strategic Postures

The 2D matrix in `PrioritizingChart.jsx` plots Uncertainty (X-axis) against Impact (Y-axis), dividing the coordinate space into strategic action quadrants:

```
Impact (Y)
  ▲
10│   [PREPARE & ACT]          │      [EXPLORE & HEDGE]
  │   High Impact, Low Uncert. │      High Impact, High Uncert.
  │   Immediate capital alloc. │      Scenario planning, R&D options
  │                            │
 6├────────────────────────────┼──────────────────────────────
  │   [MAINTAIN]               │      [MONITOR / PARK]
  │   Low Impact, Low Uncert.  │      Low Impact, High Uncert.
  │   Business-as-usual tracking│     Passive signal watch
 1│                            │
  └────────────────────────────┴──────────────────────────────►
  1                            6                           10   Uncertainty (X)
```

### Strategic Action Categorization:
* **ACT (`status_action = 'Act'`):** Applied to high-impact items requiring direct capital expenditure, initiative ownership, or strategic realignment.
* **MONITOR (`status_action = 'Monitor'`):** Applied to items with high uncertainty or moderate impact requiring ongoing indicator tracking and automated signal scanning.
* **PARK (`status_action = 'Park'`):** Low impact, low relevance items placed in passive archive with periodic review triggers.

---

## 5. Visual Coordinate Generation on the Radar

The Foresight Radar ([`Radar.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Components/Radar.jsx)) converts structured database records into polar coordinates $(r, \theta)$:

### 5.1 Angular Dimension Mapping ($\theta$)
Eight discrete dimensions are mapped to fixed polar angles:

$$\theta(\text{Dimension}) = \begin{cases} 
0^\circ & \text{Economy} \\
45^\circ & \text{Competitor} \\
90^\circ & \text{Customer} \\
135^\circ & \text{Supplier} \\
180^\circ & \text{Substitute} \\
225^\circ & \text{Technology} \\
270^\circ & \text{Regulation} \\
315^\circ & \text{Ecology} 
\end{cases}$$

### 5.2 Radial Distance Mapping ($r$)
The radius represents the temporal proximity (Time Horizon) of the driving force:
* **Short Term (1–3 years):** Inner circle ($r \approx 1.5 - 3.0$) — imminent operational reality.
* **Medium Term (3–5 years):** Middle circle ($r \approx 4.0 - 6.5$) — strategic planning horizon.
* **Long Term (5–10+ years):** Outer circle ($r \approx 7.5 - 10.0$) — emergent horizon / transformative shifts.

### 5.3 Anti-Collision Algorithm
Because multiple driving forces frequently share identical dimensions and time horizons, dots would stack directly on top of each other. 

In `Radar.jsx` (lines 82–125), a custom Euclidean anti-collision algorithm iteratively detects overlapping points within collision radius $\delta = 0.5$ and adjusts their positions:

$$d(p_1, p_2) = \sqrt{(x_1 - x_2)^2 + (y_1 - y_2)^2}$$

If $d(p_1, p_2) < \delta$, the algorithm applies a small angular offset $\Delta\theta = \pm 3^\circ$ and radial nudge $\Delta r = \pm 0.2$ until all points clear the overlap threshold.

---

## 6. Proposed Methodological Enhancements for Product Evolution

1. **Composite Priority Score:** Replace binary threshold logic with an evidence-weighted composite score:
   $$\text{Priority Score} = w_1 \cdot \text{Impact} + w_2 \cdot \text{Uncertainty} + w_3 \cdot \text{Relevance}$$
2. **Confidence Level Indicator:** Require an explicit confidence rating (1–5) reflecting source quality and corroboration.
3. **Assessor Calibration:** Allow multiple assessors to score the same driving force, displaying mean scores and variance as a proxy for internal consensus.
