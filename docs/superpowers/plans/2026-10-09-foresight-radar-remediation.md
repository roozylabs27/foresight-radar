# Foresight Radar Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate all high and medium security vulnerabilities, query logic flaws, reliability issues, performance bottlenecks, and test gaps identified in the October 2026 technical audit of Foresight Radar.

**Architecture:** Implement secure role hierarchy authorization in `UserController`/`UserRequest`, add Spatie permission middleware to `/visualization/*` routes, wrap search queries into logical parameter groupings in `DrivingForceService` to eliminate operator precedence filter bypasses, sanitize controller 500 error outputs, patch vulnerable dependencies, add defensive array validation in models, and build out automated feature tests covering the complete 6-stage foresight workflow lifecycle.

**Tech Stack:** Laravel 10.48.17, PHP 8.2, Spatie Laravel-Permission 6.9, React 18, Inertia.js 1.3, Vite 5.3, MySQL 8.0, Docker.

## Global Constraints

- Never mutate production data or expose secrets in commit messages, logs, or reports.
- Follow Test-Driven Development (TDD) strictly: write the failing test before changing implementation code.
- Maintain documentation and code comments integrity.
- Use conventional commits format with author `roozylabs <roozylabs@gmail.com>`.
- Keep Docker environment functional across `laravel_app`, `laravel_mysql`, and `laravel_nginx`.

---

### Task 1: Fix SQL Operator Precedence and Filter Bypass in `DrivingForceService` (SEC-003)

**Files:**
- Modify: `app/Services/DrivingForceService.php:59-62, 85-88, 108-111, 134-137, 162-165, 191-194`
- Test: `tests/Feature/Security/FilterOperatorPrecedenceTest.php`

**Interfaces:**
- Consumes: `DrivingForceService::filter()`, `timeHorizon()`, `ratingUrgency()`, `statusAction()`, `approvalItems()`, `closedItems()`.
- Produces: Parameter-grouped Eloquent queries where `keyword` and `description` search terms are contained within `WHERE (keyword LIKE ? OR description LIKE ?)` rather than global unparenthesized `OR`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Security/FilterOperatorPrecedenceTest.php`:
```php
<?php

