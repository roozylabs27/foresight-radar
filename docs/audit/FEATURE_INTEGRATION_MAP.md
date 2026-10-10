# Feature Integration Map - Foresight Radar

This document provides visual architectural models of feature integration across Foresight Radar. It models the end-to-end business user journey, the technical component dependency graph, and the comprehensive data flow lifecycle using standard Mermaid diagrams.

---

## 1. Business User Journey Map

The following workflow diagram illustrates how human personas (Strategic Analysts, Working Groups, Board of Directors, and Executives) progress through the corporate foresight lifecycle from raw external signals to strategic visualization.

```mermaid
flowchart TD
    subgraph S0["Stage 0: Environmental Scanning & Ingestion"]
        A1["Analyst inputs URL or Raw Intelligence"] --> A2["Signal Extraction Service parses Candidates"]
        A2 --> A3{"Analyst Review Decision"}
        A3 -->|Reject| A4["Signal marked REJECTED"]
        A3 -->|Link to Existing| A5["Link Pivot Association Created"]
        A3 -->|Promote to New| B1["Spawn New Driving Force"]
    end

    subgraph S1_S4["Stage 1 to 4: Analysis & Deliberation Pipeline"]
        B1 --> B2["Stage 1: Register Core Metadata (Dimension, Environment)"]
        B2 --> B3["Stage 2: Assess Time Horizon (Short, Medium, Long, Ultra)"]
        B3 --> B4["Stage 3: Score Urgency (Impact: 1-10, Uncertainty: 1-10)"]
        B4 --> B5["Auto-Calculate Strategic Priority (High, Med, Low)"]
        B5 --> B6["Stage 4: Designate Status of Action (Act, Prepare, Watch, Dismiss)"]
        B6 --> B7["Log Mandatory Audit Justification in Action Reasons"]
    end

    subgraph S5["Stage 5: Executive Governance Gate"]
        B7 --> C1{"Executive Review (BOD / Management)"}
        C1 -->|Reject with Feedback| C2["Status REJECTED (Feedback Remark logged)"]
        C1 -->|Decommission / Dismiss| C3["Status CLOSED (Decommissioned)"]
        C1 -->|Approve| D1["Status APPROVED (Timestamp & User recorded)"]
    end

    subgraph S6_S7["Stage 6 & 7: Consumption, Visualizations & Archival"]
        C3 --> E1["Stage 6: Closed Items Terminal Archive"]
        D1 --> F1["Stage 7A: Foresight Radar (Concentric Polar Canvas)"]
        D1 --> F2["Stage 7B: Prioritizing Grid (2D Cartesian Matrix)"]
        D1 --> F3["Stage 7C: Registered List Matrix & PDF/Excel Export"]
        D1 --> F4["Stage 7D: Executive KPI Overview Dashboard"]
    end
```

---

## 2. Technical Component Dependency Map

This diagram models the architectural wiring across frontend Inertia views, HTTP controllers, domain services, Eloquent models, and underlying MySQL database tables.

