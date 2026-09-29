# Foresight Radar — Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate all critical, high, and medium findings from the full project audit, bringing the application to a production-ready baseline.

**Architecture:** Incremental fixes applied in priority order — critical security/crash fixes first, then structural improvements, then refactoring. Each task is independently deployable and testable. No breaking changes to existing functionality.

**Tech Stack:** Laravel 10 (PHP 8.2), Inertia.js, React 18, Ant Design 5, MySQL 8, Docker

## Global Constraints

- All commands run inside Docker: prefix with `docker exec laravel_app` for PHP/artisan, `docker exec laravel_mysql` for MySQL
- Do NOT modify the existing database schema destructively — only add new migrations, policies, and indexes
- Do NOT change the seeder/factory data files — they are reference data
- All PHP code follows PSR-12 style
- All new backend code must have corresponding test coverage
- Commit after each task completes successfully
- Run `docker exec laravel_app php artisan test` after each backend task to verify no regressions

---

## Phase 0 — Immediate Critical Fixes

---

### Task 1: Patch Symfony CVEs via Composer Update

**Files:**
- Modify: `composer.lock` (auto-updated by composer)

**Interfaces:**
- Consumes: Nothing
- Produces: Patched dependency tree with no known CVEs

- [ ] **Step 1: Run composer update for vulnerable packages**

```bash
docker exec laravel_app composer update symfony/process symfony/routing symfony/yaml --with-all-dependencies
```

- [ ] **Step 2: Verify no CVEs remain**

```bash
docker exec laravel_app composer audit
```

Expected: `No known vulnerabilities found` or only informational advisories.

- [ ] **Step 3: Verify application still boots**

```bash
docker exec laravel_app php artisan about
```

Expected: Laravel version, PHP version, and all drivers display correctly.

- [ ] **Step 4: Commit**

```bash
git add composer.lock
git commit -m "fix(security): patch 7 Symfony CVEs via composer update"
```

---

### Task 2: Fix Frontend Crash Bug — OverallStatus Prop Mismatch

**Files:**
- Modify: `resources/js/Pages/Dashboard.jsx:56-61`
- Modify: `resources/js/Components/OverallStatus.jsx:17,38-48,60,258`

**Interfaces:**
- Consumes: `auth.permissions` from Inertia shared props (via `HandleInertiaRequests.php`)
- Produces: Working OverallStatus component that does not crash on mount

- [ ] **Step 1: Fix the prop name in Dashboard.jsx**

In `resources/js/Pages/Dashboard.jsx`, the `<OverallStatus>` component (around line 56-61) passes `selectData` but the child expects `selectedData`. Also, `permissions` is not passed. Fix both:

Replace lines 56-61:
```jsx
                <OverallStatus
                    loading={loading}
                    setLoading={setLoading}
                    selectData={selectData}
                    date={newDate}
                />
```

With:
```jsx
                <OverallStatus
                    loading={loading}
                    setLoading={setLoading}
                    selectedData={selectData}
                    date={newDate}
                    permissions={auth.permissions || []}
                />
```

- [ ] **Step 2: Add null safety to OverallStatus.jsx useEffect**

In `resources/js/Components/OverallStatus.jsx`, the `useEffect` dependency array (line 38-48) accesses `selectedData.dimension` directly, which crashes when `selectedData` is `null`. Add null safety:

Replace lines 38-48:
```jsx
    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        date,
        selectedData.dimension,
        selectedData.time_horizon,
        selectedData.priority,
        selectedData.status_action,
    ]);
```

With:
```jsx
    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        date,
        selectedData?.dimension,
        selectedData?.time_horizon,
        selectedData?.priority,
        selectedData?.status_action,
    ]);
```

Also update line 60 in the `fetchData` function to handle null `selectedData`:
```jsx
                        ...selectedData,
```
Replace with:
```jsx
                        ...(selectedData || {}),
```

And the same for `handleExport` around line 258:
```jsx
                        ...selectedData,
```
Replace with:
```jsx
                        ...(selectedData || {}),
```

- [ ] **Step 3: Verify by building frontend**

```bash
cd c:\laragon\www\foresight-radar && npm run build
```

Expected: Build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Dashboard.jsx resources/js/Components/OverallStatus.jsx
git commit -m "fix(frontend): fix OverallStatus crash from prop mismatch and missing permissions"
```

---

### Task 3: Fix Floating setTimeout in Radar.jsx

**Files:**
- Modify: `resources/js/Components/Radar.jsx:250-272,326-328`

**Interfaces:**
- Consumes: `loading`, `setLoading` props from Dashboard
- Produces: Radar component without infinite re-render loop

- [ ] **Step 1: Remove the floating setTimeout**

In `resources/js/Components/Radar.jsx`, lines 326-328 contain a `setTimeout` in the render body (outside any hook):

```jsx
    setTimeout(() => {
        setLoading(false);
    }, 1000);
