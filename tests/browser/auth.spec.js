import { expect, test } from "@playwright/test";

import { login, PASSWORD, USERS } from "./support";

/** Workflow A — authentication, sessions, authorisation and tenant isolation, in a real browser. */
test.describe("A. Authentication and tenant isolation", () => {
    test("signs in, shows the user's workspace, and signs out", async ({ page }) => {
        await login(page, USERS.adminA);
        await expect(page.getByText(USERS.adminA.name).first()).toBeVisible();

        await page.getByRole("button", { name: new RegExp(USERS.adminA.name) }).first().click();
        await page.getByRole("menuitem", { name: /Keluar/ }).click();
        await expect(page).toHaveURL(/\/login/);

        // After logout a protected page sends the browser back to the login form.
        await page.goto("/dashboard");
        await expect(page).toHaveURL(/\/login/);
    });

    test("rejects invalid credentials and stays on the login form", async ({ page }) => {
        await page.goto("/login");
        await page.getByLabel("Email Kerja").fill(USERS.adminA.email);
        await page.locator("#password").fill(`${PASSWORD}-wrong`);
        await page.getByRole("button", { name: "Masuk ke Platform" }).click();

        await expect(page.getByRole("alert").or(page.getByText(/tidak cocok|salah|gagal|credentials/i)).first()).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });

    test("an expired session (cookies gone) returns to the login form", async ({ page, context }) => {
        await login(page, USERS.adminA);
        await context.clearCookies();

        await page.goto("/scenario-planning");
        await expect(page).toHaveURL(/\/login/);
    });

    test("a viewer cannot open an administration page", async ({ page }) => {
        await login(page, USERS.viewerA);

        const response = await page.goto("/user-management/user");
        expect(response?.status()).toBe(403);
        await expect(page.getByRole("heading", { name: "Akses Ditolak" })).toBeVisible();
    });

    test("a viewer sees exercises but cannot create one", async ({ page }) => {
        await login(page, USERS.viewerA);
        await page.goto("/scenario-planning");

        await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
        await expect(page.getByRole("button", { name: /Exercise baru|Latihan baru|Buat/ })).toHaveCount(0);
    });

    test("organisation A can neither list nor open organisation B's exercise", async ({ browser }) => {
        // Organisation B opens its own exercise and takes the address.
        const contextB = await browser.newContext();
        const pageB = await contextB.newPage();
        await login(pageB, USERS.adminB);
        await pageB.goto("/scenario-planning");
        await pageB.getByRole("link", { name: /Rahasia Organisasi B/ }).first().click();
        await expect(pageB).toHaveURL(/\/scenario-planning\/[0-9a-f-]{36}/);
        const secretUrl = new URL(pageB.url()).pathname;
        await contextB.close();

        // Organisation A: not in the list, and the direct address is a 404 that leaks nothing.
        const contextA = await browser.newContext();
        const pageA = await contextA.newPage();
        await login(pageA, USERS.adminA);
        await pageA.goto("/scenario-planning");
        await expect(pageA.getByText("Rahasia Organisasi B")).toHaveCount(0);

        const response = await pageA.goto(secretUrl);
        expect(response?.status()).toBe(404);
        await expect(pageA.getByText("Rahasia Organisasi B")).toHaveCount(0);
        await expect(pageA.getByText("Hanya organisasi B")).toHaveCount(0);

        // The exercise data endpoint behind the page leaks nothing either.
        const api = await pageA.request.get(`${secretUrl}/assessment`, { headers: { Accept: "application/json" } });
        expect([403, 404]).toContain(api.status());
        expect(await api.text()).not.toContain("Rahasia");
        await contextA.close();
    });
});
