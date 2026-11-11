# UI Enhancement Phase P0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate blocking usability and accessibility barriers across Foresight Radar by implementing a responsive mobile navigation drawer (RESP-001), resolving form label binding anomalies (A11Y-001), fixing WCAG 2.1 AA color contrast violations (A11Y-002), and cleaning up misplaced analytical date filters on User Management (DESIGN-003).

**Architecture:** Refactor the primary AuthenticatedLayout to adaptively switch between desktop Sider and mobile Ant Design Drawer using responsive breakpoints; remove hardcoded input IDs in auth forms to restore Ant Design automatic label-to-control association; upgrade design tokens and CSS selectors to exceed the 4.5:1 WCAG AA contrast threshold; and streamline User Management by removing unfunctional date range filters on the frontend while hardening `User::filter()` against missing dates on the backend.

**Tech Stack:** Laravel 10.x, Inertia.js (React 18), Ant Design 5.19.x, CSS3 / Tailwind, Vite 5.x, PHPUnit 10.x, @e2e-dev/web E2E test runner.

## Global Constraints

- All commits must use author `roozylabs <roozylabs@gmail.com>`.
- Conventional commit message format required: `feat(scope): ...`, `fix(scope): ...`, `refactor(scope): ...`.
- Zero placeholders: every test and code block must be fully written and directly applicable.
- All existing 55 PHPUnit tests executed via `docker exec laravel_app php artisan test` must stay 100% green without regressions.
- Frontend assets must cleanly compile via `npm run build` with zero errors.
- Strict TDD discipline: Step 1 Write failing test -> Step 2 Verify RED -> Step 3 Implement minimal code -> Step 4 Verify GREEN -> Step 5 Commit.

---

### Task 1: Responsive Mobile Navigation Drawer (`RESP-001`)