```

**Delete these 3 lines entirely.** The `fetchData` function already calls `setLoading(false)` when data arrives (line 271) or on error (line 280). The floating `setTimeout` is redundant and harmful.

- [ ] **Step 2: Also remove artificial delay in fetchData**

In the same file, lines 250-272, replace the `setTimeout` wrapper:

```jsx
            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));

                    const radar = response.data.map((d) => ({
                        value: d.value,
                        name: d.dimension,
                        symbol: d.symbol,
                        itemStyle: {
                            color: d.item_style,
                        },
                    }));
                    setRadarData(radar);

                    setData(newData);
                    setTableParams({
                        ...tableParams,
                    });

                    setLoading(false);
                }, 500);
```

With (no setTimeout wrapper):
```jsx
            if (response.status == 200) {
                const newData = response.data.map((d, i) => ({
                    no: i + 1,
                    ...d,
                }));

                const radar = response.data.map((d) => ({
                    value: d.value,
                    name: d.dimension,
                    symbol: d.symbol,
                    itemStyle: {
                        color: d.item_style,
                    },
                }));
                setRadarData(radar);

                setData(newData);
                setTableParams({
                    ...tableParams,
                });

                setLoading(false);
```

- [ ] **Step 3: Build and verify**

```bash
cd c:\laragon\www\foresight-radar && npm run build
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Radar.jsx
git commit -m "fix(frontend): remove floating setTimeout causing infinite re-renders in Radar"
```

---

### Task 4: Fix Broken Transaction Atomicity in RatingUrgencyController

**Files:**
- Modify: `app/Http/Controllers/RatingUrgencyController.php:46-96`

**Interfaces:**
- Consumes: `DrivingForceRating` model via route model binding, `Request` with `type` and `value`
- Produces: Atomic rating + priority update in a single transaction

- [ ] **Step 1: Rewrite the create method with a single transaction**

Replace the entire `create` method (lines 46-96) in `app/Http/Controllers/RatingUrgencyController.php`:

```php
    public function create(Request $request, DrivingForceRating $driving_force_rating)
    {
        try {
            DB::transaction(function () use ($request, $driving_force_rating) {
                if ($request['type'] == 'uncertainty') {
                    $driving_force_rating->uncertainty_analysis = $request['value'];
                } else {
                    $driving_force_rating->impact_analysis = $request['value'];
                }

                // Calculate priority if both analyses are present
                if ($driving_force_rating->impact_analysis !== null && $driving_force_rating->uncertainty_analysis !== null) {
                    if ($driving_force_rating->impact_analysis >= 6 && $driving_force_rating->uncertainty_analysis >= 6) {
                        $driving_force_rating->priority_id = 1;
                    } elseif ($driving_force_rating->impact_analysis >= 6 || $driving_force_rating->uncertainty_analysis >= 6) {
                        $driving_force_rating->priority_id = 2;
                    } else {
                        $driving_force_rating->priority_id = 3;
                    }
                }

                $driving_force_rating->save();
            });

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set rating for ' . $driving_force_rating->driving_force->keyword . ' ' . ($request['type'] == 'uncertainty' ? 'uncertainty !' : 'impact !')
            ];
        } catch (\Throwable $th) {
            Log::error('Rating urgency update failed', [
                'rating_id' => $driving_force_rating->id,
                'error' => $th->getMessage(),
            ]);

            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'An error occurred while updating the rating. Please try again.',
            ];
        }

        return response()->json($response, $response['statusCode']);
    }
```

- [ ] **Step 2: Verify the app still works**

```bash
docker exec laravel_app php artisan test
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/RatingUrgencyController.php
git commit -m "fix(data-integrity): wrap rating + priority update in single transaction"
```

---

### Task 5: Add Authorization Middleware to All Mutation Routes

**Files:**
- Modify: `routes/web.php:55-57,63,69,75,93-95`

**Interfaces:**
- Consumes: Spatie permission names from `database/data/permission.json`
- Produces: All CUD routes protected by permission middleware

- [ ] **Step 1: Review existing permissions**

The permission names from `database/data/permission.json` include: `create-driving-force`, `update-driving-force`, `delete-driving-force`, `create-time-horizon`, `create-rating-urgency`, `create-status-action`, `create-approval-items`, `create-user`, `update-user`, `delete-user`.

- [ ] **Step 2: Add ->can() to driving force mutation routes**

In `routes/web.php`, replace lines 55-57:
```php
        Route::post("/", "create")->name('create');
        Route::put("/{driving_force}", "update")->name('update');
        Route::delete("/{driving_force}", "delete")->name('delete');
