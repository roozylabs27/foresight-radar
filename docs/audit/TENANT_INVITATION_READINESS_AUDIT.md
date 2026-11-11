# Tenant Isolation & Invitation Flow — Production Readiness Audit

**Date:** 2026-10-26 · **Verdict:** **NOT READY**

## 1. Executive summary

The data-plane scoping engine (`TenantScope` + `BelongsToTenant` + `EnforceTenantContext`) works for the
ordinary Eloquent read and route-binding paths. The audit found no way to *read another tenant's domain
records* (signals, sources, driving forces, scenarios) through the main list, detail, or export endpoints.

The control plane around it does not hold up:

1. **No authorization on any tenant-management route.** The lowest role (`bod`) can make itself admin,
   invite admins, remove admins, and rewrite the organization profile (INVITE-001, TENANT-004).
2. **"Invite" takes over accounts.** Typing the email of any account that has no tenant (platform
   `super-admin`/`developer`, ex-members, self-registered users) moves that account into the caller's
   tenant and **replaces its roles**, which removes platform roles too (INVITE-002, Critical).
3. **There is no invitation lifecycle.** An invite creates an active account straight away with a
   temporary password, marks the invitation accepted in the same request, and sends no email. No
   acceptance route exists, and the token is never used (INVITE-003). The password goes into the
   inviter's session flash (INVITE-004).
4. **Cross-tenant leaks and writes outside the scoping engine:** the `/driving-force` page lists admins
   from every tenant (TENANT-001). Unscoped `exists:` validation lets a user link another tenant's
   driving force (TENANT-005). A suspended or deleted tenant keeps full JSON API access (TENANT-003).
   Queue workers carry a tenant context left over from the previous job (TENANT-002).

Of 20 reproduction tests, **16 fail against the secure requirement** and 4 positive controls pass (§8).
Verdict: **Not ready.** Fix P0 and P1 before any multi-tenant production exposure.

## Remediation status (updated 2026-10-26)

