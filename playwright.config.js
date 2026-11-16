import { defineConfig, devices } from "@playwright/test";

/**
 * Browser E2E (production readiness 2026-11-13): Playwright against the isolated E2E stack
 * (docker-compose.e2e.yml, seeded by database/seeders/E2eSeeder.php). Run it with scripts/e2e/run.sh,
 * which starts the stack and passes E2E_BASE_URL and E2E_PASSWORD. Never point it at production.
 *
 * The specs share one seeded database and build on each other's data inside a file (serial mode),
 * so files run one at a time.
 */
export default defineConfig({
    testDir: "tests/browser",
    testMatch: "**/*.spec.js",
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    // The E2E stack runs PHP's built-in server over a bind mount: slower than production, so generous limits.
    timeout: 120_000,
    expect: { timeout: 20_000 },
    reporter: process.env.CI ? [["list"], ["html", { open: "never", outputFolder: "storage/e2e-report" }]] : [["list"]],
    outputDir: "storage/e2e-results",
    use: {
        baseURL: process.env.E2E_BASE_URL ?? "http://127.0.0.1:8091",
        locale: "id-ID",
        timezoneId: "Asia/Jakarta",
        trace: "retain-on-failure",
        screenshot: "only-on-failure",
        video: "off",
        navigationTimeout: 30_000,
        actionTimeout: 20_000,
    },
    projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