**Files:**
- Create: `tests/e2e/responsive-navigation.e2e.ts`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`

**Interfaces:**
- Consumes: Ant Design `Grid`, `Drawer`, `Menu`, `@ant-design/icons` (`MenuOutlined`, `MenuFoldOutlined`, `MenuUnfoldOutlined`, `RadarChartOutlined`)
- Produces: Responsive navigation behavior with zero sider pinch on mobile screens (viewport &le; 991px), drawer overlay with auto-close navigation.

- [ ] **Step 1: Write the failing E2E test**

Create file `tests/e2e/responsive-navigation.e2e.ts`:

```typescript
import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('Responsive Mobile Navigation Drawer (RESP-001)', () => {
  test('Mobile viewport (375x667): Desktop sider is hidden and hamburger button opens drawer overlay', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    await browser.setViewportSize({ width: 375, height: 667 });

    await app.open('/login');
    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    // Verify desktop sider is not visible on mobile
    const desktopSiders = await browser.$$('aside.ant-layout-sider');
    for (const sider of desktopSiders) {
      const isVisible = await sider.isVisible();
      expect(isVisible).toBe(false);
    }

    // Hamburger button toggles mobile navigation drawer
    const hamburgerBtn = screen.getByRole('button', 'Buka menu navigasi');
    await expect(hamburgerBtn).toBeVisible();
    await hamburgerBtn.tap();

    // Verify drawer dialog appears with navigation items
    const drawer = screen.getByRole('dialog');
    await expect(drawer).toBeVisible();
    await expect(screen.getByText('Strategic Intel')).toBeVisible();

    // Verify clicking a navigation link navigates and auto-closes drawer
    await screen.getByText('Driving Force').tap();
    await expect(browser).toHaveURL(/\/driving-force/, { timeout: 30000 });
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx e2e run tests/e2e/responsive-navigation.e2e.ts`
Expected: FAIL (because `aside.ant-layout-sider` is currently visible on mobile and `<Drawer>` does not exist yet).

- [ ] **Step 3: Implement responsive drawer in AuthenticatedLayout**

Modify `resources/js/Layouts/AuthenticatedLayout.jsx`:
1. Add `Drawer` and `Grid` to the `antd` imports:
```javascript
import {
    Layout,
    Menu,
    theme,
    Flex,
    Button,
    Dropdown,
    Space,
    FloatButton,
    Avatar,
    ConfigProvider,
    Drawer,
    Grid,
} from "antd";
```
2. Add `MenuOutlined` to `@ant-design/icons` imports:
```javascript
import {
    LogoutOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    MenuOutlined,
    DownOutlined,
    DashboardOutlined,
    RadarChartOutlined,
    UsergroupAddOutlined,
    FieldTimeOutlined,
    StarOutlined,
    UserOutlined,
    FileProtectOutlined,
    FileExcelOutlined,
    DotChartOutlined,
    InsertRowAboveOutlined,
    ThunderboltOutlined,
} from "@ant-design/icons";
```
3. Destructure breakpoint hook and declare mobile drawer state inside `Authenticated`:
```javascript
    const screens = Grid.useBreakpoint();
    const isMobile = typeof screens.lg !== "undefined" && !screens.lg;
    const [collapsed, setCollapsed] = useState(false);
    const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
```
4. Update desktop `<Sider>`:
```jsx
                <Sider
                    breakpoint="lg"
                    collapsedWidth={0}
                    theme="light"
                    width={240}
                    collapsed={collapsed}
                    collapsible
                    trigger={null}
                    style={{
                        borderRight: "1px solid #f0f0f0",
                        position: "sticky",
                        top: 0,
                        height: "100vh",
                        overflowY: "auto",
                        zIndex: 20,
                        display: isMobile ? "none" : "block",
                    }}
                >
                    <div
                        style={{
                            height: 64,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: collapsed ? "center" : "flex-start",
                            padding: collapsed ? "0" : "0 18px",
                            gap: 12,
                            borderBottom: "1px solid #f0f0f0",
                            transition: "all 0.2s",
                        }}
                    >
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 8,
                                background:
                                    "linear-gradient(135deg, #1677ff 0%, #0958d9 100%)",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                boxShadow: "0 2px 8px rgba(22, 119, 255, 0.35)",
                                flexShrink: 0,
                            }}
                        >
                            <RadarChartOutlined
                                style={{ color: "#fff", fontSize: 20 }}
                            />
                        </div>
                        {!collapsed && (
                            <div style={{ overflow: "hidden", whiteSpace: "nowrap" }}>
                                <span
                                    style={{
                                        fontSize: 15,
                                        fontWeight: 700,
                                        color: "#001529",
                                        letterSpacing: "-0.2px",
                                        display: "block",
                                        lineHeight: 1.2,
                                    }}
                                >
                                    Foresight Radar
                                </span>
                                <span
                                    style={{
                                        fontSize: 11,
                                        color: "#595959",
                                        fontWeight: 500,
                                        letterSpacing: "0.5px",
                                        textTransform: "uppercase",
                                    }}
                                >
                                    Strategic Intel
                                </span>
                            </div>
                        )}
                    </div>
                    <Menu
                        theme="light"
                        mode="inline"
                        selectedKeys={[url]}
                        items={filteredItems}
                        style={{ borderRight: "none", marginTop: 8 }}
                    />
                </Sider>

                {/* Mobile Navigation Drawer */}
                <Drawer
                    placement="left"
                    open={mobileDrawerOpen}
                    onClose={() => setMobileDrawerOpen(false)}
                    bodyStyle={{ padding: 0 }}
                    width={260}
                    closable={false}
                >
                    <div
                        style={{
                            height: 64,
                            display: "flex",
                            alignItems: "center",
                            padding: "0 18px",
                            gap: 12,
                            borderBottom: "1px solid #f0f0f0",
                        }}
                    >
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 8,
                                background:
                                    "linear-gradient(135deg, #1677ff 0%, #0958d9 100%)",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                boxShadow: "0 2px 8px rgba(22, 119, 255, 0.35)",
                                flexShrink: 0,
                            }}
                        >
                            <RadarChartOutlined
                                style={{ color: "#fff", fontSize: 20 }}
                            />
                        </div>
                        <div style={{ overflow: "hidden", whiteSpace: "nowrap" }}>
                            <span
                                style={{
                                    fontSize: 15,
                                    fontWeight: 700,
                                    color: "#001529",
                                    letterSpacing: "-0.2px",
                                    display: "block",
                                    lineHeight: 1.2,
                                }}
                            >
                                Foresight Radar
                            </span>
                            <span
                                style={{
                                    fontSize: 11,
                                    color: "#595959",
                                    fontWeight: 500,
                                    letterSpacing: "0.5px",
                                    textTransform: "uppercase",
                                }}
                            >
                                Strategic Intel
                            </span>
                        </div>
                    </div>
                    <Menu
                        theme="light"
                        mode="inline"
                        selectedKeys={[url]}
                        items={filteredItems}
                        onClick={() => setMobileDrawerOpen(false)}
                        style={{ borderRight: "none", marginTop: 8 }}
                    />
                </Drawer>