```mermaid
flowchart TD
    subgraph UI["Frontend React & Inertia Layer"]
        V_Signals["Signals/Index.jsx"]
        V_DF["DrivingForces/Index.jsx"]
        V_TH["TimeHorizon/Index.jsx"]
        V_Urgency["RatingUrgency/Index.jsx"]
        V_Status["StatusAction/Index.jsx"]
        V_Appr["Approval/Index.jsx"]
        V_Closed["ClosedItems/Index.jsx"]
        V_Radar["Visualizations/ForesightRadar.jsx"]
        V_Prio["Visualizations/Prioritizing.jsx"]
        V_List["Visualizations/RegisteredList.jsx"]
        V_Dash["Dashboard.jsx"]
    end

    subgraph Controllers["Backend Controllers & Routes"]
        C_Signals["SignalIngestionController"]
        C_DF["DrivingForceController"]
        C_TH["TimeHorizonController"]
        C_Urgency["RatingUrgencyController"]
        C_Status["StatusActionController"]
        C_Appr["ApprovalController"]
        C_Closed["ClosedItemsController"]
        C_Radar["ForesightRadarController"]
        C_Prio["PrioritizingController"]
        C_List["RegisteredListController"]
        R_Dash["web.php Route Closure"]
    end

    subgraph Services["Domain Services Layer"]
        S_Extract["SignalExtractionService"]
        S_DF["DrivingForceService"]
    end

    subgraph Models["Eloquent Domain Models"]
        M_Source["Source"]
        M_Signal["Signal"]
        M_DF["DrivingForce"]
        M_Rating["DrivingForceRating"]
        M_Reason["ActionReason"]
        M_Taxon["Dimension / Environment / TimeHorizon / Priority / StatusAction"]
    end

    subgraph Database["MySQL Relational Schema"]
        T_Sources[("sources")]
        T_Signals[("signals")]
        T_Pivot[("signal_driving_force")]
        T_DF[("driving_forces")]
        T_Ratings[("driving_force_ratings")]
        T_Reasons[("action_reasons")]
        T_Dicts[("lookup dictionaries")]
    end

    %% Wiring
    V_Signals --> C_Signals
    V_DF --> C_DF
    V_TH --> C_TH
    V_Urgency --> C_Urgency
    V_Status --> C_Status
    V_Appr --> C_Appr
    V_Closed --> C_Closed
    V_Radar --> C_Radar
    V_Prio --> C_Prio
    V_List --> C_List
    V_Dash --> R_Dash

    C_Signals --> S_Extract
    C_Signals --> S_DF
    C_DF --> S_DF
    C_Urgency --> S_DF
    C_Status --> S_DF

    S_Extract --> M_Source
    S_Extract --> M_Signal
    S_DF --> M_DF
    S_DF --> M_Rating

    C_TH --> M_Rating
    C_Status --> M_Reason
    C_Appr --> M_DF
    C_Closed --> M_DF
    C_Radar --> M_Rating
    C_Prio --> M_Rating
    C_List --> M_Rating
    R_Dash --> M_DF

    M_Source --> T_Sources
    M_Signal --> T_Signals
    M_Signal --> T_Pivot
    M_DF --> T_Pivot
    M_DF --> T_DF
    M_Rating --> T_Ratings
    M_Reason --> T_Reasons
    M_Taxon --> T_Dicts
```

---

## 3. Data Flow & State Lifecycle Diagram

This diagram charts the data mutation lifecycle of a core foresight item from initial creation to final presentation, demonstrating where derived calculations occur and which features consume the data.

```mermaid
sequenceDiagram
    autonumber
    actor Analyst as Strategic Analyst
    actor Executive as Executive / BOD
    participant SignalMod as Signal Ingestion
    participant DFMod as Driving Force Service
    participant RatingMod as Rating System
    participant ApprovalMod as Approval Governance
    participant RadarMod as Foresight Radar

    Analyst->>SignalMod: Ingest Intelligence URL
    SignalMod->>SignalMod: Parse Candidate Signals
    Analyst->>SignalMod: Review & Click "Promote to New"
    SignalMod->>DFMod: Create Driving Force + Pivot Record
    DFMod->>RatingMod: Initialize Rating (status = PENDING)
    
    Analyst->>RatingMod: Assign Time Horizon (Stage 2)
    Analyst->>RatingMod: Score Impact (8) and Uncertainty (7) (Stage 3)
    RatingMod->>RatingMod: Calculate Priority -> High Priority (ID: 1)
    
    Analyst->>RatingMod: Assign Status Action (Act) + Rationale (Stage 4)
    RatingMod->>RatingMod: Append to action_reasons audit table
    Note over RatingMod,ApprovalMod: Driving Force now eligible for Approval Queue
    
    Executive->>ApprovalMod: Inspect Pending Item & Justifications
    Executive->>ApprovalMod: Submit Action (APPROVED)
    ApprovalMod->>DFMod: Set status = APPROVED, approved_at = now()
    
    Note over DFMod,RadarMod: Item immediately unblocked for Visualizations
    Analyst->>RadarMod: Load /visualization/foresight-radar
    RadarMod->>DFMod: Query approved items with ratings
    RadarMod->>RadarMod: Map Environment to Angle, Horizon to Radius
    RadarMod-->>Analyst: Render Interactive Polar Plot
```
