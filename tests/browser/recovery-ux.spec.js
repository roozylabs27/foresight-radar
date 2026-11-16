import { expect, test } from "@playwright/test";

import { login, stamp, USERS } from "./support";

/**
 * Workflow F — recovery and operational UX: unsaved changes, failed network requests, an expired
 * session during an action, a refresh during a running AI job, and empty states.
 */
test.describe.configure({ mode: "serial" });

/** @type {import("@playwright/test").Page} */
let page;
let base = "";

test.beforeAll(async ({ browser }) => {
    page = await (await browser.newContext()).newPage();
    await login(page, USERS.adminA);
    await page.goto("/scenario-planning");
    await page.getByRole("button", { name: "Latihan baru" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Nama latihan").fill(`UX ${stamp()}`);
    await dialog.getByLabel("Pertanyaan fokus (key focal issue)").fill("Bagaimana kendaraan listrik di Indonesia berkembang hingga 2035? [e2e-slow]");
    await dialog.getByLabel("Tujuan").fill("Uji pemulihan.");
    await dialog.getByRole("spinbutton", { name: "Tahun target" }).fill("2035");
    await dialog.getByRole("button", { name: "Buat latihan" }).click();
    await page.waitForURL(/\/scenario-planning\/[0-9a-f-]{36}$/);
    base = new URL(page.url()).pathname;
});

test.afterAll(async () => {
    await page?.context().close();
});

test("F1. closing a form with unsaved input asks before discarding it", async () => {
    await page.getByRole("button", { name: "Ubah" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Industri").fill("Belum disimpan");
    await page.keyboard.press("Escape");

    const discard = page.getByRole("alertdialog", { name: "Buang perubahan?" });
    await expect(discard).toBeVisible();
    await discard.getByRole("button", { name: "Lanjut mengisi" }).click();
    await expect(dialog.getByLabel("Industri")).toHaveValue("Belum disimpan");

    await page.keyboard.press("Escape");
    await page.getByRole("alertdialog", { name: "Buang perubahan?" }).getByRole("button", { name: "Buang" }).click();
    await expect(page.getByRole("dialog")).toHaveCount(0);
    await expect(page.getByText("Belum disimpan")).toHaveCount(0);
});

test("F2. an empty step explains what to do next", async () => {
    await page.goto(`${base}/driving-forces`);
    await expect(page.getByText("Belum ada driving force. Tambahkan dari pustaka organisasi.")).toBeVisible();
    await page.goto(base);
});

test("F3. a failed network request tells the user and changes nothing", async () => {
    await page.route("**/confirm", (route) => route.abort("internetdisconnected"));
    await page.getByRole("button", { name: "Konfirmasi", exact: true }).click();
    await page.getByRole("alertdialog").getByRole("button", { name: "Konfirmasi" }).click();

    await expect(page.getByText(/Koneksi ke server gagal/)).toBeVisible();
    await page.unroute("**/confirm");
    await page.reload();
    // Not confirmed: the confirm button is still offered.
    await expect(page.getByRole("button", { name: "Konfirmasi", exact: true })).toBeVisible();
});

test("F4. refreshing while an AI job runs keeps showing it as running", async () => {
    await page.getByRole("button", { name: "Minta tinjauan pertanyaan fokus AI" }).click();
    await expect(page.getByText("AI sedang meninjau pertanyaan fokus…")).toBeVisible();
    await page.reload();
    await expect(page.getByText("AI sedang meninjau pertanyaan fokus…").or(page.getByText("Tinjauan AI atas pertanyaan fokus"))).toBeVisible();
    await expect(page.getByText("Tinjauan AI atas pertanyaan fokus")).toBeVisible({ timeout: 60_000 });
});

test("F5. an action after the session expired leads back to the login form, not a silent failure", async () => {
    await page.context().clearCookies();
    await page.getByRole("button", { name: "Konfirmasi", exact: true }).click();
    await page.getByRole("alertdialog").getByRole("button", { name: "Konfirmasi" }).click();

    // Laravel answers 419 (CSRF / session expired) or redirects to login; either way the user is told.
    await expect(page.getByText(/Sesi Kedaluwarsa|419|Masuk ke Foresight Radar/).first()).toBeVisible();
});