| Finding | Status | Change | Regression guard |
|---|---|---|---|
| INVITE-002 | **Fixed** | `invite` rejects every existing account (any tenant, no tenant, soft-deleted); the direct-attach branch is gone | `TenantMemberAuthorizationTest::test_invite_does_not_absorb_platform_superuser`, `::test_invite_does_not_attach_unassigned_user` |
| INVITE-001 | **Fixed** | new `manage-members` permission (admin, super-admin, developer) on invite / role / remove / cancel routes; role change and removal also clear direct permission grants | `::test_bod_member_cannot_{invite_members,promote_self_to_admin,remove_admin,cancel_invitation}`, `::test_demoted_admin_loses_directly_granted_permissions` |
| TENANT-004 | **Fixed** (authorization only) | new `manage-organization` permission on `PATCH /settings/organization`; `settings` keys are still not whitelisted | `::test_bod_member_cannot_update_organization_profile` |
| TENANT-001 | **Fixed** | `/driving-force` PIC options and the `pic_id` rule (create and update) are limited to users of the caller's tenant; only the platform bypass (no tenant id) still sees every tenant, see TENANT-007 | `DrivingForcePicTenantScopeTest` (4 tests) |
| TENANT-003 | **Fixed** (requests only) | `EnforceTenantContext` checks tenant status on every request, JSON included; soft-deleted tenants resolve to null and are denied. Costs one tenant lookup per JSON request. Queued jobs check status too since the TENANT-002 fix | `TenantStatusEnforcementTest` (4 tests) |
| INVITE-003 | **Fixed** | `TenantInvitationService`: invite creates only a pending invitation; queued `TenantInvitationNotification` (afterCommit, 3 tries); guest-only signed route `GET/POST /invitations/{id}/accept?token=`; acceptance re-checks token hash, single use, expiry, tenant status under `lockForUpdate`, then creates the account with the invited email (marked verified), tenant and role, and logs in. Re-inviting a pending email rotates the token and resends. Invalid states render `Auth/AcceptInvitation` with 410 (or 403 when signed in), no redirects | `TenantInvitationLifecycleTest` (13 tests) |
| INVITE-004 | **Fixed** | no temporary password; the success message says the email was *scheduled*, not delivered | `TenantInvitationLifecycleTest::test_invite_creates_pending_invitation_and_queues_email_without_creating_account` |
| INVITE-005 | **Fixed** | invite runs in a transaction with the tenant row locked for the capacity check; soft-deleted emails are rejected | `TenantMemberAuthorizationTest::test_invite_for_soft_deleted_account_is_rejected_without_invitation` |
| INVITE-007 | **Fixed** | `name` is optional end to end | `TenantInvitationLifecycleTest::test_invite_without_optional_name_succeeds` |
| INVITE-009 | **Fixed** | only `sha256(token)` is stored, compared with `hash_equals`; the raw 64-char token exists only in the emailed link and, until delivery, in the queued job payload (`jobs` / `failed_jobs`) | covered by the lifecycle tests |
| TENANT-005 | **Fixed 2026-10-30** | `App\Support\TenantRule::exists()` adds `where tenant_id = <context tenant>` to `exists:` rules on tenant-owned tables (only the platform bypass, with no tenant id, keeps the unscoped check). Used in `ReviewSignalRequest`, `CrossImpactRequest`, `ScenarioRequest`, `SimulationRequest`, `DrivingForceRequest` (replaces its private PIC rule). A foreign id now gets the same 422 as an unknown one. Remaining plain `exists:` rules target global lookups only (`dimensions`, `time_horizons`, `status_actions`, `roles`) | `TenantAwareExistsRulesTest` (5 tests); audit reproduction passes |
| INVITE-006 | **Fixed 2026-10-30** | `TenantMemberController::updateRole` and `removeMember` refuse any change that would leave the tenant without an admin (flash `error`, nothing changed). The check runs in a transaction with the tenant row locked, so two admins demoting each other at once cannot both pass. Decision: admins may still demote or remove peer admins while another admin remains | `TenantMemberAuthorizationTest::test_last_admin_cannot_demote_self`, `::test_last_admin_cannot_be_removed`, plus three positive controls; audit reproduction passes |
| TENANT-006 | **Fixed 2026-10-30** | `EnforceTenantContext` no longer maps tenant-less users to tenant 1 under `APP_ENV=testing`; the suite now sees the same 403 as production. `UserFactory` still defaults to tenant 1, which is a real tenant created by the backfill migration | `TenantIsolationTest::test_user_without_tenant_or_platform_role_is_denied`; audit positive control no longer overrides `app()['env']` |
| TENANT-009 | **Fixed 2026-10-30** | `TenantMemberController` returns 404 (not 403) for another tenant's member or invitation, the same as an unknown uuid | `TenantMemberAuthorizationTest::test_foreign_member_is_indistinguishable_from_unknown_uuid`; audit positive controls now expect 404 |
| TENANT-010 | **Fixed 2026-10-30** | Decision: invitation-only. Self-registration removed entirely (routes, `RegisteredUserController`, `Auth/Register.jsx`, `auth.allow_registration`, `AUTH_ALLOW_REGISTRATION`, `canRegister` prop, Welcome links) | `RegistrationTest` (3 tests: `/register` GET/POST 404, no account created); `PhaseZeroRemediationTest::test_sec_004_public_registration_is_not_available` |
| INVITE-008 | **Fixed 2026-10-30** | Decision: resolve at acceptance. Invite answers every email the same way except this tenant's own members; no existing account is attached or changed. The accept link shows a new `account_exists` state (HTTP 409) to the mailbox owner only. Rate limiter `tenant-invites`: 10/min per inviter, 50/hour per tenant. Such invitations still count toward `max_users` until cancelled or expired | `TenantMemberAuthorizationTest::test_invite_response_does_not_reveal_registered_emails`, `::test_invites_are_throttled_per_inviter`; `TenantInvitationLifecycleTest::test_invitation_for_existing_account_shows_conflict_on_accept_page` |
| TENANT-007 | **Fixed 2026-10-30** | Decision: operators pick a workspace. Platform users (developer / super-admin without tenant) choose a tenant in a header picker (`POST /operator/workspace`, session key `operator_tenant_id`; soft-deleted tenants rejected, suspended ones allowed for support). Without a pick, `TenantContext::isWorkspaceRequired()` makes `TenantScope` return no rows and `BelongsToTenant` refuse creates (`TenantSecurityException`, rendered as 403 with "Pilih workspace…"; `HandlesApiErrors` maps it to 403 instead of 500). The superuser bypass is no longer set by any web request; it remains for test fixtures | `OperatorWorkspaceTest` (7 tests) |
| TENANT-008 | **Fixed 2026-10-30** | Decision: tenant key + token quota. `LlmClientResolver` order: user's own key (unless the tenant has an AI setting with `allow_user_byok = false`) → tenant key (`tenant_llm_settings`, enabled) → platform key. Every client, including the platform default, is wrapped in `QuotaLimitedLlmClient`: inside a tenant context it refuses calls once `tenant_llm_usages` for the month reaches `tenants.llm_monthly_token_quota` (0 = unlimited) with `LlmException('quota_exceeded')`, so features use their existing non-AI fallback, and records reported tokens after each call. Admins set the key and policy on Settings → Organization (key write-only) and see monthly usage. `storage_quota_mb` is shown as plan information only. `tenant_llm_settings.monthly_token_usage` is superseded by `tenant_llm_usages` and unused. Related G24 fix: `ScenarioNarrativeService` checks evidence against the same client it generates with | `TenantLlmQuotaTest` (9 tests); `ScenarioTest` and `LlmSignalExtractionTest` job-level tests (queued generation and extraction use the organization key under `RunInTenantContext`); `UserLlmSettingsTest` updated |

Migration `2026_10_26_000001_add_tenant_management_permissions` creates both permissions and grants them through roles only. It must run on existing databases, or admins will get 403 on member and organization changes. The Members and Organization pages hide or disable the controls for users without the permission; the server-side gate remains the authority.

**Queued jobs (fixed 2026-10-26):**

| Finding | Change | Guard |
|---|---|---|
| TENANT-002 | `TenantContext` is registered with `scoped()`, so `queue:work` drops it before every job (`Worker::$resetScope` calls `forgetScopedInstances()`). New job middleware `App\Jobs\Middleware\RunInTenantContext` (used by `ExtractSignals` and `GenerateScenarioNarrative`) loads the job model's tenant and throws `TenantSecurityException` when it is missing, soft-deleted, or not active, so the job fails through its `failed()` handler (source / scenario marked FAILED) without calling the LLM. Otherwise it runs the job via `TenantContext::runAs()`, which restores the previous context afterwards. The inline `setTenant()` calls in both `handle()` methods are gone | `QueuedJobTenantContextTest` (6 tests) |
| TENANT-002b (found by the new tests) | On the `sync` queue a job runs inside the dispatching web request, and the old inline `setTenant()` left the job's tenant in place for the rest of that request. `runAs()` restores the request's own context | `QueuedJobTenantContextTest::test_extraction_runs_under_its_own_tenant_and_restores_previous_context` |