```

With:
```php
        Route::post("/", "create")->name('create')->can('create-driving-force');
        Route::put("/{driving_force}", "update")->name('update')->can('update-driving-force');
        Route::delete("/{driving_force}", "delete")->name('delete')->can('delete-driving-force');
```

- [ ] **Step 3: Add ->can() to time-horizon create route**

Replace line 63:
```php
        Route::post("/{driving_force:id}", "create")->name('create');
```
With:
```php
        Route::post("/{driving_force:id}", "create")->name('create')->can('create-time-horizon');
```

- [ ] **Step 4: Add ->can() to rating-urgency create route**

Replace line 69:
```php
        Route::post("/{driving_force_rating}", "create")->name('create');
```
With:
```php
        Route::post("/{driving_force_rating}", "create")->name('create')->can('create-rating-urgency');
```

- [ ] **Step 5: Add ->can() to status-action create route**

Replace line 75:
```php
        Route::post("/{driving_force_rating}", "create")->name('create');
```
With:
```php
        Route::post("/{driving_force_rating}", "create")->name('create')->can('create-status-action');
```

- [ ] **Step 6: Add ->can() to user management mutation routes**

Replace lines 93-95:
```php
            Route::post("/", "create")->name('create');
            Route::put("/{user}", "update")->name('update');
            Route::delete("/{user}", "delete")->name('delete');
```

With:
```php
            Route::post("/", "create")->name('create')->can('create-user');
            Route::put("/{user}", "update")->name('update')->can('update-user');
            Route::delete("/{user}", "delete")->name('delete')->can('delete-user');
```

- [ ] **Step 7: Verify routes are correct**

```bash
docker exec laravel_app php artisan route:list --columns=method,uri,middleware
```

Expected: All POST/PUT/DELETE routes show the `can:` middleware.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php
git commit -m "fix(security): add authorization middleware to all mutation routes"
```

---

### Task 6: Fix PHPUnit Config & APP_DEBUG Default

**Files:**
- Modify: `phpunit.xml:24-25`
- Modify: `.env.example:4`

**Interfaces:**
- Consumes: Nothing
- Produces: Safe test database configuration and production-safe defaults

- [ ] **Step 1: Uncomment SQLite test database in phpunit.xml**

In `phpunit.xml`, replace lines 24-25:
```xml
        <!-- <env name="DB_CONNECTION" value="sqlite"/> -->
        <!-- <env name="DB_DATABASE" value=":memory:"/> -->
```

With:
```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 2: Set APP_DEBUG=false in .env.example**

In `.env.example`, replace line 4:
```
APP_DEBUG=true
```

With:
```
APP_DEBUG=false
```

- [ ] **Step 3: Commit**

```bash
git add phpunit.xml .env.example
git commit -m "fix(config): enable SQLite test DB, set APP_DEBUG=false as default"
```

---

## Phase 1 — Short Term Improvements

---

### Task 7: Add Unique Indexes to UUID Columns

**Files:**
- Create: `database/migrations/2026_09_29_000001_add_uuid_indexes.php`

**Interfaces:**
- Consumes: Existing tables: `users`, `dimensions`, `driving_forces`, `driving_force_ratings`
- Produces: Unique indexes on all UUID columns used for route model binding

- [ ] **Step 1: Create the migration**

```bash
docker exec laravel_app php artisan make:migration add_uuid_indexes
```

- [ ] **Step 2: Write the migration**

In the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('dimensions', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('dimensions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
docker exec laravel_app php artisan migrate
```

