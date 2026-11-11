# Tenant Isolation & Data Integrity (2026-11-11)

## Ownership model

- `App\Traits\BelongsToTenant` adds `App\Scopes\TenantScope` (filter on `tenant_id`; a web request without tenant context sees nothing) and fills `tenant_id` on create from `App\Services\TenantContext` (console fallback: tenant 1).
- Tenant-scoped models (28): AiGenerationRun, BackcastingMilestone, Comment, CriticalUncertainty, CrossImpact, Dimension, DrivingForce, DrivingForceRating, DrivingForceRatingHistory, EarlyWarningIndicator, Environment, ForceAssessment, ForceCandidate, IndicatorObservation, ProjectDecision, ProjectDrivingForce, ProjectParticipant, Scenario, ScenarioCitation, ScenarioCondition, ScenarioImplication, ScenarioPlanningProject, ScenarioQuadrant, Signal, Source, StrategicResponse, TenantInvitation, UserLlmSetting.
- Global lookups (no tenant): Priority, StatusAction, TimeHorizon. Pivot children reached only through a scoped parent: ActionReason, IndicatorScenario, ResponseScenario. Explicitly tenant-keyed: TenantLlmSetting, TenantLlmUsage.
- `User` is not tenant-scoped (see AUD-14).

## Isolation review (no P0)

| Area | Result | Evidence |
|---|---|---|
| Route model binding of child records | Safe: child models are tenant-scoped; `scopeBindings()` on most exercise groups; framework, conditions, implications, compare and legacy scenarios check parentage by hand | `routes/web.php:118-225`; `ScenarioConditionController.php:80,130`; `ScenarioImplicationController.php:69,113` |
| Relationship injection (validation) | Safe: no plain `exists:` on tenant tables; remaining plain rules target global tables (time horizons, status actions, roles, tenants for operators) | `App\Support\TenantRule`; `TenantRule::activeDimension()` (2026-11-11) |
| Bare UUID references in services | Resolved through tenant scope and the exercise | `StrategicResponseService.php:198,204`; `EarlyWarningIndicatorService.php:313,321` |
| Queued jobs | All 12 use `RunInTenantContext` (refuses missing or inactive tenants) | e.g. `app/Jobs/ExtractSignals.php:49` |
| Caches | No `Cache::` use in `app/`; rate limiters keyed by user or tenant | `RouteServiceProvider.php:27-37` |
| Exports | Registered List export streams tenant-scoped data and resolves the exercise scope through the tenant scope | `RegisteredListController::export_data`; `App\Support\ReportScope` |
| Report scope | A Key Focal Issue of another tenant is 404; using a scope needs `view-scenario` (403) | `tests/Feature/Reports/ReportScopeTest.php` |
| Dimensions | Per organisation; other organisations' ids refused; settings routes bound through the tenant scope | `tests/Feature/Taxonomy/TenantDimensionsTest.php`, `tests/Feature/Settings/DimensionSettingsTest.php` |

Hardening items: AUD-14 (User scope, platform roles only), AUD-15 (email uniqueness disclosure), AUD-16 (`failed()` handlers outside tenant context).

## Dimension references (complete)

| Column | Delete rule | Counted as "in use" |
|---|---|---|
| `driving_forces.dimension_id` | RESTRICT (was CASCADE before 2026-11-11) | yes |
| `signals.dimension_id` | SET NULL | yes |
| `force_candidates.dimension_id` | SET NULL | yes |

No other table references dimensions (scenarios, knowledge graph and rating history read them through driving forces). Soft-deleted forces still count (they keep the FK). `dimensions.environment_id` cascades; environment delete checks for dimensions first.

## Migration risk assessment — `2026_11_11_000001_make_dimensions_tenant_owned`

What it does:
1. Adds `tenant_id` (+ `sort_order` on environments; `archived_at`, `color` on dimensions).
2. Switches `unique(code)` to `unique(tenant_id, code)`.
3. Assigns existing rows to the first tenant.
4. Copies the taxonomy to every other tenant and remaps that tenant's `driving_forces`, `signals`, `force_candidates`.
5. Changes `driving_forces.dimension_id` to RESTRICT.

Verified:
- Fresh install: through every PHPUnit run (RefreshDatabase).
- Copy logic, idempotency: `TenantDimensionsTest::test_the_migration_copies_the_taxonomy_and_remaps_each_organisations_data`.
- Two-organisation database: the local dev database. The first run failed (copies made before the global unique index was dropped). It was fixed, then ran cleanly: 11 dimensions and 2 environments per organisation, no force or signal pointing at another organisation's dimension, 38 users unchanged.

Open risks (U2):
- **FK swap unguarded:** if the run fails after `dropForeign`, a rerun fails there.
- **Owner selection:** `min(tenants.id)` can be a soft-deleted tenant; copies are also made for soft-deleted tenants.
- **Name matching:** environments are matched by name, and dimensions without a code are matched by name, so duplicates collapse.
- **`down()` is unsafe:** it matches names without `whereNull('code')`, keeps unmatched copies (including dimensions created after the migration), and then fails on `unique(code)` or silently merges data. Until U2, treat this migration as **not reversible**.

**Update after U2 (2026-11-11):** the FK swap is guarded, the owner is the lowest organisation that is not deleted, and `down()` refuses before any change unless every organisation still has exactly the owner's taxonomy (see AUD-11 in technical-audit.md). In practice, once any organisation edits its dimensions the migration can only be undone by restoring the backup; the procedure below stays the same.
- MySQL commits schema changes implicitly, so a partial failure leaves columns in place. The column and index guards make a rerun work.

### Safe deployment procedure

1. **Back up** the production database (full dump) and confirm the restore works on a scratch instance.
2. **Check data:**
   - Count tenants (live and soft-deleted).
   - Look for duplicate dimension names or NULL codes.
   - Look for forces or signals whose dimension is missing.
   - Record per-tenant counts of forces, signals and candidates.
3. **Run on staging** with a copy of production data (several tenants), then verify:
   - each tenant has its own dimensions;
   - `SELECT count(*) FROM driving_forces f JOIN dimensions d ON d.id = f.dimension_id WHERE f.tenant_id <> d.tenant_id` = 0, and the same for signals and force_candidates;
   - per-tenant counts are unchanged.
4. **Deploy code and run `php artisan migrate --force`** in a maintenance window. Restart the queue workers (`laravel_queue`) and reload PHP-FPM.
5. **Re-run the checks** from step 3 on production. On failure, restore the backup; do not rely on `down()`.
6. **Configure an AI provider** per organisation before announcing ingest: since 2026-11-11, ingestion is refused without one.

## Data integrity notes

- AI extraction: a failed AI call leaves no partial signals (the LLM call is outside the transaction). The claim is not atomic, and QUEUED can stick (AUD-09, AUD-10; U2).
- Narratives: regeneration deletes old citations and overwrites the text (AUD-02; U3).
- Stale propagation never deletes; it flags with a reason (see methodology-alignment.md).
