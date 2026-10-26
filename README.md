# Foresight Radar

Foresight Radar is an executive intelligence and strategic foresight platform designed to ingest weak signals of change, evaluate driving forces, detect systems-dynamics causal loops, and simulate 2x2 scenario planning matrices.

## Technology Stack

- Backend: PHP 8.3, Laravel 11, MySQL 8.0, Redis, Spatie Laravel Permission
- Frontend: React 19, Inertia.js 2.0, TypeScript, Tailwind CSS v4, Radix UI, TanStack Table
- LLM Gateway: Multi-provider engine (OpenAI-compatible, Anthropic Claude, Google Gemini, Ollama) with background queue processing and deterministic offline test fallbacks

## Key Capabilities

- Signal Ingestion and Verification: Ingests unstructured evidence, extracts candidate weak signals via LLM workers, and enforces quote veracity against sources.
- Driving Force Lifecycle: 6-stage lifecycle tracking with multi-rater urgency scoring and priority classification.
- Cross-Impact and Causal Loop Analysis: Cross-impact matrix evaluation with cycle detection to identify reinforcing and balancing feedback loops.
- 2x2 Scenario Planning: Scenario matrix generator with atomic queue locking, narrative generation, and propagated what-if simulation ripples.
- Multi-Tenant Isolation: Strict 1-user-1-tenant security model with organization workspace settings and team invitation governance.
- Interactive Visualizations: Dynamic force-directed Signal Knowledge Graph and Causal Loop Network Graph.
- Internationalization: Native bilingual support (English and Indonesian) with session and cookie persistence.

## System Requirements

- PHP >= 8.3
- Composer >= 2.2
- Node.js >= 18.0 and NPM
- MySQL >= 8.0
- Redis (recommended for queue and cache handling)
- Docker and Docker Compose (optional, for containerized workflows)

## Getting Started

### Local Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/roozylabs27/foresight-radar.git
   cd foresight-radar
   ```

2. Configure environment variables:
   ```bash
   cp .env.example .env
   ```
   Update database credentials and optional LLM API keys in `.env`.

3. Install dependencies:
   ```bash
   composer install
   npm install
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Run migrations and database seeders:
   ```bash
   php artisan migrate --seed
   ```

6. Start local development processes:
   ```bash
   # Terminal 1: Backend Server
   php artisan serve

   # Terminal 2: Frontend Asset Server
   npm run dev

   # Terminal 3: Background Queue Worker
   php artisan queue:work
   ```

### Docker Setup

Alternatively, start the environment using the provided harness script:
```bash
./init.sh
```
Or directly using Docker Compose:
```bash
docker compose up -d
```

## Quality Assurance and Verification

The repository enforces strict quality standards before merging:

- Unified Quality Verification (Pint, Larastan Level 5, and PHPUnit):
  ```bash
  composer verify
  ```
- Code Style Check:
  ```bash
  composer format:check
  ```
- Code Style Auto-Fix:
  ```bash
  composer format
  ```
- Static Analysis:
  ```bash
  composer stan
  ```
- Automated Test Suite:
  ```bash
  composer test
  ```
- Frontend Production Build:
  ```bash
  npm run build
  ```
- TypeScript Type Check:
  ```bash
  npm run typecheck
  ```

## License

This project is open-sourced under the MIT License.