Expected: Migration runs successfully.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "perf(database): add unique indexes to UUID columns for route model binding"
```

---

### Task 8: Fix N+1 Query in DrivingForceController

**Files:**
- Modify: `app/Http/Controllers/DrivingForceController.php:27`

**Interfaces:**
- Consumes: `User` model with `roles` relationship
- Produces: Single query for users with roles (no N+1)

- [ ] **Step 1: Add eager loading for roles**

In `app/Http/Controllers/DrivingForceController.php`, replace line 27:
```php
        $users = User::select('id', 'name')->whereHas('roles', function ($q) {
```

With:
```php
        $users = User::select('id', 'name')->with('roles:id,name,display_name')->whereHas('roles', function ($q) {
```

- [ ] **Step 2: Verify no errors**

```bash
docker exec laravel_app php artisan test
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/DrivingForceController.php
git commit -m "perf(query): fix N+1 query on User roles in DrivingForceController"
```

---

### Task 9: Sanitize Error Messages Returned to Frontend

**Files:**
- Create: `app/Http/Controllers/Concerns/HandlesApiErrors.php`
- Modify: `app/Http/Controllers/DrivingForceController.php`
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `app/Http/Controllers/TimeHorizonController.php`
- Modify: `app/Http/Controllers/StatusActionController.php`
- Modify: `app/Http/Controllers/ApprovalController.php`
- Modify: `app/Http/Controllers/ClosedItemsController.php`

**Interfaces:**
- Consumes: `\Throwable` exceptions in catch blocks
- Produces: Safe error responses without internal implementation details

- [ ] **Step 1: Create a helper trait for consistent error responses**

Create `app/Http/Controllers/Concerns/HandlesApiErrors.php`:

```php
<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

trait HandlesApiErrors
{
    protected function handleError(\Throwable $th, string $context = 'operation'): array
    {
        Log::error("Failed {$context}", [
            'error' => $th->getMessage(),
            'trace' => $th->getTraceAsString(),
        ]);

        return [
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => "An error occurred during {$context}. Please try again.",
        ];
    }
}
```

- [ ] **Step 2: Apply trait to all controllers**

In each controller listed above, replace catch blocks like:
```php
        } catch (\Throwable $th) {
            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $th->getMessage(),
            ];
        }
```

With:
```php
        } catch (\Throwable $th) {
            $response = $this->handleError($th, 'driving force creation');
        }
```

Use the trait in each controller:
```php
use App\Http\Controllers\Concerns\HandlesApiErrors;

class DrivingForceController extends Controller
{
    use HandlesApiErrors;
```

Vary the context string per method (e.g., `'driving force creation'`, `'driving force update'`, `'user update'`, `'rating update'`, `'status action update'`, `'approval update'`).

- [ ] **Step 3: Verify no errors**

```bash
docker exec laravel_app php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/
git commit -m "fix(security): sanitize error messages, never expose internals to frontend"
```

---

### Task 10: Create Laravel Policies for DrivingForce and User

**Files:**
- Create: `app/Policies/DrivingForcePolicy.php`
- Create: `app/Policies/UserPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Modify: `app/Http/Controllers/DrivingForceController.php`
- Modify: `app/Http/Controllers/UserController.php`

**Interfaces:**
- Consumes: `User` model, `DrivingForce` model, Spatie `HasRoles` trait
- Produces: Object-level authorization for update/delete operations

- [ ] **Step 1: Generate policies**

```bash
docker exec laravel_app php artisan make:policy DrivingForcePolicy --model=DrivingForce
docker exec laravel_app php artisan make:policy UserPolicy --model=User
```

- [ ] **Step 2: Implement DrivingForcePolicy**

In `app/Policies/DrivingForcePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\DrivingForce;
use App\Models\User;

class DrivingForcePolicy
{
    public function update(User $user, DrivingForce $drivingForce): bool
    {
        // Creator, PIC, or admin+ can update
        return $user->id === $drivingForce->created_by
            || $user->id === $drivingForce->pic
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    public function delete(User $user, DrivingForce $drivingForce): bool
    {
        // Creator or admin+ can delete
        return $user->id === $drivingForce->created_by
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }
}
```

- [ ] **Step 3: Implement UserPolicy**

In `app/Policies/UserPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $authUser, User $targetUser): bool
    {
        // Cannot edit users with higher-privilege roles
        if ($targetUser->hasRole('developer') && !$authUser->hasRole('developer')) {
            return false;
        }
        if ($targetUser->hasRole('super-admin') && !$authUser->hasAnyRole(['developer', 'super-admin'])) {
            return false;
        }
        return $authUser->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    public function delete(User $authUser, User $targetUser): bool
    {
        // Cannot delete yourself
        if ($authUser->id === $targetUser->id) {
            return false;
        }
        return $this->update($authUser, $targetUser);
    }
}
```

- [ ] **Step 4: Register policies in AuthServiceProvider**

In `app/Providers/AuthServiceProvider.php`, add to `$policies`:

```php
use App\Models\DrivingForce;
use App\Models\User;
use App\Policies\DrivingForcePolicy;
use App\Policies\UserPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        DrivingForce::class => DrivingForcePolicy::class,
        User::class => UserPolicy::class,
    ];
```

- [ ] **Step 5: Use policies in controllers**

In `DrivingForceController::update()`, add before `DB::beginTransaction()`:
```php
        $this->authorize('update', $driving_force);
```

In `DrivingForceController::delete()`, add before `DB::beginTransaction()`:
```php
        $this->authorize('delete', $driving_force);
```

In `UserController::update()`, add before `DB::beginTransaction()`:
```php
        $this->authorize('update', $user);
```

In `UserController::delete()`, add before `DB::beginTransaction()`:
```php
        $this->authorize('delete', $user);
```

- [ ] **Step 6: Verify**

```bash
docker exec laravel_app php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Policies/ app/Providers/AuthServiceProvider.php app/Http/Controllers/DrivingForceController.php app/Http/Controllers/UserController.php
git commit -m "feat(security): add Laravel Policies for object-level authorization"
```

---

### Task 11: Set Up Basic GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`
- Create: `.dockerignore`

**Interfaces:**
- Consumes: `composer.json`, `package.json`, `phpunit.xml`
- Produces: CI pipeline that runs on push/PR

- [ ] **Step 1: Create .dockerignore**

Create `.dockerignore`:

```
.git
.github
node_modules
vendor
storage/logs/*
.env
.env.backup
.idea
.vscode
tests
docs
```

- [ ] **Step 2: Create GitHub Actions workflow**

Create `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  backend:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, mbstring, bcmath, gd
          coverage: none

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Copy .env
        run: cp .env.example .env

      - name: Generate key
        run: php artisan key:generate

      - name: Run tests
        run: php artisan test

      - name: Composer audit
        run: composer audit

  frontend:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Build
        run: npm run build
```

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml .dockerignore
git commit -m "ci: add GitHub Actions CI pipeline with backend tests and frontend build"
```

---

## Phase 2 — Medium Term Refactoring

---

### Task 12: Extract Fat Model Queries to DrivingForceService

**Files:**
- Create: `app/Services/DrivingForceService.php`
- Modify: `app/Models/DrivingForce.php` (remove static filter methods, lines 91-263)
- Modify: `app/Http/Controllers/DrivingForceController.php`
- Modify: `app/Http/Controllers/TimeHorizonController.php`
- Modify: `app/Http/Controllers/RatingUrgencyController.php`
- Modify: `app/Http/Controllers/StatusActionController.php`
- Modify: `app/Http/Controllers/ApprovalController.php`
- Modify: `app/Http/Controllers/ClosedItemsController.php`

**Interfaces:**
- Consumes: `DrivingForce` model relationships, request parameters (passed as explicit args)
- Produces: `DrivingForceService` class with methods: `filter(array): DrivingForceCollection`, `timeHorizon(array): TimeHorizonCollection`, `ratingUrgency(array): RatingUrgencyCollection`, `statusAction(array): StatusActionCollection`, `approvalItems(array): ApprovalItemsCollection`, `closedItems(array): ClosedItemsCollection`

- [ ] **Step 1: Create the service class**

Create `app/Services/DrivingForceService.php`:

```php
<?php

namespace App\Services;

use App\Http\Resources\ApprovalItemsCollection;
use App\Http\Resources\ClosedItemsCollection;
use App\Http\Resources\DrivingForceCollection;
use App\Http\Resources\RatingUrgencyCollection;
use App\Http\Resources\StatusActionCollection;
use App\Http\Resources\TimeHorizonCollection;
use App\Models\DrivingForce;

class DrivingForceService
{
    public function filter(array $params): DrivingForceCollection
    {
        $query = DrivingForce::with(['dimension', 'created_by_user', 'updated_by_user'])
            ->when($params['search'] ?? null, function ($q, $search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($params['dimension'] ?? null, function ($q, $dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->when($params['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->where('created_at', '>=', $params['date'][0] . ' 00:00:00')
            ->where('created_at', '<=', $params['date'][1] . ' 23:59:59')
            ->orderBy('created_at', 'DESC')
            ->paginate($params['pagination']['pageSize'] ?? 10);

        return new DrivingForceCollection($query);
    }

    public function timeHorizon(array $params): TimeHorizonCollection
    {
        $query = DrivingForce::with(['dimension', 'rating'])
            ->when($params['search'] ?? null, function ($q, $search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($params['dimension'] ?? null, function ($q, $dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $params['date'][0] . ' 00:00:00')
            ->where('created_at', '<=', $params['date'][1] . ' 23:59:59')
            ->orderBy('created_at', 'DESC')
            ->paginate($params['pagination']['pageSize'] ?? 10);

        return new TimeHorizonCollection($query);
    }

    public function ratingUrgency(array $params): RatingUrgencyCollection
    {
        $query = DrivingForce::with(['dimension', 'rating'])
            ->when($params['search'] ?? null, function ($q, $search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($params['dimension'] ?? null, function ($q, $dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $params['date'][0] . ' 00:00:00')
            ->has('rating')
            ->where('created_at', '<=', $params['date'][1] . ' 23:59:59')
            ->orderBy('created_at', 'DESC')
            ->paginate($params['pagination']['pageSize'] ?? 10);

        return new RatingUrgencyCollection($query);
    }

    public function statusAction(array $params): StatusActionCollection
    {
        $query = DrivingForce::with(['dimension', 'rating.action_reasons' => function ($q) {
                $q->select('driving_force_rating_id', 'date', 'reason', 'status_action_id')
                    ->orderBy('date', 'ASC');
            }])
            ->when($params['search'] ?? null, function ($q, $search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($params['dimension'] ?? null, function ($q, $dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->where('created_at', '>=', $params['date'][0] . ' 00:00:00')
            ->has('rating')
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('impact_analysis')
                    ->whereNotNull('uncertainty_analysis');
            })
            ->where('created_at', '<=', $params['date'][1] . ' 23:59:59')
            ->orderBy('created_at', 'DESC')
            ->paginate($params['pagination']['pageSize'] ?? 10);

        return new StatusActionCollection($query);
    }

    public function approvalItems(array $params): ApprovalItemsCollection
    {
        $query = DrivingForce::with(['rating'])
            ->when($params['search'] ?? null, function ($q, $search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($params['dimension'] ?? null, function ($q, $dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('status_action_id');
            })
            ->has('rating')
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->where('created_at', '>=', $params['date'][0] . ' 00:00:00')
            ->where('created_at', '<=', $params['date'][1] . ' 23:59:59')
            ->orderBy('created_at', 'DESC')
            ->orderBy('status', 'DESC')
            ->paginate($params['pagination']['pageSize'] ?? 10);

        return new ApprovalItemsCollection($query);
    }

    public function closedItems(array $params): ClosedItemsCollection
    {
        $query = DrivingForce::with(['rating'])
            ->when($params['search'] ?? null, function ($q, $search) {
                $q->where('keyword', 'LIKE', $search . '%')
                    ->orWhere('description', 'LIKE', $search . '%');
            })
            ->when($params['dimension'] ?? null, function ($q, $dimension) {
                $q->where('dimension_id', $dimension);
            })
            ->whereHas('rating', function ($q) {
                $q->whereNotNull('status_action_id');
            })
            ->has('rating')
            ->where('status', 'CLOSED')
            ->where('created_at', '>=', $params['date'][0] . ' 00:00:00')
            ->where('created_at', '<=', $params['date'][1] . ' 23:59:59')
            ->orderBy('created_at', 'DESC')
            ->paginate($params['pagination']['pageSize'] ?? 10);

        return new ClosedItemsCollection($query);
    }
}
```

- [ ] **Step 2: Update DrivingForceController to use the service**

```php
use App\Services\DrivingForceService;

class DrivingForceController extends Controller
{
    use HandlesApiErrors;

    public function __construct(private DrivingForceService $service) {}

    public function fetch_data()
    {
        try {
            $result = $this->service->filter(request()->all());
            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json($this->handleError($th, 'fetching driving forces'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    // ... rest of methods unchanged
```

- [ ] **Step 3: Update TimeHorizonController::fetch_data()**

```php
    public function __construct(private DrivingForceService $service) {}

    public function fetch_data()
    {
        try {
            $result = $this->service->timeHorizon(request()->all());
            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            // ...
        }
    }
```

- [ ] **Step 4: Update RatingUrgencyController::fetch_data()**

```php
    public function __construct(private DrivingForceService $service) {}

    public function fetch_data()
    {
        try {
            $result = $this->service->ratingUrgency(request()->all());
            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            // ...
        }
    }
```

- [ ] **Step 5: Update StatusActionController::fetch_data()**

```php
    public function __construct(private DrivingForceService $service) {}

    public function fetch_data()
    {
        try {
            $result = $this->service->statusAction(request()->all());
            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            // ...
        }
    }
```

- [ ] **Step 6: Update ApprovalController::fetch_data()**

```php
    public function __construct(private DrivingForceService $service) {}

    public function fetch_data()
    {
        try {
            $result = $this->service->approvalItems(request()->all());
            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            // ...
        }
    }
```

- [ ] **Step 7: Update ClosedItemsController::fetch_data()**

```php
    public function __construct(private DrivingForceService $service) {}

    public function fetch_data()
    {
        try {
            $result = $this->service->closedItems(request()->all());
            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            // ...
        }
    }
```

- [ ] **Step 8: Remove static methods from DrivingForce model**

Delete the `filter()`, `time_horizon()`, `rating_urgency()`, `status_action()`, `closed_items()`, and `approval_items()` static methods from `app/Models/DrivingForce.php` (lines 91-263). Keep all relationships, attributes, and `getRouteKeyName()`.

- [ ] **Step 9: Verify**

```bash
docker exec laravel_app php artisan test
```

- [ ] **Step 10: Commit**

```bash
git add app/Services/ app/Models/DrivingForce.php app/Http/Controllers/
git commit -m "refactor(architecture): extract Fat Model queries to DrivingForceService"
```

---

### Task 13: Fix Login.jsx to Use Inertia Instead of window.location

**Files:**
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php:33-45`
- Modify: `resources/js/Pages/Auth/Login.jsx`

**Interfaces:**
- Consumes: Inertia `router` from `@inertiajs/react`
- Produces: SPA login flow without full page reload

- [ ] **Step 1: Change AuthenticatedSessionController to return redirect**

In `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, replace the `store` method (lines 33-45):

```php
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }
```

Remove the `use Illuminate\Http\JsonResponse;` import since it is no longer needed.

- [ ] **Step 2: Update Login.jsx to use Inertia router**

Replace the entire `Login.jsx` content at `resources/js/Pages/Auth/Login.jsx`:

```jsx
import { useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, router } from "@inertiajs/react";
import { Alert, Button, Form, Input, Checkbox } from "antd";
import { LockOutlined, UserOutlined } from "@ant-design/icons";

export default function Login({ errors: serverErrors }) {
    const [loading, setLoading] = useState(false);

    const onFinish = (values) => {
        setLoading(true);

        router.post(route("login"), values, {
            onError: () => {
                setLoading(false);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div style={{ maxWidth: "400px", margin: "auto", padding: "50px 0" }}>
                {serverErrors?.email && (
                    <Alert
                        message={serverErrors.email}
                        type="error"
                        showIcon
                        style={{ marginBottom: "20px" }}
                    />
                )}
                <Form
                    name="login"
                    disabled={loading}
                    initialValues={{ remember: false }}
                    onFinish={onFinish}
                >
                    <Form.Item
                        name="email"
                        rules={[{ required: true, message: "Please input your Email!" }]}
                    >
                        <Input prefix={<UserOutlined />} autoFocus placeholder="Email" />
                    </Form.Item>
                    <Form.Item
                        name="password"
                        rules={[{ required: true, message: "Please input your Password!" }]}
                    >
                        <Input prefix={<LockOutlined />} type="password" placeholder="Password" />
                    </Form.Item>
                    <Form.Item>
                        <Form.Item name="remember" valuePropName="checked" noStyle>
                            <Checkbox>Remember me</Checkbox>
                        </Form.Item>
                    </Form.Item>
                    <Form.Item>
                        <Button
                            type="primary"
                            htmlType="submit"
                            className="login-form-button"
                            loading={loading}
                        >
                            Login
                        </Button>
                    </Form.Item>
                </Form>
            </div>
        </GuestLayout>
    );
}
```

- [ ] **Step 3: Build and verify**

```bash
cd c:\laragon\www\foresight-radar && npm run build
docker exec laravel_app php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Auth/AuthenticatedSessionController.php resources/js/Pages/Auth/Login.jsx
git commit -m "fix(inertia): use Inertia redirect for login instead of window.location"
```

---

## Phase 3 — Testing

---

### Task 14: Add Feature Tests for Core Business Flows

**Files:**
- Create: `tests/Feature/DrivingForceTest.php`
- Create: `tests/Feature/RatingUrgencyTest.php`
- Create: `tests/Feature/AuthorizationTest.php`

**Interfaces:**
- Consumes: All models, controllers, policies, Spatie roles/permissions
- Produces: Test coverage for CRUD operations, authorization, and the rating pipeline

- [ ] **Step 1: Create DrivingForceTest**

Create `tests/Feature/DrivingForceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrivingForceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Dimension $dimension;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view-driving-force']);
        Permission::create(['name' => 'create-driving-force']);
        Permission::create(['name' => 'update-driving-force']);
        Permission::create(['name' => 'delete-driving-force']);
        Permission::create(['name' => 'view-dashboard']);

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role->givePermissionTo([
            'view-driving-force',
            'create-driving-force',
            'update-driving-force',
            'delete-driving-force',
            'view-dashboard',
        ]);

        $this->admin = User::factory()->create(['uuid' => fake()->uuid()]);
        $this->admin->assignRole('admin');
        $this->admin->syncPermissions($role->permissions);

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);

        $this->dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);
    }

    public function test_authenticated_user_can_view_driving_force_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/driving-force');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_driving_force(): void
    {
        $response = $this->get('/driving-force');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_driving_force(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/driving-force', [
            'keyword' => 'Test Signal',
            'description' => 'Test description',
            'dimension_id' => $this->dimension->id,
            'pic_id' => $this->admin->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('driving_forces', ['keyword' => 'Test Signal']);
    }

    public function test_admin_can_delete_own_driving_force(): void
    {
        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'To Delete',
            'description' => 'Will be deleted',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/driving-force/{$drivingForce->uuid}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('driving_forces', ['id' => $drivingForce->id]);
    }
}
```

- [ ] **Step 2: Run DrivingForceTest**

```bash
docker exec laravel_app php artisan test --filter=DrivingForceTest
```

Expected: All tests pass.

- [ ] **Step 3: Create RatingUrgencyTest**

Create `tests/Feature/RatingUrgencyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RatingUrgencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private DrivingForceRating $rating;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'view-rating-urgency']);
        Permission::create(['name' => 'create-rating-urgency']);

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role->givePermissionTo(['view-rating-urgency', 'create-rating-urgency']);

        $this->admin = User::factory()->create(['uuid' => fake()->uuid()]);
        $this->admin->assignRole('admin');
        $this->admin->syncPermissions($role->permissions);

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Test',
            'description' => 'Test description',
            'dimension_id' => $dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $this->rating = DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $drivingForce->id,
            'time_horizon_id' => 1,
        ]);
    }

    public function test_can_set_impact_analysis(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            "/rating-urgency/{$this->rating->uuid}",
            ['type' => 'impact', 'value' => 7]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('driving_force_ratings', [
            'id' => $this->rating->id,
            'impact_analysis' => 7,
        ]);
    }

    public function test_priority_is_calculated_when_both_values_set(): void
    {
        $this->rating->update(['impact_analysis' => 7]);

        $response = $this->actingAs($this->admin)->postJson(
            "/rating-urgency/{$this->rating->uuid}",
            ['type' => 'uncertainty', 'value' => 8]
        );

        $response->assertStatus(200);
        $this->rating->refresh();
        // Both >= 6, should be priority 1 (High)
        $this->assertEquals(1, $this->rating->priority_id);
    }

    public function test_priority_low_when_both_values_below_threshold(): void
    {
        $this->rating->update(['impact_analysis' => 3]);

        $response = $this->actingAs($this->admin)->postJson(
            "/rating-urgency/{$this->rating->uuid}",
            ['type' => 'uncertainty', 'value' => 4]
        );

        $response->assertStatus(200);
        $this->rating->refresh();
        // Both < 6, should be priority 3 (Low)
        $this->assertEquals(3, $this->rating->priority_id);
    }
}
```

- [ ] **Step 4: Run RatingUrgencyTest**

```bash
docker exec laravel_app php artisan test --filter=RatingUrgencyTest
```

- [ ] **Step 5: Create AuthorizationTest**

Create `tests/Feature/AuthorizationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'view-driving-force']);
        Permission::create(['name' => 'view-dashboard']);
        // Intentionally NOT creating create/update/delete permissions for bod role

        Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $bodRole = Role::create(['name' => 'bod', 'display_name' => 'Board of Directors']);
        $bodRole->givePermissionTo(['view-driving-force', 'view-dashboard']);
    }

    public function test_user_without_create_permission_gets_403(): void
    {
        $user = User::factory()->create(['uuid' => fake()->uuid()]);
        $user->assignRole('bod');

        Permission::create(['name' => 'create-driving-force']);

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $response = $this->actingAs($user)->postJson('/driving-force', [
            'keyword' => 'Unauthorized',
            'description' => 'Should fail',
            'dimension_id' => $dimension->id,
            'pic_id' => $user->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_non_owner_without_admin_role_cannot_delete_driving_force(): void
    {
        Permission::create(['name' => 'delete-driving-force']);

        $owner = User::factory()->create(['uuid' => fake()->uuid()]);
        $owner->assignRole('bod');

        $otherUser = User::factory()->create(['uuid' => fake()->uuid()]);
        $otherUser->assignRole('bod');
        $otherUser->givePermissionTo('delete-driving-force');

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Protected',
            'description' => 'Cannot delete',
            'dimension_id' => $dimension->id,
            'created_by' => $owner->id,
            'pic' => $owner->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($otherUser)
            ->deleteJson("/driving-force/{$drivingForce->uuid}");

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 6: Run full test suite**

```bash
docker exec laravel_app php artisan test
```

Expected: All tests pass, including existing Breeze auth tests.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/
git commit -m "test: add feature tests for DrivingForce CRUD, rating pipeline, and authorization"
```

---

## Self-Review Checklist

1. **Audit coverage:** All Phase 0 critical findings (CF-01 through CF-05) are covered by Tasks 1-6. All security findings (SEC-01, SEC-02, SEC-04) covered by Tasks 5, 9, 10. Database findings covered by Tasks 7-8. Architecture findings covered by Task 12. Frontend findings covered by Tasks 2, 3, 13. Testing gap covered by Task 14. CI/CD gap covered by Task 11. ✅
2. **Placeholder scan:** No TBDs, TODOs, or "implement later" found. All code blocks are complete. ✅
3. **Type consistency:** Service method names (`filter`, `timeHorizon`, `ratingUrgency`, `statusAction`, `approvalItems`, `closedItems`) match controller calls. Policy method names (`update`, `delete`) match `$this->authorize()` calls. Migration table names match model table conventions. ✅