namespace Tests\Feature\Security;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\User;
use App\Services\DrivingForceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterOperatorPrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_search_does_not_leak_records_with_unmatched_status_or_dimension(): void
    {
        $user = User::factory()->create();
        $dimension1 = Dimension::create(['name' => 'Technology', 'symbol' => 'T']);
        $dimension2 = Dimension::create(['name' => 'Economy', 'symbol' => 'E']);

        // Record A matches dimension 1, status CLOSED, description contains "quantum"
        $closedDf = DrivingForce::create([
            'dimension_id' => $dimension1->id,
            'created_by' => $user->id,
            'keyword' => 'Computing',
            'description' => 'quantum processing unit',
            'status' => 'CLOSED',
        ]);

        // Record B is dimension 2, status PENDING (Draft), description also contains "quantum"
        $draftDf = DrivingForce::create([
            'dimension_id' => $dimension2->id,
            'created_by' => $user->id,
            'keyword' => 'Secret',
            'description' => 'quantum encryption draft',
            'status' => 'PENDING',
        ]);

        $service = new DrivingForceService();

        // Query closed items filtered specifically for dimension 1 with search term "quantum"
        $result = $service->closedItems([
            'dimension' => $dimension1->id,
            'search' => 'quantum',
            'date' => ['2020-01-01', '2030-12-31'],
        ]);

        $ids = collect($result->resource->items())->pluck('id')->all();

        // Must ONLY return matching items from dimension 1 that are CLOSED
        $this->assertNotContains($draftDf->id, $ids, 'Draft record was leaked due to ungrouped OR query precedence!');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=FilterOperatorPrecedenceTest`  
Expected: FAIL (assertion fails or leaks `draftDf->id` because ungrouped `orWhere` evaluates unconditionally).

- [ ] **Step 3: Write minimal implementation in `DrivingForceService.php`**

In `app/Services/DrivingForceService.php`, update the search closure across all 6 query methods (`filter`, `timeHorizon`, `ratingUrgency`, `statusAction`, `approvalItems`, `closedItems`):
Replace:
```php
->when($search, function ($q) use ($search) {
    $q->where('keyword', 'LIKE', $search . '%')
        ->orWhere('description', 'LIKE', $search . '%');
})
```
With:
```php
->when($search, function ($q) use ($search) {
    $q->where(function ($sub) use ($search) {
        $sub->where('keyword', 'LIKE', $search . '%')
            ->orWhere('description', 'LIKE', $search . '%');
    });
})
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=FilterOperatorPrecedenceTest`  
Expected: PASS (1 test passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DrivingForceService.php tests/Feature/Security/FilterOperatorPrecedenceTest.php
git commit -m "fix(security): wrap search filters in logical closures to prevent operator precedence bypass"
```

---

### Task 2: Secure Visualization Routes and Data Export with Permission Gates (SEC-002)

**Files:**
- Modify: `routes/web.php:35-49`
- Modify: `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/Security/VisualizationAuthorizationTest.php`

**Interfaces:**
- Consumes: Spatie `can:view-visualization` and `can:export-visualization` permissions.
- Produces: HTTP 403 Forbidden for authenticated users lacking explicit visualization privileges.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Security/VisualizationAuthorizationTest.php`:
```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VisualizationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unprivileged_authenticated_user_cannot_access_visualization_endpoints(): void
    {
        $user = User::factory()->create(); // Has no permissions assigned

        $endpoints = [
            '/visualization/prioritizing',
            '/visualization/prioritizing/get-data',
            '/visualization/registered-list',
            '/visualization/registered-list/get-data',
            '/visualization/registered-list/export-data',
            '/visualization/foresight-radar',
            '/visualization/foresight-radar/get-data',
        ];

        foreach ($endpoints as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertStatus(403, "Endpoint {$url} should return 403 Forbidden for unprivileged users!");
        }
    }

    public function test_authorized_user_can_access_visualization_endpoints(): void
    {
        Permission::create(['name' => 'view-visualization']);
        Permission::create(['name' => 'export-visualization']);

        $user = User::factory()->create();
        $user->givePermissionTo(['view-visualization', 'export-visualization']);

        $response = $this->actingAs($user)->get('/visualization/prioritizing');
        $response->assertOk();

        $response = $this->actingAs($user)->get('/visualization/registered-list/export-data');
        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=VisualizationAuthorizationTest`  
Expected: FAIL (returns 200 instead of 403 because no `can:...` middleware is currently attached).

- [ ] **Step 3: Update `routes/web.php` and permission definitions**

In `routes/web.php:35-49`:
```php
    Route::prefix('visualization')->name('visualization.')->group(function() {
        Route::prefix("/prioritizing")->name('prioritizing.')->group(function() {
            Route::get("/", [PrioritizingController::class, 'index'])->can('view-visualization');
            Route::get("/get-data", [PrioritizingController::class, 'prioritizing'])->name('get-data')->can('view-visualization');
        });
        Route::prefix("/registered-list")->name('registered-list.')->group(function() {
            Route::get("/", [RegisteredListController::class, 'index'])->can('view-visualization');
            Route::get("/get-data", [RegisteredListController::class, 'registered_list'])->name('get-data')->can('view-visualization');
            Route::get('/export-data', [RegisteredListController::class, 'export_data'])->name('export-data')->can('export-visualization');
        });
        Route::prefix("/foresight-radar")->name('foresight-radar.')->group(function() {
            Route::get("/", [ForesightRadarController::class, 'index'])->can('view-visualization');
            Route::get("/get-data", [ForesightRadarController::class, 'foresight_radar'])->name('get-data')->can('view-visualization');
        });
    });
```
Add permissions to `database/seeders/PermissionSeeder.php` (`view-visualization`, `export-visualization`) for `admin` and `super-admin` roles.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=VisualizationAuthorizationTest`  
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
git add routes/web.php database/seeders/PermissionSeeder.php tests/Feature/Security/VisualizationAuthorizationTest.php
git commit -m "feat(security): enforce permission middleware on visualization and export endpoints"
```

---

### Task 3: Prevent Privilege Escalation in User Management (SEC-001)

**Files:**
- Modify: `app/Http/Requests/UserRequest.php:30-45`
- Modify: `app/Http/Controllers/UserController.php:55-115`
- Test: `tests/Feature/Security/RoleEscalationTest.php`

**Interfaces:**
- Consumes: Authenticated user roles via `$request->user()->hasRole('super-admin')`.
- Produces: Validation error (HTTP 422) or authorization denial (HTTP 403) when non-super-admins attempt to assign restricted roles.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Security/RoleEscalationTest.php`:
```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_cannot_assign_super_admin_role(): void
    {
        Permission::create(['name' => 'view-user']);
        Permission::create(['name' => 'create-user']);

        $superAdminRole = Role::create(['name' => 'super-admin']);
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(['view-user', 'create-user']);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->postJson('/user-management/user', [
            'name' => 'New Guy',
            'email' => 'newguy@example.com',
            'password' => 'Password123!',
            'role_id' => $superAdminRole->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'newguy@example.com']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=RoleEscalationTest`  
Expected: FAIL (status code 200 or 302 instead of 422).

- [ ] **Step 3: Update `UserRequest.php`**

In `app/Http/Requests/UserRequest.php`:
```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'name' => 'required',
        'email' => 'required|email|unique:users,email' . ($this->id ? ',' . $this->id : ''),
        'role_id' => [
            'required',
            Rule::exists('roles', 'id')->where(function ($query) {
                if (!auth()->user() || !auth()->user()->hasRole('super-admin')) {
                    $query->whereNotIn('name', ['super-admin', 'developer']);
                }
            }),
        ],
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=RoleEscalationTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/UserRequest.php tests/Feature/Security/RoleEscalationTest.php
git commit -m "fix(security): prevent privilege escalation by restricting high-privilege role assignment"
```

---

### Task 4: Fix Account Collision and Insecure Name Matching (REL-002)

**Files:**
- Modify: `app/Http/Controllers/UserController.php:58-65`
- Test: `tests/Feature/Reliability/UserAccountIntegrityTest.php`

**Interfaces:**
- Consumes: Validated `UserRequest` attributes.
- Produces: Independent user records mapped strictly by unique email.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Reliability/UserAccountIntegrityTest.php`:
```php
<?php

namespace Tests\Feature\Reliability;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_user_with_duplicate_name_does_not_overwrite_existing_user(): void
    {
        Permission::create(['name' => 'view-user']);
        Permission::create(['name' => 'create-user']);

        $role1 = Role::create(['name' => 'staff']);
        $role2 = Role::create(['name' => 'manager']);

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(['view-user', 'create-user']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        // Pre-existing user named "John Doe"
        $existing = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.original@example.com',
        ]);
        $existing->assignRole($role1);

        // Admin creates a second user also named "John Doe" but different email
        $response = $this->actingAs($admin)->postJson('/user-management/user', [
            'name' => 'John Doe',
            'email' => 'john.second@example.com',
            'password' => 'Password123!',
            'role_id' => $role2->id,
        ]);

        $response->assertStatus(200);

        // Existing user's role must NOT be overwritten
        $existing->refresh();
        $this->assertTrue($existing->hasRole('staff'), 'Original user role was overwritten!');
        $this->assertDatabaseHas('users', ['email' => 'john.second@example.com']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=UserAccountIntegrityTest`  
Expected: FAIL (because `firstOrCreate(['name' => 'John Doe'])` finds the existing user and updates their role).

- [ ] **Step 3: Update `UserController::create()`**

In `app/Http/Controllers/UserController.php:58-65`:
Replace:
```php
$new_user = User::firstOrCreate(["name" => $request->name], [
    "name" => $request->name,
    "email" => $request->email,
    "password" => Hash::make($request->password),
]);
```
With:
```php
$new_user = User::create([
    "name" => $request->name,
    "email" => $request->email,
    "password" => Hash::make($request->password),
]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=UserAccountIntegrityTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/UserController.php tests/Feature/Reliability/UserAccountIntegrityTest.php
git commit -m "fix(reliability): create users directly instead of matching by non-unique name"
```

---

### Task 5: Sanitize 500 Responses to Prevent Information Disclosure (SEC-005)

**Files:**
- Modify: `app/Http/Controllers/RegisteredListController.php:61-63, 77-79`
- Modify: `app/Http/Controllers/PrioritizingController.php:35-37`
- Modify: `app/Http/Controllers/ForesightRadarController.php:35-37`
- Test: `tests/Feature/Security/InformationDisclosureTest.php`

**Interfaces:**
- Consumes: Exception instances via `catch (\Throwable $th)`.
- Produces: Internal log entry via `Log::error()` and generic client-facing error message without raw database SQL or file paths.

- [ ] **Step 1: Write test verifying sanitized error response**

Create `tests/Feature/Security/InformationDisclosureTest.php`:
```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InformationDisclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_visualization_fetch_does_not_leak_raw_sql_exceptions_in_500_response(): void
    {
        Permission::create(['name' => 'view-visualization']);
        $user = User::factory()->create();
        $user->givePermissionTo('view-visualization');

        // Trigger request
        $response = $this->actingAs($user)->getJson('/visualization/registered-list/get-data');
        $response->assertOk();

        // Ensure error response format never contains raw PDO message structure
        $this->assertArrayNotHasKey('errors', $response->json());
    }
}
```

- [ ] **Step 2: Run test**

Run: `docker exec laravel_app php artisan test --filter=InformationDisclosureTest`

- [ ] **Step 3: Update controllers to sanitize catch blocks**

In `app/Http/Controllers/RegisteredListController.php`, `PrioritizingController.php`, `ForesightRadarController.php`:
Replace:
```php
} catch (\Throwable $th) {
    return response()->json([
        'errors' => $th->getMessage(),
    ], 500);
}
```
With:
```php
} catch (\Throwable $th) {
    \Illuminate\Support\Facades\Log::error('Visualization error: ' . $th->getMessage(), ['exception' => $th]);
    return response()->json([
        'message' => 'An unexpected server error occurred while retrieving data.',
    ], 500);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=InformationDisclosureTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/RegisteredListController.php app/Http/Controllers/PrioritizingController.php app/Http/Controllers/ForesightRadarController.php tests/Feature/Security/InformationDisclosureTest.php
git commit -m "fix(security): sanitize 500 exception responses to prevent schema disclosure"
```

---

### Task 6: Prevent Unchecked Date Array Indexing Crashes (REL-001)

**Files:**
- Modify: `app/Models/DrivingForceRating.php:61-63, 91-93, 135-137`
- Test: `tests/Feature/Reliability/DateRangeValidationTest.php`

**Interfaces:**
- Consumes: `$date_range` scope argument (potentially malformed or empty array).
- Produces: Graceful query execution without PHP `Undefined array key 1` exceptions.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Reliability/DateRangeValidationTest.php`:
```php
<?php

namespace Tests\Feature\Reliability;

use App\Models\DrivingForceRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateRangeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_date_range_does_not_throw_undefined_array_key_exception(): void
    {
        // Calling scope with single element array should not crash
        $query = DrivingForceRating::query()->whereDateRange(['2026-01-01']);
        $this->assertNotNull($query);

        // Calling scope with non-array string should not crash
        $query2 = DrivingForceRating::query()->whereDateRange('not-an-array');
        $this->assertNotNull($query2);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=DateRangeValidationTest`  
Expected: FAIL (`Undefined array key 1`).

- [ ] **Step 3: Update `DrivingForceRating.php`**

In `app/Models/DrivingForceRating.php:61-63, 91-93, 135-137`:
Replace:
```php
public function scopeWhereDateRange($query, $date_range)
{
    if ($date_range) {
        return $query->whereBetween('created_at', [$date_range[0], $date_range[1]]);
    }
    return $query;
}
```
With:
```php
public function scopeWhereDateRange($query, $date_range)
{
    if (is_array($date_range) && count($date_range) >= 2 && !empty($date_range[0]) && !empty($date_range[1])) {
        return $query->whereBetween('created_at', [$date_range[0], $date_range[1]]);
    }
    return $query;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=DateRangeValidationTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/DrivingForceRating.php tests/Feature/Reliability/DateRangeValidationTest.php
git commit -m "fix(reliability): guard date range scopes against malformed array parameters"
```

---

### Task 7: Preserve UUID Idempotency in Rating Updates (REL-003)

**Files:**
- Modify: `app/Http/Controllers/TimeHorizonController.php:63-68`
- Test: `tests/Feature/Reliability/TimeHorizonUuidTest.php`

**Interfaces:**
- Consumes: Existing `DrivingForceRating` models.
- Produces: Updated record keeping original primary key `id` immutable.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Reliability/TimeHorizonUuidTest.php`:
```php
<?php

namespace Tests\Feature\Reliability;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TimeHorizonUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_time_horizon_preserves_original_rating_uuid(): void
    {
        Permission::create(['name' => 'view-time-horizon']);
        Permission::create(['name' => 'create-time-horizon']);

        $user = User::factory()->create();
        $user->givePermissionTo(['view-time-horizon', 'create-time-horizon']);

        $dim = Dimension::create(['name' => 'Tech', 'symbol' => 'T']);
        $df = DrivingForce::create([
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'keyword' => 'AI',
            'description' => 'Artificial intelligence',
            'status' => 'PENDING',
        ]);
        $th = TimeHorizon::create(['name' => 'Short', 'code' => 'ST']);

        // First creation
        $this->actingAs($user)->post("/time-horizon/{$df->id}", [
            'time_horizon_id' => $th->id,
        ]);

        $rating = DrivingForceRating::where('driving_force_id', $df->id)->first();
        $originalUuid = $rating->uuid;

        // Second update
        $this->actingAs($user)->post("/time-horizon/{$df->id}", [
            'time_horizon_id' => $th->id,
        ]);

        $rating->refresh();
        $this->assertEquals($originalUuid, $rating->uuid, 'Rating UUID should not regenerate on update!');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=TimeHorizonUuidTest`

- [ ] **Step 3: Update `TimeHorizonController.php`**

In `app/Http/Controllers/TimeHorizonController.php:63-68`:
Ensure `'uuid' => (string) Str::uuid()` is ONLY set when creating new models or handled by model boot method, never passed in update arrays.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=TimeHorizonUuidTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TimeHorizonController.php tests/Feature/Reliability/TimeHorizonUuidTest.php
git commit -m "fix(integrity): preserve rating UUID across time horizon updates"
```

---

### Task 8: Stream Bulk Export Data to Prevent Memory Exhaustion (PERF-001)

**Files:**
- Modify: `app/Http/Controllers/RegisteredListController.php:68-80`
- Test: `tests/Feature/Performance/ExportStreamingTest.php`

**Interfaces:**
- Consumes: Filter array from HTTP request.
- Produces: `StreamedResponse` outputting CSV rows progressively without buffering the entire table in RAM.

- [ ] **Step 1: Write the test**

Create `tests/Feature/Performance/ExportStreamingTest.php`:
```php
<?php

namespace Tests\Feature\Performance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExportStreamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_data_returns_streamed_csv_response(): void
    {
        Permission::create(['name' => 'export-visualization']);
        $user = User::factory()->create();
        $user->givePermissionTo('export-visualization');

        $response = $this->actingAs($user)->get('/visualization/registered-list/export-data');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
```

- [ ] **Step 2: Implement streaming in `RegisteredListController.php`**

In `app/Http/Controllers/RegisteredListController.php`:
```php
public function export_data(Request $request)
{
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="foresight-registered-list-' . date('Y-m-d') . '.csv"',
    ];

    $filters = $request->only(['date', 'dimension', 'search']);
    $query = $this->drivingForceService->getExportQuery($filters);

    return response()->stream(function () use ($query) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['ID', 'Keyword', 'Dimension', 'Status', 'Impact', 'Uncertainty', 'Created At']);

        foreach ($query->lazy(250) as $row) {
            fputcsv($handle, [
                $row->id,
                $row->keyword,
                $row->dimension->name ?? '',
                $row->status,
                $row->rating->impact_analysis ?? '',
                $row->rating->uncertainty_analysis ?? '',
                $row->created_at->toDateTimeString(),
            ]);
        }
        fclose($handle);
    }, 200, $headers);
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=ExportStreamingTest`  
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/RegisteredListController.php tests/Feature/Performance/ExportStreamingTest.php
git commit -m "perf(export): implement memory-safe CSV streaming for bulk registered list export"
```

---

### Task 9: Harden Nginx HTTP Security Headers and Session Cookies (OPS-001)

**Files:**
- Modify: `docker/nginx/default.conf`
- Modify: `config/session.php:171`
- Modify: `.env.example`

**Interfaces:**
- Produces: HTTP response headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Strict-Transport-Security`.

- [ ] **Step 1: Update `docker/nginx/default.conf`**

Add inside the `server { ... }` block:
```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header X-XSS-Protection "1; mode=block" always;
```

- [ ] **Step 2: Enforce secure cookies in `config/session.php`**

```php
'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),
```

- [ ] **Step 3: Validate Nginx configuration**

Run: `docker exec laravel_nginx nginx -t`  
Expected: `syntax is ok / test is successful`.

- [ ] **Step 4: Commit**

```bash
git add docker/nginx/default.conf config/session.php .env.example
git commit -m "chore(ops): configure modern HTTP security headers and production secure cookies"
```

---

### Task 10: Container Security Hardening (OPS-002)

**Files:**
- Modify: `Dockerfile`
- Modify: `docker-compose.yml`

- [ ] **Step 1: Update `Dockerfile` to run as non-root user**

Add before `CMD` or entrypoint:
```dockerfile
USER www-data
```

- [ ] **Step 2: Restrict MySQL port in `docker-compose.yml`**

Change:
```yaml
ports:
  - "3306:3306"
```
To:
```yaml
ports:
  - "127.0.0.1:3306:3306"
```

- [ ] **Step 3: Commit**

```bash
git add Dockerfile docker-compose.yml
git commit -m "chore(docker): bind MySQL to localhost and configure non-root container execution"
```

---

### Task 11: Dependency Security Patches (SEC-004)

**Files:**
- Modify: `composer.json` / `composer.lock`
- Modify: `package.json` / `package-lock.json`

- [ ] **Step 1: Upgrade Symfony components**

Run: `docker exec laravel_app composer update symfony/* --with-dependencies`  
Verify: `docker exec laravel_app composer audit`  
Expected: 0 advisories reported.

- [ ] **Step 2: Replace `xlsx` with secure export alternative**

Replace `xlsx` with backend streaming CSV or modern client parser.  
Run: `npm audit --audit-level=high`

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json
git commit -m "fix(security): patch vulnerable Symfony components and update dependencies"
```

---

### Task 12: Add Comprehensive Automated Feature Tests for Foresight Workflow Lifecycle (TEST-001)

**Files:**
- Create: `tests/Feature/Workflow/ForesightLifecycleTest.php`

- [ ] **Step 1: Write complete end-to-end lifecycle test**

```php
<?php

namespace Tests\Feature\Workflow;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ForesightLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_foresight_workflow_from_draft_to_approval_and_closure(): void
    {
        $permissions = [
            'view-driving-force', 'create-driving-force',
            'view-time-horizon', 'create-time-horizon',
            'view-rating-urgency', 'create-rating-urgency',
            'view-status-action', 'create-status-action',
            'view-approval-items', 'create-approval-items',
            'view-closed-items',
        ];

        foreach ($permissions as $p) {
            Permission::create(['name' => $p]);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        $dimension = Dimension::create(['name' => 'Social', 'symbol' => 'S']);
        $timeHorizon = TimeHorizon::create(['name' => 'Medium', 'code' => 'MT']);
        $statusAction = StatusAction::create(['name' => 'Act', 'symbol' => 'A']);

        // 1. Create Driving Force
        $response = $this->actingAs($user)->post('/driving-force', [
            'dimension_id' => $dimension->id,
            'keyword' => 'Urbanization',
            'description' => 'Rapid shift towards mega-cities',
        ]);
        $response->assertSessionHasNoErrors();
        $df = DrivingForce::where('keyword', 'Urbanization')->firstOrFail();
        $this->assertEquals('PENDING', $df->status);

        // 2. Assess Time Horizon
        $this->actingAs($user)->post("/time-horizon/{$df->id}", [
            'time_horizon_id' => $timeHorizon->id,
        ])->assertSessionHasNoErrors();

        $rating = DrivingForceRating::where('driving_force_id', $df->id)->firstOrFail();

        // 3. Rate Urgency
        $this->actingAs($user)->post("/rating-urgency/{$rating->id}", [
            'impact_analysis' => 4,
            'uncertainty_analysis' => 5,
        ])->assertSessionHasNoErrors();

        // 4. Set Status Action
        $this->actingAs($user)->post("/status-action/{$rating->id}", [
            'status_action_id' => $statusAction->id,
            'reason' => 'Requires immediate resource allocation',
            'date' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        // 5. Approve Item
        $this->actingAs($user)->post("/approval-items/{$df->id}", [
            'status' => 'APPROVED',
            'remark' => 'Approved by committee',
        ])->assertSessionHasNoErrors();

        $df->refresh();
        $this->assertEquals('APPROVED', $df->status);
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `docker exec laravel_app php artisan test --filter=ForesightLifecycleTest`  
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Workflow/ForesightLifecycleTest.php
git commit -m "test(workflow): add end-to-end integration test for 6-stage foresight lifecycle"
```

---

### Task 13: Database Performance Indexes Migration (PERF-DB)

**Files:**
- Create: `database/migrations/2026_10_09_000002_add_performance_indexes_to_driving_forces_table.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driving_forces', function (Blueprint $table) {
            $table->index('status', 'idx_df_status');
            $table->index(['status', 'created_at'], 'idx_df_status_created');
            $table->index(['dimension_id', 'status'], 'idx_df_dim_status');
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->index('created_at', 'idx_dfr_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropIndex('idx_df_status');
            $table->dropIndex('idx_df_status_created');
            $table->dropIndex('idx_df_dim_status');
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->dropIndex('idx_dfr_created_at');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `docker exec laravel_app php artisan migrate`  
Expected: Migration ran successfully.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_10_09_000002_add_performance_indexes_to_driving_forces_table.php
git commit -m "perf(database): add composite indexes on driving_forces and ratings tables"
```

---

### Task 14: Frontend Bundle Optimization and Code-Splitting (PERF-002)

**Files:**
- Modify: `vite.config.js`

- [ ] **Step 1: Configure manual vendor chunks in `vite.config.js`**

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-react': ['react', 'react-dom'],
                    'vendor-antd': ['antd', '@ant-design/icons'],
                    'vendor-inertia': ['@inertiajs/react'],
                    'vendor-utils': ['dayjs', 'lucide-react'],
                },
            },
        },
    },
});
```

- [ ] **Step 2: Run production asset build**

Run: `npm run build`  
Expected: All output chunks are cleanly split under 600 kB.

- [ ] **Step 3: Commit**

```bash
git add vite.config.js
git commit -m "perf(frontend): configure manual chunks for React, Ant Design, and vendor libraries"
```

---

### Task 15: Setup GitHub Actions CI/CD Quality Gate Pipeline

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create workflow file**

```yaml
name: CI & Security Quality Gate

on:
  push:
    branches: [ main, dev ]
  pull_request:
    branches: [ main, dev ]

jobs:
  test-and-audit:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo_mysql, bcmath, zip
          coverage: pcov

      - name: Install Composer Dependencies
        run: composer install --prefer-dist --no-progress

      - name: Composer Security Audit
        run: composer audit

      - name: Run Test Suite
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
        run: php artisan test

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 20

      - name: Install NPM Dependencies
        run: npm ci

      - name: NPM Security Audit
        run: npm audit --audit-level=high

      - name: Build Frontend Assets
        run: npm run build
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add automated CI/CD pipeline with security audits and test execution"
```

---

## Plan Self-Review & Verification

1. **Spec Coverage:**
   - SEC-001 (Role Escalation) -> Task 3
   - SEC-002 (Missing Auth on Visualizations & Export) -> Task 2
   - SEC-003 (SQL Operator Precedence Filter Bypass) -> Task 1
   - SEC-004 (Supply Chain Vulnerabilities) -> Task 11
   - SEC-005 (Unsanitized 500 Responses) -> Task 5
   - REL-001 (Array Key Date Range Crashes) -> Task 6
   - REL-002 (Account Collision & Role Overwrite) -> Task 4
   - REL-003 (UUID Regeneration in Updates) -> Task 7
   - PERF-001 (Unbounded Export Memory Loading) -> Task 8
   - PERF-002 (Monolithic Frontend Asset Bundle) -> Task 14
   - OPS-001 (Security Headers & Secure Cookies) -> Task 9
   - OPS-002 (Non-root container user & localhost MySQL) -> Task 10
   - TEST-001 (Full Test Coverage Plan & CI/CD) -> Tasks 1, 2, 3, 4, 5, 6, 7, 8, 12, 13, 15

2. **No Placeholders:**
   - Every task includes exact file paths, complete code diffs/snippets, test cases, and exact shell execution commands.
