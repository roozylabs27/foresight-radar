import { expect, test } from "@playwright/test";

import { login, stamp, USERS } from "./support";

/**
 * Workflow D — AI assists in the browser, with the E2E scripted client (App\Services\Llm\E2eScriptedLlmClient)
 * behind a real queue worker. This proves the app's handling (pending, done, failed, retry, duplicate
 * prevention, persistence, authorisation); it does NOT prove a real AI provider. Markers in the focal
 * question steer the double: [e2e-slow] answers after a few seconds, [e2e-fail] fails like an overloaded
 * provider. Recovery of a job lost with its worker needs a 15-minute stale window and is covered by
 * PHPUnit (ProjectAssessmentTest, PI-10).
 */
test.describe.configure({ mode: "serial" });

/** @type {import("@playwright/test").Page} */
let page;

test.beforeAll(async ({ browser }) => {
    page = await (await browser.newContext()).newPage();
    await login(page, USERS.adminA);
});

test.afterAll(async () => {
    await page?.context().close();
});

async function createExercise(name, question) {
    await page.goto("/scenario-planning");
    await page.getByRole("button", { name: "Latihan baru" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Nama latihan").fill(name);
    await dialog.getByLabel("Pertanyaan fokus (key focal issue)").fill(question);
    await dialog.getByLabel("Tujuan").fill("Uji asisten AI.");
    await dialog.getByRole("spinbutton", { name: "Tahun target" }).fill("2035");
    await dialog.getByRole("button", { name: "Buat latihan" }).click();
    await page.waitForURL(/\/scenario-planning\/[0-9a-f-]{36}$/);
    return new URL(page.url()).pathname;
}

const askAi = () => page.getByRole("button", { name: "Minta tinjauan pertanyaan fokus AI" });

let slowExercise = "";

test("D1. shows the pending state, blocks a second request, then shows and keeps the result", async () => {
    slowExercise = await createExercise(`AI lambat ${stamp()}`, "Bagaimana kendaraan listrik di Indonesia? [e2e-slow]");
    // The page follows the job through Server-Sent Events, not by polling.
    const stream = page.waitForResponse((response) => response.url().includes("/ai-status/stream"));
    await askAi().click();
    const streamResponse = await stream;
    expect(streamResponse.status()).toBe(200);
    expect(streamResponse.headers()["content-type"]).toContain("text/event-stream");

    await expect(page.getByText("AI sedang meninjau pertanyaan fokus…")).toBeVisible();
    // While the job runs the assist cannot be asked again (no duplicate submission).
    await expect(askAi()).toHaveCount(0);

    await expect(page.getByText("Tinjauan AI atas pertanyaan fokus")).toBeVisible({ timeout: 60_000 });
    await expect(page.getByText(/Bagaimana adopsi kendaraan listrik di Indonesia berkembang hingga 2035/)).toBeVisible();

    // The suggestion is stored, not only shown: it is still there after a reload.
    await page.reload();
    await expect(page.getByText("Tinjauan AI atas pertanyaan fokus")).toBeVisible();
});

test("D2. a failed generation explains itself and can be retried", async () => {
    await createExercise(`AI gagal ${stamp()}`, "Bagaimana pasar baterai berubah? [e2e-fail]");
    await askAi().click();

    await expect(page.getByText("Layanan AI sedang sibuk. Coba lagi sebentar lagi.")).toBeVisible({ timeout: 60_000 });
    // Retry: the assist is offered again after the failure.
    await expect(askAi()).toBeEnabled();
    await askAi().click();
    await expect(page.getByText("Layanan AI sedang sibuk. Coba lagi sebentar lagi.")).toBeVisible({ timeout: 60_000 });
});

test("D3. another organisation cannot trigger or read the assist of this exercise", async ({ browser }) => {
    const context = await browser.newContext();
    const other = await context.newPage();
    await login(other, USERS.adminB);
    const xsrf = (await context.cookies()).find((cookie) => cookie.name === "XSRF-TOKEN")?.value ?? "";

    const trigger = await other.request.post(`${slowExercise}/focal/suggest`, {
        headers: { "X-XSRF-TOKEN": decodeURIComponent(xsrf), Accept: "application/json" },
    });
    expect([403, 404]).toContain(trigger.status());

    const read = await other.goto(slowExercise);
    expect(read?.status()).toBe(404);
    await expect(other.getByText("Tinjauan AI atas pertanyaan fokus")).toHaveCount(0);
    await context.close();
});
