# User LLM Settings — Feature Specification & Implementation Plan

## 1. Feature Overview

This feature provides users with a dedicated **Settings > LLM Configuration** page in Foresight Radar where each user can configure, test, and activate their own preferred Large Language Model (LLM) provider.

### Core Requirements
1. **Multi-Provider Support (Latest Models + Custom Model Input):**
   - **Anthropic Claude:**
     - Curated latest: `claude-sonnet-5-5` (Recommended), `claude-opus-5-5`, `claude-3-7-sonnet-latest`, `claude-3-7-sonnet-20250219`, `claude-3-5-sonnet-latest`, `claude-3-5-haiku-latest`.
     - Option to specify any custom Claude model name.
   - **OpenAI (ChatGPT):**
     - Curated latest: `gpt-4o` (Recommended), `gpt-4o-mini`, `o3-mini`, `o1`, `gpt-4.5-preview`, `chatgpt-4o-latest`.
     - Option to specify any custom OpenAI model name.
   - **Google Gemini:**
     - Curated latest: `gemini-3.8-flash` (Recommended), `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.0-flash`, `gemini-1.5-pro`.
     - Endpoint: `https://generativelanguage.googleapis.com/v1beta/openai/` (OpenAI-compatible protocol).
     - Option to specify any custom Gemini model name.
   - **OpenRouter / Custom OpenAI-Compatible:**
     - Any custom provider with custom `base_url`, `api_key`, and `model` (e.g. `anthropic/claude-3.7-sonnet`, `deepseek/deepseek-r1`, `meta-llama/llama-3.3-70b-instruct`).
2. **Single Active Provider Rule:** Exactly **one (1)** provider can be active per user at any given time. Activating one provider automatically deactivates all other providers for that user.
3. **Encrypted Key Storage:** API keys are encrypted at rest using Laravel's application key (`Crypt::encryptString()` / `Crypt::decryptString()`). When loaded into the frontend, keys are masked (`••••••••abcd`) to prevent shoulder surfing or XSS credential leakage.
4. **Live Connection Testing:** Users can click **"Test Connection"** to verify their API key and model validity against the provider's API before saving or activating.
5. **Dynamic Resolution & Fallback:** When a user initiates signal extraction or scenario generation, the application resolves that user's active LLM configuration. If no personal provider is configured or active, it seamlessly falls back to the system environment defaults (`.env`).
6. **Frontend UI & Clean Architecture:**
   - Located at `/settings/llm` accessible via the sidebar navigation (new `Settings` nav item) and user profile dropdown.
   - Built exclusively with `shadcn/ui` + `radix-ui` + Tailwind CSS v4 (no Ant Design).
   - Lean orchestrator page (`< 150 lines`), presentation components in `resources/js/Components/Settings/`, hooks in `resources/js/hooks/`.

---

## 2. Architecture & Data Model

### 2.1 Database Schema (`user_llm_settings`)

```
table: user_llm_settings
├── id: unsignedBigInteger (PK)
├── uuid: uuid (indexed, unique)
├── user_id: foreignId (references users.id, onDelete cascade)
├── provider: enum/string ('anthropic', 'openai', 'gemini', 'openai_compatible')
├── label: string ('Claude', 'ChatGPT', 'Google Gemini', 'OpenRouter')
├── api_key: text (encrypted with Crypt)
├── model: string
├── base_url: string nullable
├── is_active: boolean (default: false)
├── settings: json nullable (custom options: timeout, max_retries, reasoning_effort)
├── created_at: timestamp
└── updated_at: timestamp

Indexes:
- unique(user_id, provider)
- index(user_id, is_active)
```

### 2.2 Domain Entities & Service Layer

```mermaid
flowchart TD
    User["User UI / Inertia Page (/settings/llm)"] -->|POST /settings/llm| Controller["UserLlmSettingController"]
    Controller -->|Store / Update / Activate| Service["UserLlmSettingService"]
    Controller -->|Test Connection| Service
    Service -->|Encrypt / Decrypt| Crypt["Laravel Crypt"]
    Service -->|Save / Query| Model["UserLlmSetting"]
    
    Job1["ExtractSignals Job"] -->|user_id| Resolver["LlmClientResolver"]
    Job2["GenerateScenarioNarrative Job"] -->|user_id| Resolver
    WebAction["Direct User AI Action"] -->|Auth::user()| Resolver
    
    Resolver -->|Active Setting Found?| Factory["LlmFactory"]
    Resolver -->|No Setting / Inactive| Fallback["System Default / AppServiceProvider"]
    Factory -->|Provider Type| Clients["AnthropicClient / OpenAiCompatibleClient"]
```

---

## 3. Implementation Breakdown (Vertical Slices)

### Slice 1: Database Migration & Model (`user_llm_settings`)
- Migration creating `user_llm_settings` with unique constraint `(user_id, provider)`.
- Eloquent Model `App\Models\UserLlmSetting` with:
  - UUID generation
  - Attribute cast: `settings` as `array`, `is_active` as `boolean`
  - Accessors/Mutators for `api_key` using `Crypt::encryptString` and `Crypt::decryptString`
  - Safe representation method `toMaskedArray()` for UI serialization.
