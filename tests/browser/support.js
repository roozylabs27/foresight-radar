import { expect } from "@playwright/test";

/**
 * Fixture accounts of database/seeders/E2eSeeder.php (isolated E2E stack only). The password comes from
 * docker/e2e/e2e.env through scripts/e2e/run.sh; it is a local throw-away value.
 */
export const USERS = {
    adminA: { email: "admin-a@e2e.test", name: "Admin Uji A" },
    viewerA: { email: "viewer-a@e2e.test", name: "Direksi Uji A" },
    adminB: { email: "admin-b@e2e.test", name: "Admin Uji B" },
};

export const PASSWORD = process.env.E2E_PASSWORD ?? "";

/** Sign in through the real login form and wait until the app has left the login page. */
export async function login(page, user) {
    expect(PASSWORD, "E2E_PASSWORD must be set (run through scripts/e2e/run.sh)").not.toBe("");
    await page.goto("/login");
    await page.getByLabel("Email Kerja").fill(user.email);
    await page.locator("#password").fill(PASSWORD);
    await page.getByRole("button", { name: "Masuk ke Platform" }).click();
    // The first page after login (the radar) is slow on a cold worker of the E2E stack.
    await expect(page).not.toHaveURL(/\/login/, { timeout: 60_000 });
}

/** A unique, readable suffix so data created by one run never collides with another. */
export function stamp() {
    return Date.now().toString(36).slice(-5);
}