Not covered: jobs still do not re-check that the dispatching user is still a member or still holds the permission (they only use `userId` to pick that user's LLM settings).

**UX and browser-run follow-ups (fixed 2026-10-26):**

| Finding | Change | Guard |
|---|---|---|
| UX-001 | `Members.jsx` sends `member.uuid` to the role and remove routes; `HandleInertiaRequests` shares `flash.success` / `flash.error`; `resources/js/lib/flash-toast.js` shows them as toasts once per completed visit (global `router.on('success')`, so repeated identical messages still show and history navigation does not replay them); self-removal now flashes `error` instead of an unrendered `errors.message` | `MembersUiContractTest` (3 tests); browser: role change returned via `/settings/members/{uuid}/role`, toast shown on each of three repeats |
| E2E-001 | `app.blade.php` emits `<meta name="referrer" content="no-referrer">` on `invitations.*` routes, before the Google Fonts links, so the document policy wins over nginx's appended header | `TenantInvitationLifecycleTest::test_accept_page_document_forbids_referrers_before_any_subresource`; browser: meta present and ordered first |
| E2E-002 | Invite dialog info box describes the pending-invitation flow | browser: old "kredensial akses awal" text absent |
| E2E-003 | Accept page shows the role display name | `::test_accept_page_shows_role_display_name`; browser: "sebagai Board of Directors" |
| E2E-004 | Signed-in state offers "Keluar dari akun ini" (POST logout) | browser: button signed the admin out |

**Invitation flow residual risks:** delivery is not tracked (a failed email leaves the invitation pending; the admin re-invites to resend). The flow has not been exercised end to end in a browser or with the dev SMTP mailer. Acceptance concurrency is guarded by `lockForUpdate` plus the unique `users.email` index, but only SQLite tests ran, which do not exercise row locks. Existing accounts still cannot be invited (by design after INVITE-002).

### End-to-end browser verification (2026-10-26)

Run in the built-in browser against the Docker dev app (`http://127.0.0.1:8080`). Isolated fixtures: tenant `E2E Invite Audit` (id 2), admin `e2e-admin@invite-audit.test`, invitee `e2e-invitee@invite-audit.test`. `MAIL_MAILER` was switched to `log` for the run and restored to `smtp` afterwards; `laravel_queue` was restarted both times.

| Step | Observed | Result |
|---|---|---|
| Admin invites invitee as BOD from `/settings/members` | "Undangan Menunggu Aktivasi (1)" lists E2E Invitee, Bod, invited by E2E Admin, expires 2 Nov 2026; member count stays 1; no user row created | PASS |
| Email | `laravel_queue` processed the notification (`jobs=0`, `failed_jobs=0`); `laravel.log` holds the message "E2E Admin mengundang Anda bergabung dengan workspace E2E Invite Audit sebagai Board of Directors" with the signed link | PASS |
| Token at rest | `tenant_invitations.token` equals `sha256` of the emailed token (64 chars) | PASS |
| Link opened while signed in as admin | "Anda sedang masuk dengan akun lain", HTTP 403, nothing changed | PASS |
| Link opened as guest | `Auth/AcceptInvitation`, state `valid`, email read-only, name prefilled, HTTP 200 | PASS |
| Accept with name + password | Redirect to `/visualization/foresight-radar`, signed in as E2E Invitee in E2E Invite Audit; user tenant 2, role `bod`, email verified, no `manage-members`; invitation `accepted_at` set; exactly 1 user with that email | PASS |
| Members page as the new BOD member | 2 active members, 0 pending; no invite button, no row action menus | PASS |
| Reuse of the same link (signed out) | "Undangan sudah dipakai", HTTP 410 | PASS |
| Forged signature | 403 "Invalid signature" error page | PASS |

Issues found during the run (not fixed):
- **E2E-001 (Low, security hardening):** nginx adds `Referrer-Policy: strict-origin-when-cross-origin` to every response (`docker/nginx/default.conf:9`), so the accept page is served with `no-referrer, strict-origin-when-cross-origin` and browsers apply the last value. Cross-origin requests still get only the origin (no token), but the controller's `no-referrer` is not effective behind nginx, while `TenantInvitationLifecycleTest` asserts it at the Laravel layer. Fix: a `<meta name="referrer" content="no-referrer">` on the accept page, or a dedicated nginx `location` for `/invitations/`.
- **E2E-002 (Low, UX copy):** the invite dialog info box in `Members.jsx` still says the new member is connected immediately "dengan kredensial akses awal", which contradicts the pending-invitation flow.
- **E2E-003 (Low, UX):** the accept page shows the role key (`sebagai bod`) instead of the display name (Board of Directors), unlike the email.
- **E2E-004 (Low, UX):** the signed-in state only offers "Ke halaman masuk", which redirects a signed-in user away; it should offer a sign-out action.
- **Pre-existing:** no success message is shown after inviting (flash is not shared, UX-001); the global `/signals/extractions/active` poller fires on guest pages (login, accept) and logs 401s in the console.

## 2. Scope, branch, environment

| Item | Value |
|---|---|
| Audited code | branch `claude/tenant-isolation-audit-791e36` @ `3d25019` (tree identical to `development` @ `ff8502d`; `git diff ff8502d 3d25019` is empty) |
| Runtime | Docker `laravel_app` (PHP 8.3, mounts `C:\laragon\www\foresight-radar` → `/var/www`), tests on SQLite `:memory:` (`phpunit.xml`) |
| Inspected | `routes/web.php`, `routes/auth.php`, `bootstrap/app.php`, `app/Http/Middleware/{EnforceTenantContext,HandleInertiaRequests}.php`, `app/Scopes/TenantScope.php`, `app/Traits/BelongsToTenant.php`, `app/Services/TenantContext.php`, `app/Http/Controllers/Settings/*`, `UserController`, `DrivingForceController`, `SignalIngestionController`, `Auth/RegisteredUserController`, `ProfileController`, `app/Http/Requests/*`, `app/Policies/UserPolicy.php`, `app/Jobs/*`, `app/Services/{SignalExtractionService,ScenarioService,ForesightReportingService}.php`, `app/Services/Llm/*`, all models, tenant migrations, `database/data/role.json`, `resources/js/Pages/Settings/{Members,Organization}.jsx`, `docker-compose.yml` |
| Not executed | `./init.sh`, browser/E2E flows, MySQL-level concurrency, load tests (see §11) |

## 3. Architecture as implemented

**Tenant model:** `users.tenant_id` is nullable (NULL means platform user or detached account). Domain
tables carry a nullable `tenant_id` with a `cascadeOnDelete` FK. Roles are global Spatie roles with no
teams. Tenant roles in use: `admin` (25 permissions), `bod` (11). Platform roles: `developer`, `super-admin`.

**Request path:** `web` group → `EnforceTenantContext` (prioritised before `SubstituteBindings`) →
`TenantContext` singleton → `TenantScope` adds `WHERE tenant_id = ?`. With no context it adds `0 = 1` in
web requests and **no filter at all in console/queue** (`TenantScope.php:24`). Platform roles with NULL
tenant get `superuserBypass`, which removes the filter entirely, and their creates are stamped
`tenant_id = 1` (`BelongsToTenant.php:24-26`).

**Invitation state machine (actual):**

```
POST /settings/members/invite
 ├─ email belongs to this tenant ─────────────► error
 ├─ email belongs to another tenant ──────────► error (discloses that the account exists)
 ├─ members + pending ≥ max_users ────────────► error
 ├─ email has user with tenant_id NULL ───────► user.tenant_id := caller tenant; syncRoles([role])   (no invitation row)
 └─ otherwise
      updateOrCreate(invitation, accepted_at = NULL, token = Str::random(40))
      User::create(temp password)               ← may throw → invitation left "pending"
      invitation.accepted_at := now()           ← "accepted" without recipient action
DELETE /settings/members/invitations/{id} ─────► hard delete (only reachable while pending)
```

Not implemented: invitation email, acceptance URL or route, token validation, recipient identity binding,
resend, expiry handling (expiry only filters the list), audit log.

## 4. Tenant isolation & authorization matrix

| Path | AuthN | Server-side authZ | Tenant scoping | Result |
|---|---|---|---|---|
| Domain list/detail/export (signals, DF, scenarios, reports) | ✔ | `->can(permission)` | Eloquent global scope; route binding scoped | ✔ isolated (existing `TenantIsolationTest`, 6 tests) |
| `GET /driving-force` PIC options | ✔ | `view-driving-force` | **`User` is not tenant-scoped** | ✘ TENANT-001 |
| `POST/PUT /driving-force` `pic_id` | ✔ | ✔ | `exists:users,id` unscoped | ✘ TENANT-001 |
| `POST /signals/{signal}/review` `driving_force_id` | ✔ | `review-signal` | `exists:driving_forces,id` unscoped, pivot has no tenant_id | ✘ TENANT-005 |
| JSON endpoints for a suspended or soft-deleted tenant | ✔ | ✔ | status check skipped when `expectsJson()` | ✘ TENANT-003 |
| `/settings/members/*` | ✔ | **none** | handler compares tenant ids | ✘ INVITE-001 (cross-tenant blocked ✔) |
| `/settings/organization` PATCH | ✔ | **none** | context tenant | ✘ TENANT-004 |
| `/settings/llm` & `/settings/ai` | ✔ | per-user | `UserLlmSetting` scoped + `user_id` filter | ✔ (no tenant-level config is used, TENANT-008) |
| `/user-management/*` | ✔ | `view-user`… (platform roles only) | User unscoped by design | ✔ platform-only; tenant admins lack the permission |
| Queue jobs | n/a | dispatch-time only | context set from model, **never reset** | ✘ TENANT-002 |
| Removed member, next request | ✔ | middleware 403 when `tenant_id` is NULL (non-testing env) | — | ✔ positive control |

## 5. Invitation lifecycle matrix

| Scenario | Behaviour | Status |
|---|---|---|
| Authorized actor only | Any authenticated tenant user | ✘ INVITE-001 |
| Recipient consent / acceptance | None; account active immediately | ✘ INVITE-003 |
| Email delivered | Nothing sent; temp password only in session flash, which `HandleInertiaRequests` does not share, so the UI never shows it | ✘ INVITE-003/004 |
| Existing user in another tenant | Rejected, and the message reveals it | ✔ / enumeration INVITE-008 |
| Existing user without a tenant (incl. super-admin) | Silently absorbed, roles replaced | ✘ INVITE-002 |
| Already a member | Rejected | ✔ |
| Duplicate pending invite, same tenant | `updateOrCreate` on non-unique `(tenant_id,email)` index | ⚠ race not DB-guarded |
| Same email, two tenants | First invite creates the user; the second is rejected as "other tenant" | ✔ by side effect |
| Token: entropy / storage / single use | `Str::random(40)` (CSPRNG, ~238 bits), stored plaintext, unique index, **never read** | ⚠ INVITE-009 |
| Expired | Hidden from list; no transition | n/a |
| Revoke | Hard delete; cross-tenant 404 ✔ | ✔ |
| Resend | Not implemented | — |
| Concurrent accept | No accept path; capacity check is read-then-write | ⚠ unmeasured |
| Soft-deleted user holds email | `User::create` unique violation → 500, invitation left pending and counted in quota | ✘ INVITE-005 |
| `name` omitted (API client) | 500 `Undefined array key "name"`, orphan invitation | ✘ INVITE-007 |
| Last admin demotes self | Allowed | ✘ INVITE-006 |
| Role change / remove from UI | UI sends numeric `id`, route binds `uuid` → 404 | ✘ UX-001 |

## 6. Threat model

- **Assets:** tenant domain data, membership (`users.tenant_id`), role assignments, platform operator
  accounts, invitation records, temporary credentials, organization profile and settings, LLM keys.
- **Trust boundaries:** browser ↔ Laravel (session, CSRF, Inertia props); Laravel ↔ MySQL; web ↔
  `laravel_queue` worker (long-lived process with a shared singleton); Laravel ↔ LLM provider. There is no
  email provider in the flow.
- **Actors and realistic paths:**
  - *Tenant `bod` member* → self-promotes to admin (INVITE-001) → absorbs platform super-admins and
    strips their roles (INVITE-002) → locks the operator out and holds the accounts. **Highest risk**: a
    single authenticated low-privilege user and two requests.
  - *Tenant admin* → harvests the names and IDs of every other tenant's admins (TENANT-001). Enumerates
    registered emails platform-wide (INVITE-008). Plants cross-tenant pivot rows (TENANT-005).
  - *Suspended tenant user* → keeps reading and mutating data, and running LLM extraction, through the
    JSON endpoints (TENANT-003).
  - *Inviter* → knows the invitee's password (INVITE-004) and can act as them until they reset it.
  - *Queue worker* → a job runs under the wrong tenant when its tenant row is gone (TENANT-002).

## 7. Findings

Confidence **Confirmed** means a failing reproduction test in
`tests/Audit/TenantInvitationReadinessAuditTest.php`, unless noted otherwise.

### INVITE-002 — Invite absorbs tenant-less accounts and strips platform roles · **Critical** · Confirmed · **Fixed 2026-10-26**
- **Where:** `app/Http/Controllers/Settings/TenantMemberController.php:150-157`, route `POST /settings/members/invite`.
- **Preconditions:** any authenticated tenant user (with INVITE-001), and a target account with `tenant_id`
  NULL: platform `super-admin`/`developer` (`database/data/user.json` seeds them NULL), removed members
  (`removeMember` sets NULL), or self-registered users.
- **Impact:** the target is moved into the attacker's tenant and `syncRoles` **replaces** `super-admin`
  with `bod`/`admin`. Platform operators lose their platform role, and the attacker's tenant gets the account.
  The target is never asked.
- **Proof:** `test_tenant_admin_cannot_absorb_platform_superuser_by_inviting_their_email` fails
  (`Failed asserting that 2 is null`), and `test_unassigned_user_is_not_attached_without_accepting` fails.
- **Root cause:** a shortcut treats "no tenant" as "free to claim".
- **Fix:** remove the direct-attach branch. Never touch users who hold platform roles. A NULL-tenant user
  may join only by accepting an invitation as themselves.
- **Acceptance:** both tests pass; a platform user's roles and `tenant_id` are unchanged by any tenant endpoint.

### INVITE-001 — Tenant-management routes have no authorization · **High** · Confirmed · **Fixed 2026-10-26**
- **Where:** `routes/web.php:196-202` (no `->can()`/policy); handlers check only the tenant match
  (`TenantMemberController.php:189-238`). The UI hides only the actions on your own row (`Members.jsx:163`).
- **Impact:** a `bod` user can promote itself to admin, invite admins, remove admins, and cancel
  invitations. This is vertical privilege escalation inside the tenant, and the entry point for INVITE-002.
- **Proof:** `test_bod_member_cannot_invite_members`, `..._promote_self_to_admin`, and `..._remove_admin`
  each return 302 (success) instead of 403.
- **Fix:** add a `TenantMemberPolicy` (or `manage-members` permission given to `admin` only) and apply it on
  the routes and in the handlers. Expose the ability in Inertia props so the UI can hide the controls.
- **Acceptance:** all three tests pass; an admin still passes `TenantManagementTest`.

### TENANT-001 — PIC picker exposes every tenant's admins; PIC is assignable across tenants · **High** · Confirmed · **Fixed 2026-10-26**
- **Where:** `app/Http/Controllers/DrivingForceController.php:30-38` (`User::whereHas('roles', admin)` with no
  tenant filter); `app/Http/Requests/DrivingForceRequest.php:31,38` (`exists:users,id`).
- **Impact:** any user with `view-driving-force` gets the name and internal ID of every admin in every
  tenant in the page props. They can then set such a user as PIC, and the name is rendered back through
  `DrivingForceResource:135`, `ForesightRadarResource:102`, and others.
- **Proof:** `test_driving_force_pic_options_exclude_other_tenants_users`: the props contain
  `Zed Secret Admin B - Admin`.
- **Root cause:** `User` does not use `BelongsToTenant`, so every `User::` query needs a manual
  `tenant_id` filter, and this one has none.
- **Fix:** filter by `TenantContext` tenant. Use `Rule::exists('users','id')->where('tenant_id', …)`.
  Consider a `User::scopeInTenant()` helper and a lint/grep check for unscoped `User::` queries.
- **Acceptance:** test passes; a PIC id from another tenant fails validation.

### TENANT-003 — Suspended or deleted tenants keep JSON API access · **High** · Confirmed · **Fixed 2026-10-26 (requests)**
- **Where:** `app/Http/Middleware/EnforceTenantContext.php:39` checks status only when `! expectsJson() || X-Inertia`.
- **Impact:** the whole axios/JSON surface keeps working after suspension or soft-delete: `/*/fetch-data`,
  `/signals/ingest` (LLM spend), DF create/update, cross-impact scoring. Suspension does not contain anything.
- **Proof:** `test_suspended_tenant_is_blocked_on_json_endpoints` returns 200.
- **Fix:** check the status on every request. Cache the tenant per request; the lookup is one query.
- **Acceptance:** test passes for both suspended and soft-deleted tenants.

### TENANT-002 — Queue worker reuses tenant context across jobs · **Medium** · Confirmed (mechanism) · **Fixed 2026-10-26**
- **Where:** `TenantContext` is a `singleton` (`AppServiceProvider.php:20`). The jobs set it but never
  reset it (`ExtractSignals.php:46-51`, `GenerateScenarioNarrative.php:48-53`). The worker is a long-lived
  `queue:work` (`docker-compose.yml:23`).
- **Impact:** if `Tenant::find()` returns null (tenant soft-deleted after dispatch), the job runs under the
  previous job's tenant. `Signal::create` then stamps that tenant's id, which is a cross-tenant write. With
  no previous context, the console path has no scope at all, and creates fall back to tenant 1
  (`BelongsToTenant.php:24-26`). Jobs also do not check tenant status (TENANT-003) or whether the
  dispatching user is still a member or still has permission.
- **Proof:** `test_job_never_runs_under_previous_jobs_tenant_context`: the service sees tenant A while
  processing tenant B's source. Positive control: model restoration itself is unaffected, because
  restoration queries run without global scopes.
- **Fix:** register `TenantContext` as `scoped()` (the worker flushes scoped instances between jobs), or
  reset it in a job middleware. Fail the job when its tenant is missing or inactive.
- **Acceptance:** test passes; a job for a deleted or suspended tenant fails without writing anything.

### TENANT-005 — Unscoped `exists:` rules allow cross-tenant pivot writes · **Medium** · Confirmed · **Fixed 2026-10-30**
- **Where:** `app/Http/Requests/ReviewSignalRequest.php:26`, used at `SignalIngestionController.php:265-273`
  (`syncWithoutDetaching([$dfId])`). Same pattern: `DrivingForceRequest:31,38`, `CrossImpactRequest:18-19`,
  `ScenarioRequest:22-23`, `SimulationRequest:21`. Those last services re-resolve through scoped Eloquent,
  so for them it is only an existence oracle.
- **Impact:** a `signal_driving_force` row links tenant A's signal to tenant B's force. The pivot has no
  `tenant_id`, so the database cannot reject it. Scoped relations hide the row today, so this is an
  integrity breach and an ID oracle rather than a read leak.
- **Proof:** `test_signal_cannot_be_linked_to_other_tenants_driving_force`: the pivot row exists.
- **Fix:** use tenant-aware `Rule::exists(...)->where('tenant_id', …)`, or resolve the force through
  `DrivingForce::findOrFail` (scoped) before linking.

### TENANT-004 — Any member can rewrite the organization profile · **Medium** · Confirmed · **Authorization fixed 2026-10-26**
- **Where:** `routes/web.php:190-193`, `OrganizationController.php:76-92`. `settings` accepts any array, and
  `domain` is validated `unique:tenants`.
- **Impact:** a `bod` user can rename the tenant and overwrite `settings`. The `domain` uniqueness error
  tells the caller which domains other tenants have claimed, and a user can squat a domain.
- **Proof:** `test_bod_member_cannot_update_organization_profile` returns 302.
- **Fix:** admin-only policy; whitelist the `settings` keys.

### INVITE-003 — There is no invitation lifecycle; accounts are activated by the inviter · **High** · Confirmed · **Fixed 2026-10-26**
- **Where:** `TenantMemberController.php:159-183`; no accept route in `routes/web.php` or `routes/auth.php`.
- **Impact:** the recipient never consents, there is no proof they control the mailbox, and the email is
  never verified. `User` is not `MustVerifyEmail`, so the `verified` middleware does nothing. Nothing is
  pending, so expiry, revoke, resend, and reuse do not exist in practice. The token is dead data. With
  nothing sent and no flash rendering, the new user's only way in is `forgot-password`.
- **Proof:** `test_invite_creates_pending_invitation_without_active_account` fails, because `accepted_at`
  is set in the same request.
- **Fix (design):** `pending → accepted | revoked | expired`. Store a hashed token. Queue the email
  (`ShouldQueue` notification, `URL::temporarySignedRoute`). Accept means: authenticated or registering
  user, normalized email match, verified email, then a single DB transaction with
  `lockForUpdate` on the invitation and a `tenant_id IS NULL` guard on the user.
  Add a unique key on pending `(tenant_id, email)`.

### INVITE-004 — Recipient's temporary password goes to the inviter's session · **Medium** · Confirmed · **Fixed 2026-10-26**
- **Where:** `TenantMemberController.php:173-183`. Sessions are file-backed (`SESSION_DRIVER=file`).
- **Impact:** a plaintext credential sits in session storage, and the inviter knows the user's password,
  with no forced rotation.
- **Proof:** `test_invite_does_not_disclose_recipient_password_to_inviter` (message contains `Kata sandi sementara: …`).
- **Fix:** removed by INVITE-003. Never generate passwords for others.

### INVITE-005 — Invite is not atomic; failures leave quota-consuming orphans · **Medium** · Confirmed · **Fixed 2026-10-26**
- **Where:** `TenantMemberController.php:160-181` (no transaction). The capacity check at `:140-148` is
  read-then-write.
- **Impact:** when the email belongs to a soft-deleted user (for example after `ProfileController::destroy`),
  the unique violation gives a 500 and leaves a pending invitation. The admin cannot remove it, because the
  UI list shows it but the 500 repeats on every retry. It also counts against `max_users`. Concurrent
  invites can exceed `max_users`.
- **Proof:** `test_invite_for_soft_deleted_email_fails_cleanly_without_orphan_invitation` returns 500.
- **Fix:** run inside `DB::transaction`; check `withTrashed()`; lock the tenant row for the capacity check.

### INVITE-006 — No admin invariant · **Medium** · Confirmed · **Fixed 2026-10-30**
- **Where:** `TenantMemberController.php:189-203`.
- **Impact:** the last admin can demote themselves, leaving the tenant with no one who can manage it. Any
  admin can demote any other admin.
- **Proof:** `test_last_admin_cannot_demote_self` fails.
- **Fix:** reject changes that leave zero admins; decide whether admins may demote peers.

### UX-001 — Role change and remove from the Members UI return 404 · **Medium** (functional) · Confirmed
- **Where:** `resources/js/Pages/Settings/Members.jsx:174,177,183` pass `member.id`. `User::getRouteKeyName()`
  is `uuid` (`User.php:148`). `TenantManagementTest` passes the model, so it never exercises the
  identifier the UI sends.
- **Proof:** `test_member_actions_accept_the_identifier_the_ui_sends` returns 404.
- **Fix:** send `member.uuid`. Add an E2E or contract test. Also share `flash` in `HandleInertiaRequests`,
  so success and errors are actually shown. Today `back()->with('success')` is never rendered.

### INVITE-007 — Omitting optional `name` returns 500 · **Low** · Confirmed · **Fixed 2026-10-26**
- **Where:** `TenantMemberController.php:176` reads `$validated['name']` without `??`. The Inertia form
  always sends `name`, so only API clients hit this. It leaves an orphan invitation, as in INVITE-005.
- **Proof:** `test_invite_without_optional_name_succeeds_atomically` returns 500.

### INVITE-008 — Platform-wide account enumeration · **Low** · Confirmed (existing test) · **Fixed 2026-10-30**
- **Where:** `TenantMemberController.php:133-137`. `test_inviting_member_belonging_to_another_tenant_is_rejected`
  asserts the distinct error.
- **Fix:** return a uniform "invitation sent" response, and resolve conflicts at acceptance. Add a
  per-tenant throttle on invite (only the `throttle:120,1` group applies now).

### INVITE-009 — Token stored in plaintext and unused · **Informational**
`tenant_invitations.token` (`2026_10_25_000004…:20`) holds a 40-character `Str::random` value, which has
enough entropy. It is not hashed and never read. Hash it when INVITE-003 is implemented, and keep it out of
Inertia props (it is not exposed today).

### TENANT-006 — The test harness masks fail-closed behaviour · **Low** · Confirmed · **Fixed 2026-10-30**
`EnforceTenantContext.php:29-31` maps NULL-tenant users to tenant 1 when `APP_ENV=testing`, and
`UserFactory` defaults `tenant_id => 1`. So the suite cannot catch "no tenant → 403" regressions, and the
existing removal test only checks the DB column. The positive control
`test_removed_member_is_denied_outside_testing_environment` had to override `app()['env']`.
**Fix:** remove the testing branch and give fixtures explicit tenants.

### TENANT-007 — Platform bypass merges every tenant and writes into tenant 1 · **Low / design** · Confirmed (code) · **Fixed 2026-10-30**
`superuserBypass` removes all scoping (`TenantScope.php:16-18`). Dashboards, reports, and exports for
`developer`/`super-admin` therefore mix every tenant's data, and their creates land in tenant 1
(`BelongsToTenant.php:24-26`). Decide whether this is intended. If it is, give operators an explicit
tenant switch instead of a global merge.

### TENANT-008 — Tenant quotas and tenant LLM config are not enforced · **Informational** · **Fixed 2026-10-30 (LLM; storage display-only)**
`llm_monthly_token_quota`, `storage_quota_mb`, and `tenant_llm_settings` are never read outside
models/display. LLM calls fall back to the platform key from `.env` for every tenant, with no per-tenant
spend cap.

### TENANT-009 — 403 vs 404 existence oracle on `{user}` · **Low** · **Fixed 2026-10-30**
`User` route binding is unscoped, so a foreign UUID returns 403 and an unknown one returns 404
(`TenantMemberController.php:192,211`). Low risk, because UUIDs are not guessable.

### TENANT-010 — Registration creates tenant-less accounts · **Informational** · **Fixed 2026-10-30 (registration removed)**
`AUTH_ALLOW_REGISTRATION` defaults to false. If it is enabled, `RegisteredUserController::store` creates
users without a tenant: they get 403 everywhere in production and can be claimed through INVITE-002.

## 8. Test coverage and execution results

New reproduction file: `tests/Audit/TenantInvitationReadinessAuditTest.php`. It sits outside the
`Unit`/`Feature` suites, so `composer verify` stays green. Run it with `php artisan test tests/Audit`.
Identities: tenant A (`adminA`, `bodA`), tenant B (`adminB`), platform `super-admin`, and an unassigned user.

| Test | Finding | Result |
|---|---|---|
| bod_member_cannot_invite_members | INVITE-001 | ✘ 302 |
| bod_member_cannot_promote_self_to_admin | INVITE-001 | ✘ 302 |
| bod_member_cannot_remove_admin | INVITE-001 | ✘ 302 |
| bod_member_cannot_update_organization_profile | TENANT-004 | ✘ 302 |
| tenant_admin_cannot_absorb_platform_superuser… | INVITE-002 | ✘ tenant_id set |
| unassigned_user_is_not_attached_without_accepting | INVITE-002 | ✘ |
| invite_creates_pending_invitation_without_active_account | INVITE-003 | ✘ accepted_at set |
| invite_does_not_disclose_recipient_password_to_inviter | INVITE-004 | ✘ |
| invite_for_soft_deleted_email_fails_cleanly… | INVITE-005 | ✘ 500 |
| last_admin_cannot_demote_self | INVITE-006 | ✘ |
| driving_force_pic_options_exclude_other_tenants_users | TENANT-001 | ✘ |
| suspended_tenant_is_blocked_on_json_endpoints | TENANT-003 | ✘ 200 |
| job_never_runs_under_previous_jobs_tenant_context | TENANT-002 | ✘ |
| signal_cannot_be_linked_to_other_tenants_driving_force | TENANT-005 | ✘ |
| member_actions_accept_the_identifier_the_ui_sends | UX-001 | ✘ 404 |
| invite_without_optional_name_succeeds_atomically | INVITE-007 | ✘ 500 |
| queue_worker_restores_job_for_a_different_tenant… | control | ✔ |
| removed_member_is_denied_outside_testing_environment | control | ✔ |
| admin_cannot_change_role_of_other_tenant_member | control | ✔ |
| admin_cannot_cancel_other_tenant_invitation | control | ✔ |

`Tests: 16 failed, 4 passed (34 assertions), Duration: 135.10s`
(`docker exec laravel_app php artisan test /tmp/audit/TenantInvitationReadinessAuditTest.php`).

**Baseline (unchanged code):**

| Command | Result |
|---|---|
| `vendor/bin/pint --test` | PASS, 280 files |
| `vendor/bin/phpstan analyse --memory-limit=2G` | `[OK] No errors` |
| `php artisan test` | 290 passed (2680 assertions), 407 s |
| `npm run build` | built in 1m 29s |
| `npm audit --audit-level=critical` | exit 0, 0 critical (8 total: 3 moderate, 5 high) |

**Critical missing regression tests:** members and organization authorization per role; the
platform-account guard; tenant-scoped PIC options and `exists` rules; JSON status enforcement; queue
context reset; UI identifier contract; and, once INVITE-003 exists, accept/expire/revoke/reuse/wrong-recipient
plus concurrent acceptance on MySQL (SQLite cannot exercise `lockForUpdate`).

## 9. Performance and reliability

- Members index: `with('roles')` + paginate (cap 100). This is a constant query count by reading the code;
  query counts were not measured.
- Invitations list: unbounded `->get()`. Currently always empty because of INVITE-003, but it needs
  pagination once pending invitations exist.
- `users.tenant_id` has an FK index. `tenant_invitations` has a non-unique `(tenant_id,email)` index, so
  nothing at the DB level prevents duplicate pending invitations.
- Invite: about 8 queries, no transaction (INVITE-005).
- `HandleInertiaRequests` runs an `active_extractions` query on every authenticated Inertia request
  (pre-existing).
- No email or queue retry behaviour exists for invitations, so there is nothing to measure.
- Load and concurrency: **not measured.**

## 10. Remediation backlog

**P0 — block release**
- [x] INVITE-002: remove the direct attach of NULL-tenant users; protect platform-role accounts.
      *AC:* both INVITE-002 tests pass.
- [x] INVITE-001 + TENANT-004: `manage-members` / `manage-organization` (admin only) on routes and handlers.
      *AC:* the four `bod_*` tests pass; admins still pass `TenantManagementTest`.

**P1**
- [x] TENANT-001: tenant-filter the PIC options and `pic_id` validation. *AC:* PIC test passes.
- [x] TENANT-003: enforce tenant status for JSON requests. *AC:* JSON suspended test passes.
- [x] INVITE-003/004: real invitation lifecycle (hashed token, queued notification, signed accept route,
      email match, transactional acceptance, pending-unique constraint). *AC:* pending test passes, plus
      new accept/expire/revoke/reuse/wrong-user tests.

**P2**
- [x] TENANT-002: `scoped()` TenantContext or job middleware, and fail on a missing or inactive tenant.
- [x] TENANT-005: tenant-aware `exists` rules across all Form Requests.
- [x] INVITE-005/007: transactional invite, `withTrashed` check, locked capacity check.
- [x] INVITE-006: keep at least one admin.
- [ ] UX-001: send `uuid` from `Members.jsx`; share `flash`; add a Playwright smoke test for the members page.
- [x] TENANT-006: remove the testing fallback in `EnforceTenantContext`; explicit tenants in factories.

**P3**
- [x] INVITE-008: uniform responses and an invite throttle.
- [x] INVITE-009: hash tokens.
- [x] TENANT-007: an explicit operator tenant switch.
- [x] TENANT-008: enforce quotas or remove them.
- [x] TENANT-009: 404 for foreign users.
- [ ] Audit log for membership and role changes.

## 11. Limitations and unverified assumptions

- `./init.sh` was not run. The running Docker stack was used as is.
- **No browser verification was performed.** Chrome DevTools MCP was not available in this session, and
  running invite flows against the shared dev database would have created real accounts. UX-001 is proven
  at the HTTP layer only.
- Concurrency (double accept, racing invites) was not executed. SQLite in-memory cannot reproduce MySQL locking.
- Not covered in depth: password-reset and email-verification flows, Sanctum token routes (no
  `routes/api.php`), and cache keys. Cache is file-based, and the code has no `Cache::` calls.
- The impact of INVITE-002 on the live environment assumes seeded platform users still have `tenant_id`
  NULL, as `database/data/user.json` defines. This was not checked against the dev database.
- The absence of other findings is not proof of isolation. Only the paths listed in §2 were traced.