- Relationship on `App\Models\User`: `public function llmSettings(): HasMany`.

### Slice 2: LLM Factory & Connection Verification Service
- `App\Services\Llm\LlmClientResolver`:
  - Resolves `StructuredLlmClient` for a specific `User` (or user ID).
  - Implements provider adapters for:
    - **Anthropic Claude:** `AnthropicStructuredClient`
    - **OpenAI (ChatGPT):** `OpenAiCompatibleClient` with `https://api.openai.com/v1`
    - **Google Gemini:** `OpenAiCompatibleClient` with `https://generativelanguage.googleapis.com/v1beta/openai/`
    - **OpenAI-Compatible:** `OpenAiCompatibleClient` with custom `base_url`
  - Fallback to singleton default if user has no active configuration.
- `App\Services\Llm\UserLlmSettingService`:
  - CRUD operations for provider settings.
  - `activateProvider(User $user, string $provider)`: atomic transaction setting selected provider `is_active = true` and all other providers `is_active = false`.
  - `testConnection(array $attributes): array`: executes a lightweight prompt (`"Respond with OK"`) using the candidate credentials to verify connectivity and return latency / status.

### Slice 3: Controller, Requests & Routes
- FormRequests:
  - `SaveUserLlmSettingRequest`: validates `provider`, `model`, `api_key`, `base_url`.
  - `ActivateUserLlmSettingRequest`: validates `provider`.
- `App\Http\Controllers\Settings\UserLlmSettingController`:
  - `index()`: renders Inertia page `Settings/LlmSettings` with masked settings of supported providers and default templates.
  - `store()`: creates/updates setting for a provider.
  - `activate()`: activates selected provider and deactivates others.
  - `test()`: tests credentials without requiring saving first.
  - `destroy()`: resets/deletes settings for a specific provider.
- Routes in `routes/web.php`:
  ```php
  Route::prefix('settings/llm')->name('settings.llm.')->group(function () {
      Route::get('/', [UserLlmSettingController::class, 'index'])->name('index');
      Route::post('/', [UserLlmSettingController::class, 'store'])->name('store');
      Route::post('/activate', [UserLlmSettingController::class, 'activate'])->name('activate');
      Route::post('/test', [UserLlmSettingController::class, 'test'])->name('test');
      Route::delete('/{provider}', [UserLlmSettingController::class, 'destroy'])->name('destroy');
  });
  ```

### Slice 4: Background Jobs & Ingestion Integration
- Inject `LlmClientResolver` into `ExtractSignals` and `GenerateScenarioNarrative` (or their services `SignalExtractionService` and `ScenarioNarrativeService`).
- In `ExtractSignals`, resolve the LLM client of `$source->creator` (`created_by`).
- In `GenerateScenarioNarrative`, resolve the LLM client of `$scenario->creator` or `$userId`.

### Slice 5: Frontend Clean Architecture & UI Design
- **Atoms & Molecules (`resources/js/Components/Settings/`):**
  - `ProviderCard.jsx`: Card representing a provider (Claude, ChatGPT, Gemini, Custom) showing status badge (`Active`, `Configured`, `Not Configured`), model selector, masked API key input, and action buttons.
  - `ModelSelector.jsx`: Searchable / Select primitive with curated model options and ability to enter custom model name.
  - `ConnectionTestBadge.jsx`: Real-time status indicator showing success (latency in ms) or error message.
- **Page (`resources/js/Pages/Settings/LlmSettings.jsx`):**
  - Clean orchestrator `< 150 lines`.
  - Tab or Grid layout of available providers with prominent "Active Provider" highlight.
  - Switch toggle to activate/deactivate.
- **Layout Integration:**
  - Added to `NAV_ITEMS` in `AuthenticatedLayout.jsx` with `SettingsIcon` (Lucide React).
  - Added to user dropdown menu in header.

### Slice 6: Automated Verification & Testing (TDD)
- `tests/Feature/Settings/UserLlmSettingsTest.php`:
  - User can view settings page.
  - User can save encrypted API key and model.
  - Activating one provider deactivates previous active provider.
  - User without active setting falls back to system client.
  - Test connection endpoint verifies credential validity.
  - API key is never exposed unmasked in Inertia props.
- Unified quality contract:
  - `docker exec laravel_app composer verify` (Pint, Larastan Level 5, PHPUnit 100%).
  - `npm run typecheck` + `npm run build`.
  - `npm audit --audit-level=critical`.

---

## 4. Verification Checkpoints

1. [ ] Migration created and executed cleanly on MySQL container and SQLite in-memory test DB.
2. [ ] `UserLlmSetting` model tests pass (encryption, masking, unique constraint).
3. [ ] `LlmClientResolver` successfully instantiates Anthropic, OpenAI, and Gemini clients with proper credentials and falls back to system `.env` defaults.
4. [ ] All backend endpoints verified with HTTP status codes and validation handling.
5. [ ] Frontend page `Settings/LlmSettings` renders cleanly with light/dark theme support and zero console errors.
6. [ ] Unified verification gate (`composer verify`, `npm run typecheck`, `npm run build`) passes 100%.