```
5. Update header toggle button:
```jsx
                            <Button
                                type="text"
                                icon={
                                    isMobile ? (
                                        <MenuOutlined />
                                    ) : collapsed ? (
                                        <MenuUnfoldOutlined />
                                    ) : (
                                        <MenuFoldOutlined />
                                    )
                                }
                                onClick={() => {
                                    if (isMobile) {
                                        setMobileDrawerOpen(!mobileDrawerOpen);
                                    } else {
                                        setCollapsed(!collapsed);
                                    }
                                }}
                                style={{
                                    fontSize: 16,
                                    width: 40,
                                    height: 40,
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                }}
                                aria-label={
                                    isMobile
                                        ? (mobileDrawerOpen
                                            ? "Tutup menu navigasi"
                                            : "Buka menu navigasi")
                                        : (collapsed
                                            ? "Buka menu navigasi"
                                            : "Tutup menu navigasi")
                                }
                            />
```

- [ ] **Step 4: Run test and build to verify it passes**

Run:
```bash
npm run build
npx e2e run tests/e2e/responsive-navigation.e2e.ts
```
Expected: PASS with 1 test passed.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/responsive-navigation.e2e.ts resources/js/Layouts/AuthenticatedLayout.jsx
git commit -m "feat(layout): implement responsive mobile navigation drawer for AuthenticatedLayout"
```

---

### Task 2: Form Label Accessibility & Input Binding Remediation (`A11Y-001`)

**Files:**
- Create: `tests/e2e/accessibility-labels.e2e.ts`
- Modify: `resources/js/Pages/Auth/Login.jsx:208-245`
- Modify: `resources/js/Pages/Auth/Register.jsx:205-305`

**Interfaces:**
- Consumes: Ant Design `<Form.Item>` automatic `id` generation (`${formName}_${fieldName}`)
- Produces: Standards-compliant `<label for="...">` and `<input id="...">` pairs with zero console errors and full screen-reader compliance.

- [ ] **Step 1: Write the failing E2E test**

Create file `tests/e2e/accessibility-labels.e2e.ts`:

```typescript
import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Form Label Accessibility (A11Y-001)', () => {
  test('Login form inputs correctly match label htmlFor without browser binding errors', async ({ app, browser }) => {
    await app.open('/login');

    const emailLabel = await browser.$('label[for="email"], label[for="login_email"]');
    expect(emailLabel).not.toBeNull();
    const emailForAttr = await emailLabel.getAttribute('for');

    const emailInput = await browser.$(`#${emailForAttr}`);
    expect(emailInput).not.toBeNull();
    const inputType = await emailInput.getAttribute('type');
    expect(['text', 'email']).toContain(inputType);

    const passwordLabel = await browser.$('label[for="password"], label[for="login_password"]');
    expect(passwordLabel).not.toBeNull();
    const passwordForAttr = await passwordLabel.getAttribute('for');

    const passwordInput = await browser.$(`#${passwordForAttr}`);
    expect(passwordInput).not.toBeNull();
  });

  test('Register form inputs correctly match label htmlFor without browser binding errors', async ({ app, browser }) => {
    await app.open('/register');

    const fields = ['name', 'email', 'password', 'password_confirmation'];
    for (const field of fields) {
      const label = await browser.$(`label[for$="${field}"]`);
      expect(label).not.toBeNull();
      const forAttr = await label.getAttribute('for');
      const input = await browser.$(`#${forAttr}`);
      expect(input).not.toBeNull();
    }
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx e2e run tests/e2e/accessibility-labels.e2e.ts`
Expected: FAIL (because `<label for="login_email">` does not find `#login_email` since the input has `id="email"`).

- [ ] **Step 3: Remove conflicting manual IDs in Login.jsx and Register.jsx**

In `resources/js/Pages/Auth/Login.jsx`:
Remove `id="email"` from `<Input>` on line 209:
```jsx
                                <Input
                                    prefix={
                                        <UserOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="analis@perusahaan.com"
                                    autoComplete="email"
                                    autoFocus
                                />
```
Remove `id="password"` from `<Input.Password>` on line 234:
```jsx
                                <Input.Password
                                    prefix={
                                        <LockOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Masukkan kata sandi"
                                    autoComplete="current-password"
                                />
```

In `resources/js/Pages/Auth/Register.jsx`:
Remove `id="name"` from line 206:
```jsx
                                <Input
                                    prefix={
                                        <UserOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Nama lengkap"
                                    autoComplete="name"
                                    autoFocus
                                />
```
Remove `id="email"` from line 236:
```jsx
                                <Input
                                    prefix={
                                        <MailOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="analis@perusahaan.com"
                                    autoComplete="email"
                                />
```
Remove `id="password"` from line 265:
```jsx
                                <Input.Password
                                    prefix={
                                        <LockOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Minimal 8 karakter"
                                    autoComplete="new-password"
                                />
```
Remove `id="password_confirmation"` from line 299:
```jsx
                                <Input.Password
                                    prefix={
                                        <LockOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Ulangi kata sandi"
                                    autoComplete="new-password"
                                />
```

- [ ] **Step 4: Run test and build to verify it passes**

Run:
```bash
npm run build
npx e2e run tests/e2e/accessibility-labels.e2e.ts
```
Expected: PASS with 2 tests passed.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/accessibility-labels.e2e.ts resources/js/Pages/Auth/Login.jsx resources/js/Pages/Auth/Register.jsx
git commit -m "fix(a11y): remove conflicting manual input ids to restore ant design label associations"
```

---

### Task 3: WCAG 2.1 AA Color Contrast Remediation (`A11Y-002`)

**Files:**
- Create: `tests/e2e/color-contrast.e2e.ts`
- Modify: `resources/css/additional.css:8-12,108-124`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx:195-204,267-277`

**Interfaces:**
- Consumes: Design system color tokens and utility CSS classes
- Produces: Verified &ge; 4.5:1 contrast ratios on button backgrounds, tag hover states, and secondary metadata typography.

- [ ] **Step 1: Write the failing test**

Create file `tests/e2e/color-contrast.e2e.ts`:

```typescript
import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('WCAG 2.1 AA Color Contrast Remediation (A11Y-002)', () => {
  test('Action buttons and layout secondary subtitles adhere to WCAG AA compliant contrast colors', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    await app.open('/login');
    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    // Verify secondary subtitle color is #595959 (rgb(89, 89, 89))
    const subtitle = await browser.$('span:has-text("Strategic Intel")');
    if (subtitle) {
      const color = await subtitle.evaluate((el) => window.getComputedStyle(el).color);
      expect(color).toBe('rgb(89, 89, 89)');
    }

    // Verify .custom-green-button has compliant dark green background (#2e7d32 = rgb(46, 125, 50))
    const greenBtnStyle = await browser.evaluate(() => {
      const dummy = document.createElement('button');
      dummy.className = 'custom-green-button';
      document.body.appendChild(dummy);
      const bg = window.getComputedStyle(dummy).backgroundColor;
      document.body.removeChild(dummy);
      return bg;
    });
    expect(greenBtnStyle).toBe('rgb(46, 125, 50)');

    // Verify .custom-tag hover background rule (#b25900)
    const tagHoverBg = await browser.evaluate(() => {
      for (const sheet of Array.from(document.styleSheets)) {
        try {
          for (const rule of Array.from(sheet.cssRules || [])) {
            if ((rule as CSSStyleRule).selectorText === '.custom-tag:hover') {
              return (rule as CSSStyleRule).style.background;
            }
          }
        } catch {
          // ignore cross-origin sheet errors
        }
      }
      return null;
    });
    if (tagHoverBg) {
      expect(tagHoverBg).toContain('#b25900');
    }
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx e2e run tests/e2e/color-contrast.e2e.ts`
Expected: FAIL (because `.custom-green-button` has `#4caf50` and subtitle has `#8c8c8c`).

- [ ] **Step 3: Update CSS and token color definitions**

In `resources/css/additional.css`:
1. Change `.custom-tag:hover`:
```css
.custom-tag:hover {
    background: #b25900;
    color: white;
}
```
2. Change `.custom-green-button` and its hover state:
```css
.custom-green-button {
    background-color: #2e7d32;
    border-color: #2e7d32;
    color: white;
}

.custom-red-button {
    background-color: #b63232;
    border-color: #b63232;
    color: white;
}

.custom-green-button:hover {
    background-color: #1b5e20;
    border-color: #1b5e20;
}
```

In `resources/js/Layouts/AuthenticatedLayout.jsx`:
1. Add `colorTextSecondary: "#595959"` to `ConfigProvider` token (line 198):
```jsx
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: "#1677ff",
                    borderRadius: 8,
                    fontFamily:
                        "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
                    colorTextSecondary: "#595959",
                },
            }}
        >
```
2. Update subtitle style on line 270:
```jsx
                                <span
                                    style={{
                                        fontSize: 11,
                                        color: "#595959",
                                        fontWeight: 500,
                                        letterSpacing: "0.5px",
                                        textTransform: "uppercase",
                                    }}
                                >
                                    Strategic Intel
                                </span>
```

- [ ] **Step 4: Run test and build to verify it passes**

Run:
```bash
npm run build
npx e2e run tests/e2e/color-contrast.e2e.ts
```
Expected: PASS with 1 test passed.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/color-contrast.e2e.ts resources/css/additional.css resources/js/Layouts/AuthenticatedLayout.jsx
git commit -m "fix(a11y): adjust secondary text and action button colors to satisfy wcag aa contrast"
```

---

### Task 4: Clean up Analytical Filter Anomaly on User Management (`DESIGN-003`)

**Files:**
- Create: `tests/Feature/UserManagementFilterTest.php`
- Modify: `app/Models/User.php:69-106`
- Modify: `resources/js/Pages/UserManagement/User/Table.jsx:45-70,345-365,400-465`

**Interfaces:**
- Consumes: `User::filter()` query scope, `TableUser` admin component
- Produces: Clean administrative toolbar with instant user search without irrelevant date range controls, robust backend `User::filter()` that handles missing date gracefully.

- [ ] **Step 1: Write the failing feature test**

Create file `tests/Feature/UserManagementFilterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementFilterTest extends TestCase
{
    use RefreshDatabase;

    private function createRoleWithDefaults(string $name): Role
    {
        return Role::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'display_name' => ucfirst($name),
        ]);
    }

    private function createPermissionWithDefaults(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => 'user',
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_user_fetch_data_succeeds_without_date_parameter(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $adminRole = $this->createRoleWithDefaults('super-admin');
        $adminRole->givePermissionTo([$viewPerm]);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        User::factory()->count(3)->create();

        // Call fetch_data without date parameters
        $response = $this->actingAs($admin)->getJson('/user-management/user/fetch-data?pagination[pageSize]=10&pagination[current]=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
        ]);
        $this->assertGreaterThanOrEqual(4, $response->json('total'));
    }

    public function test_user_fetch_data_searches_by_name_without_date_parameter(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $adminRole = $this->createRoleWithDefaults('super-admin');
        $adminRole->givePermissionTo([$viewPerm]);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        User::factory()->create(['name' => 'Alexander Supertramp']);

        $response = $this->actingAs($admin)->getJson('/user-management/user/fetch-data?search=Alexander&pagination[pageSize]=10&pagination[current]=1');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('Alexander Supertramp', $response->json('data.0.name'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec laravel_app php artisan test --filter=UserManagementFilterTest`
Expected: FAIL with 500 error / TypeError / Undefined array key 0 because `User::filter()` tries to index `$date_range[0]`.

- [ ] **Step 3: Harden User::filter() and remove RangePicker from Table.jsx**

In `app/Models/User.php`:
Replace lines 69-106 with:
```php
    public static function filter()
    {
        $pagination = request('pagination.pageSize', 10);
        $search = request('search');
        $date_range = request('date');
        $has_date = is_array($date_range) && count($date_range) === 2 && !empty($date_range[0]) && !empty($date_range[1]);

        if (in_array('developer', auth()->user()->roles->pluck('name')->toArray())) {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name')->orderBy('id');
            });
        } else if (in_array('super-admin', auth()->user()->roles->pluck('name')->toArray())) {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name');
            })->whereHas('roles', function ($q) {
                $q->whereNotIn('name', ['qa', 'developer', 'token']);
            });
        } else {
            $users = self::query()->with('roles', function ($q) {
                $q->select('id', 'display_name');
            })->whereHas('roles', function ($q) {
                $q->whereNotIn('name', ['super-admin', 'qa', 'developer']);
            });
        }

        $users = $users->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'LIKE',  $search . '%')
                    ->orWhere('email', 'LIKE', $search . '%');
            });
        })
            ->when($has_date, function ($q) use ($date_range) {
                $q->where('created_at', '>=', $date_range[0] . ' 00:00:00')
                    ->where('created_at', '<=', $date_range[1] . ' 23:59:59');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($pagination);

        return new UserCollection($users);
    }
```

In `resources/js/Pages/UserManagement/User/Table.jsx`:
1. Remove `DatePicker` from `antd` imports and remove `const { RangePicker } = DatePicker;`.
2. Remove `defaultDate` state and remove `date` from `tableParams` initial state:
```javascript
    // Table
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [tableParams, setTableParams] = useState({
        pagination: {
            current: 1,
            pageSize: 10,
        },
        search: "",
    });
```
3. Update `useEffect` dependencies:
```javascript
    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        tableParams?.search,
    ]);
```
4. Update `handleResetFilter`:
```javascript
    const handleResetFilter = () => {
        setTableParams((prev) => ({
            ...prev,
            pagination: { ...prev.pagination, current: 1 },
            search: "",
        }));
    };
```
5. Remove `handleRangePickerChange` function.
6. Remove the entire `.radar-filter-bar` block (lines 401-460) so the page goes straight from `<Head title={title} />` and `<Content>` to `<Card>`.

- [ ] **Step 4: Run tests and build to verify it passes**

Run:
```bash
docker exec laravel_app php artisan test --filter=UserManagementFilterTest
docker exec laravel_app php artisan test
npm run build
```
Expected:
- `UserManagementFilterTest` PASS (2 passed).
- All 57 tests in PHPUnit test suite PASS.
- Vite build completes cleanly with zero errors.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/UserManagementFilterTest.php app/Models/User.php resources/js/Pages/UserManagement/User/Table.jsx
git commit -m "refactor(users): remove misplaced date range filter from user management table and harden user query filter"
```
