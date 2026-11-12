# Foresight Radar: Architecture

**Inspection date:** 2026-10-09

---

## 1. Technology Stack

| Layer | Component | Version | Source |
|---|---|---|---|
| Backend runtime | PHP | ^8.1 (resolved 8.2) | [composer.json L8](file:///c:/laragon/www/foresight-radar/composer.json#L8) |
| Web framework | Laravel | ^10.10 (resolved 10.48.17) | [composer.json L11](file:///c:/laragon/www/foresight-radar/composer.json#L11) |
| Auth scaffolding | Laravel Breeze | ^1.26 (dev) | [composer.json L19](file:///c:/laragon/www/foresight-radar/composer.json#L19) |
| RBAC | Spatie Laravel-Permission | ^6.9 | [composer.json L14](file:///c:/laragon/www/foresight-radar/composer.json#L14) |
| API tokens | Laravel Sanctum | ^3.2 | [composer.json L12](file:///c:/laragon/www/foresight-radar/composer.json#L12) |
| Frontend bridge | Inertia.js (server) | ^0.6.3 | [composer.json L10](file:///c:/laragon/www/foresight-radar/composer.json#L10) |
| Route generation | Ziggy | ^2.0 | [composer.json L15](file:///c:/laragon/www/foresight-radar/composer.json#L15) |
| Frontend framework | React | ^18.2.0 | [package.json L19](file:///c:/laragon/www/foresight-radar/package.json#L19) |
| Frontend bridge (client) | @inertiajs/react | ^1.0.0 | [package.json L12](file:///c:/laragon/www/foresight-radar/package.json#L12) |
| UI components | Ant Design | ^5.19.3 | [package.json L27](file:///c:/laragon/www/foresight-radar/package.json#L27) |
| Icons | @ant-design/icons | ^5.4.0 | [package.json L25](file:///c:/laragon/www/foresight-radar/package.json#L25) |
| Charts | ECharts + echarts-for-react | ^5.5.1 / ^3.0.2 | [package.json L29-L30](file:///c:/laragon/www/foresight-radar/package.json#L29-L30) |
| Charts (secondary) | @ant-design/plots | ^2.2.6 | [package.json L26](file:///c:/laragon/www/foresight-radar/package.json#L26) |
| CSS framework | Tailwind CSS | ^3.2.1 | [package.json L21](file:///c:/laragon/www/foresight-radar/package.json#L21) |
| Build tool | Vite | ^5.0.0 | [package.json L22](file:///c:/laragon/www/foresight-radar/package.json#L22) |
| HTTP client | Axios | ^1.6.4 | [package.json L16](file:///c:/laragon/www/foresight-radar/package.json#L16) |
| Excel export | SheetJS (xlsx) | ^0.18.5 | [package.json L33](file:///c:/laragon/www/foresight-radar/package.json#L33) |
| Date handling | Day.js | ^1.11.12 | [package.json L28](file:///c:/laragon/www/foresight-radar/package.json#L28) |
| Database | MySQL | 8.0 | [docker-compose.yml](file:///c:/laragon/www/foresight-radar/docker-compose.yml) |
| Web server | Nginx | 1.25+ | [docker/nginx/default.conf](file:///c:/laragon/www/foresight-radar/docker/nginx/default.conf) |
| Testing | PHPUnit | ^10.1 | [composer.json L24](file:///c:/laragon/www/foresight-radar/composer.json#L24) |

---

## 2. System Architecture

```mermaid
flowchart TD
    Browser["Browser<br/>(React 18 + Ant Design 5)"]
    Nginx["Nginx Reverse Proxy<br/>Port 8080"]
    PHPFPM["PHP-FPM<br/>Laravel 10.48"]
    MySQL["MySQL 8.0<br/>Port 3306"]

    Browser -->|HTTPS| Nginx
    Nginx -->|FastCGI :9000| PHPFPM
    PHPFPM -->|PDO| MySQL
    PHPFPM -->|Inertia JSON| Browser
    Browser -->|Axios async| PHPFPM
```

### Deployment Topology (Docker)

```mermaid
flowchart LR
    subgraph Docker["Docker Compose"]
        APP["laravel_app<br/>PHP-FPM 8.2"]
        NGINX["laravel_nginx<br/>Nginx"]
        DB["laravel_mysql<br/>MySQL 8.0"]
    end

    CLIENT["Client"] -->|:8080| NGINX
    NGINX -->|:9000| APP
    APP -->|:3306| DB
```

---

## 3. Application Layer Architecture

```mermaid
flowchart TD
    subgraph Presentation["Presentation Layer"]
        Pages["Inertia Pages<br/>(React Components)"]
        Components["Shared Components<br/>(Radar, PrioritizingChart, etc.)"]
    end

    subgraph HTTP["HTTP Layer"]
        Routes["Route Dispatcher<br/>routes/web.php"]
        Middleware["Middleware Stack<br/>(Auth, CSRF, Permission)"]
        Controllers["Controllers<br/>(DrivingForce, Approval, etc.)"]
        FormRequests["Form Requests<br/>(Validation)"]
        Resources["API Resources<br/>(Collections)"]
    end

    subgraph Business["Business Layer"]
        Service["DrivingForceService<br/>(Query Logic)"]
        Policies["Policies<br/>(DrivingForce, User)"]
    end

    subgraph Data["Data Layer"]
        Models["Eloquent Models<br/>(DrivingForce, Rating, etc.)"]
        DB["MySQL Database"]
    end

    Pages -->|Inertia Visit| Routes
    Pages -->|Axios GET| Routes
    Routes --> Middleware
    Middleware --> Controllers
    Controllers --> FormRequests
    Controllers --> Service
    Controllers --> Policies
    Controllers --> Resources
    Service --> Models
    Models --> DB
    Resources -->|JSON| Pages
```

---

## 4. Database Entity Relationships

```mermaid
erDiagram
    environments ||--o{ dimensions : "has many"
    dimensions ||--o{ driving_forces : "has many"
    users ||--o{ driving_forces : "created_by"
    users ||--o{ driving_forces : "pic (person-in-charge)"
    users ||--o{ driving_forces : "updated_by"
    driving_forces ||--o| driving_force_ratings : "has one"
    time_horizons ||--o{ driving_force_ratings : "has many"
    status_actions ||--o{ driving_force_ratings : "has many"
    priorities ||--o{ driving_force_ratings : "has many"
    driving_force_ratings ||--o{ action_reasons : "has many"
    status_actions ||--o{ action_reasons : "has many"

    environments {
        uuid id PK
        string name
    }
    dimensions {
        uuid id PK
        uuid environment_id FK
        string name
    }
    driving_forces {
        uuid id PK
        uuid dimension_id FK
        uuid pic FK
        uuid created_by FK
        uuid updated_by FK
        string keyword
        text description
        string status
        text remark
        timestamp approved_at
        timestamp closed_at
        timestamp deleted_at
    }
    driving_force_ratings {
        uuid id PK
        uuid driving_force_id FK
        uuid time_horizon_id FK
        uuid status_action_id FK
        uuid priority_id FK
        integer impact_analysis
        integer uncertainty_analysis
    }
    action_reasons {
        uuid id PK
        uuid driving_force_rating_id FK
        uuid status_action_id FK
        date date
        text reason
    }
    time_horizons {
        uuid id PK
        string name
        string code
    }
    status_actions {
        uuid id PK
        string name
        string symbol
        string code
    }
    priorities {
        uuid id PK
        string name
        string color
    }
    users {
        uuid id PK
        string name
        string email
        string password
    }
```

---

## 5. Module Dependency Map

```mermaid
flowchart LR
    subgraph Core["Core Domain"]
        DF["Driving Force"]
        DIM["Dimension"]
        ENV["Environment"]
    end

    subgraph Assessment["Assessment Pipeline"]
        TH["Time Horizon"]
        RU["Rating/Urgency"]
        SA["Status Action"]
        AR["Action Reason"]
        PR["Priority"]
    end

    subgraph Governance["Governance"]
        APR["Approval"]
        CL["Closed Items"]
    end

    subgraph Visualization["Visualization"]
        RADAR["Foresight Radar"]
        PRIO["Prioritizing Matrix"]
        RL["Registered List"]
    end

    subgraph Admin["Administration"]
        UM["User Management"]
        RBAC["Roles/Permissions"]
    end

    ENV --> DIM
    DIM --> DF
    DF --> TH
    TH --> RU
    RU --> SA
    SA --> AR
    RU --> PR
    SA --> APR
    APR --> CL
    CL --> RADAR
    CL --> PRIO
    CL --> RL
    UM --> RBAC
```

---

## 6. Frontend Page Structure

```mermaid
flowchart TD
    subgraph Auth["Authentication"]
        LOGIN["Login"]
        REG["Register"]
        FORGOT["Forgot Password"]
        RESET["Reset Password"]
        VERIFY["Verify Email"]
    end

    subgraph App["Application (AuthenticatedLayout)"]
        DASH["Dashboard<br/>(Tabs: Prioritizing, Status, Radar)"]

        subgraph Workflow["Data Management"]
            DFT["Driving Force Table"]
            DFF["Driving Force Form"]
            THT["Time Horizon Table"]
            THF["Time Horizon Form"]
            RUT["Rating/Urgency Table"]
            SAT["Status Action Table"]
            SAF["Status Action Form"]
            APRT["Approval Table"]
            CLT["Closed Items Table"]
        end

        subgraph Reports["Reports/Visualization"]
            R_RADAR["Foresight Radar"]
            R_PRIO["Prioritizing"]
            R_LIST["Registered List"]
        end

        subgraph Admin["Administration"]
            UT["User Table"]
            UF["User Form"]
            PROF["Profile Edit"]
        end
    end

    LOGIN --> DASH
    DASH --> Workflow
    DASH --> Reports
    DASH --> Admin
```

---

## 7. Middleware and Authorization Stack

Every authenticated request passes through this chain:

| Order | Middleware | Purpose |
|---|---|---|
| 1 | `TrustProxies` | Handle proxy headers |
| 2 | `HandleCors` | CORS headers |
| 3 | `PreventRequestsDuringMaintenance` | Maintenance mode |
| 4 | `ValidatePostSize` | Request size limit |
| 5 | `TrimStrings` | Input sanitization |
| 6 | `ConvertEmptyStringsToNull` | Null normalization |
| 7 | `EncryptCookies` | Cookie encryption |
| 8 | `AddQueuedCookiesToResponse` | Cookie management |
| 9 | `StartSession` | Session initialization |
| 10 | `ShareErrorsFromSession` | Validation error sharing |
| 11 | `VerifyCsrfToken` | CSRF protection |
| 12 | `SubstituteBindings` | Route model binding |
| 13 | `HandleInertiaRequests` | Inertia protocol (shares auth, permissions, flash) |
| 14 | `auth` | Authentication gate |
| 15 | `verified` | Email verification gate |
| 16 | `can:permission-name` | Spatie permission gate (per-route) |

Source: [app/Http/Kernel.php](file:///c:/laragon/www/foresight-radar/app/Http/Kernel.php)
