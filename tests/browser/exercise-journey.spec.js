import { expect, test } from "@playwright/test";

import { login, stamp, USERS } from "./support";

/**
 * Workflows B and C — one exercise, created and taken through the nine steps in a real browser, with
 * the gates checked on the way (a gate that is not passed keeps the next step locked; a library copy
 * cannot pass G3; reopening the framework withdraws later approvals). The tests run in order on one
 * signed-in page and build on each other.
 */
test.describe.configure({ mode: "serial" });

const NAME = `Elektrifikasi E2E ${stamp()}`;
const FORCES = ["Regulasi TKDN", "Teknologi Baterai Padat", "Daya Beli Konsumen"];

/** @type {import("@playwright/test").Page} */
let page;
let base = "";

const step = (path) => page.goto(`${base}/${path}`);
const confirmDialog = () => page.getByRole("alertdialog");

test.beforeAll(async ({ browser }) => {
    page = await (await browser.newContext()).newPage();
    await login(page, USERS.adminA);
});

test.afterAll(async () => {
    await page?.context().close();
});

test("B1. creates an exercise with its focal issue", async () => {
    await page.goto("/scenario-planning");
    await page.getByRole("button", { name: "Latihan baru" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Nama latihan").fill(NAME);
    await dialog.getByLabel("Pertanyaan fokus (key focal issue)").fill("Bagaimana adopsi kendaraan listrik di Indonesia berkembang hingga 2035?");
    await dialog.getByLabel("Tujuan").fill("Menyusun strategi produk kendaraan listrik.");
    await dialog.getByRole("spinbutton", { name: "Tahun target" }).fill("2035");
    await dialog.getByRole("button", { name: "Buat latihan" }).click();

    await page.waitForURL(/\/scenario-planning\/[0-9a-f-]{36}$/);
    base = new URL(page.url()).pathname;
    await expect(page.getByRole("heading", { name: NAME })).toBeVisible();
});

test("B2. edits and saves the exercise, and finds it again in the list", async () => {
    await page.getByRole("button", { name: "Ubah" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Industri").fill("Kendaraan roda empat");
    await dialog.getByRole("button", { name: "Simpan" }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText("Kendaraan roda empat")).toBeVisible();

    await page.goto("/scenario-planning");
    await page.getByRole("link", { name: new RegExp(NAME) }).click();
    await expect(page).toHaveURL(new RegExp(`${base}$`));
});

test("C1. step 2 stays locked until the focal issue is confirmed (G1)", async () => {
    await step("driving-forces");
    await expect(page.getByText("Konfirmasi focal issue dulu")).toBeVisible();

    await page.goto(base);
    await page.getByRole("button", { name: "Konfirmasi", exact: true }).click();
    await confirmDialog().getByLabel("Alasan (opsional)").fill("Disepakati di lokakarya E2E.");
    await confirmDialog().getByRole("button", { name: "Konfirmasi" }).click();
    await expect(confirmDialog()).toBeHidden();
    // The decision log keeps the reason (on G1 and on the status change to active, made by the same request).
    await expect(page.getByRole("region", { name: "Riwayat keputusan" }).getByText("Alasan: Disepakati di lokakarya E2E.").first()).toBeVisible();
});

test("C2. adds library forces and agrees the list (G2)", async () => {
    await step("driving-forces");
    await page.getByRole("button", { name: /pustaka/i }).first().click();
    const dialog = page.getByRole("dialog");
    for (const force of FORCES) {
        await dialog.getByRole("checkbox", { name: new RegExp(force) }).check();
    }
    await dialog.getByRole("button", { name: /Tambah|Simpan/ }).last().click();
    await expect(dialog).toBeHidden();
    for (const force of FORCES) {
        await expect(page.getByText(force, { exact: true }).first()).toBeVisible();
    }

    await page.getByRole("button", { name: "Sepakati daftar" }).click();
    await confirmDialog().getByRole("button", { name: "Sepakati" }).click();
    await expect(confirmDialog()).toBeHidden();
});

test("C3. library scores must be reviewed before the scores are agreed (G3)", async () => {
    await step("assessment");
    const agree = page.getByRole("button", { name: /skor library belum ditinjau/ });
    await expect(agree).toBeDisabled();

    for (const force of FORCES) {
        await page.getByRole("button", { name: `Pakai nilai library untuk ${force}` }).click();
        await expect(page.getByRole("button", { name: `Pakai nilai library untuk ${force}` })).toHaveCount(0);
    }
    await page.getByRole("button", { name: "Sepakati skor" }).click();
    await confirmDialog().getByRole("button", { name: "Sepakati" }).click();
    await expect(page.getByText(/Disepakati oleh/)).toBeVisible();
});

/** Proposes one critical uncertainty from one force and approves it as the given axis (G4). */
async function proposeAndApprove(name, force, low, high, axis) {
    await page.getByRole("button", { name: "Usulan baru" }).click();
    const form = page.getByRole("dialog");
    await form.getByRole("textbox", { name: "Nama", exact: true }).fill(name);
    await form.getByRole("textbox", { name: "Kutub rendah" }).fill(low);
    await form.getByRole("textbox", { name: "Kutub tinggi" }).fill(high);
    await form.getByRole("textbox", { name: "Alasan pemilihan" }).fill("Dampak dan ketidakpastian tertinggi.");
    await form.getByRole("checkbox", { name: new RegExp(force) }).check();
    await form.getByRole("button", { name: "Simpan usulan" }).click();
    await expect(form).toBeHidden();

    // The card around the uncertainty's heading (the nearest ancestor that holds its approve button).
    const card = page.getByRole("heading", { name, exact: true }).locator("xpath=ancestor::*[.//button[normalize-space()='Setujui sebagai sumbu']][1]");
    await card.getByRole("button", { name: "Setujui sebagai sumbu" }).click();
    const approve = page.getByRole("dialog");
    await approve.getByRole("radio", { name: `Sumbu ${axis}` }).click();
    await approve.getByRole("button", { name: "Setujui" }).click();
    await expect(approve).toBeHidden();
}

test("C4. two critical uncertainties become the axes (G4)", async () => {
    await step("uncertainties");
    await proposeAndApprove("Regulasi lokal", "Regulasi TKDN", "Longgar", "Ketat", "A");
    await proposeAndApprove("Kematangan baterai", "Teknologi Baterai Padat", "Lambat", "Cepat", "B");
    await expect(page.getByText(/Dua critical uncertainty disetujui|sumbu B/).first()).toBeVisible();
});

test("C5. the framework is created and approved (G5); four quadrants exist", async () => {
    await step("framework");
    await page.getByRole("button", { name: "Buat kerangka" }).click();
    await page.getByRole("button", { name: "Setujui kerangka" }).click();
    await confirmDialog().getByRole("button", { name: "Setujui" }).click();
    await expect(confirmDialog()).toBeHidden();
    await expect(page.getByRole("button", { name: "Buka kembali" })).toBeVisible();
});

test("C6. a condition is added to a quadrant (step 5)", async () => {
    await step("conditions");
    await page.getByRole("button", { name: "Tambah kondisi" }).first().click();
    const dialog = page.getByRole("dialog");
    await dialog.getByRole("textbox", { name: "Kondisi" }).fill("Insentif pajak kendaraan listrik diperpanjang hingga 2035.");
    await dialog.getByRole("button", { name: "Simpan kondisi" }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText("Insentif pajak kendaraan listrik diperpanjang hingga 2035.")).toBeVisible();
});

test("C7. all four narratives are written and approved (G6)", async () => {
    await step("narratives");
    for (let i = 0; i < 4; i++) {
        // Each round edits the first quadrant that still offers "Sunting" without an approval.
        const edit = page.getByRole("button", { name: "Sunting" }).nth(i);
        await edit.click();
        const sheet = page.getByRole("dialog", { name: "Sunting kuadran" });
        // Wait until the sheet has loaded the quadrant (its title is filled in), so the form's reset on
        // open cannot wipe what is typed next.
        await expect(sheet.getByRole("textbox", { name: "Judul" })).toHaveValue(/·/);
        const narrative = `Narasi kuadran ${i + 1}: adopsi kendaraan listrik mengikuti regulasi dan kematangan baterai.`;
        await sheet.getByRole("textbox", { name: "Narasi" }).fill(narrative);
        await expect(sheet.getByRole("textbox", { name: "Narasi" })).toHaveValue(narrative);
        await sheet.getByRole("button", { name: "Simpan", exact: true }).click();
        await expect(sheet).toBeHidden();
    }
    // Approve each quadrant through its own card and wait for that card to show the approval.
    for (const title of ["Longgar · Cepat", "Longgar · Lambat", "Ketat · Cepat", "Ketat · Lambat"]) {
        const card = page.getByRole("heading", { name: title, exact: true }).locator("xpath=ancestor::*[.//button[normalize-space()='Sunting']][1]");
        await card.getByRole("button", { name: "Setujui", exact: true }).click();
        await expect(card.getByText("Disetujui", { exact: true })).toBeVisible();
    }
    await expect(page.getByText("4 dari 4 narasi disetujui (G6)")).toBeVisible();
});

test("C8. a challenge or opportunity is recorded for a quadrant (step 7)", async () => {
    await step("implications");
    await page.getByRole("button", { name: "Tambah item" }).first().click();
    const dialog = page.getByRole("dialog");
    await dialog.getByRole("textbox", { name: "Judul" }).fill("Pasar baterai domestik tumbuh");
    await dialog.getByRole("button", { name: "Simpan item" }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText("Pasar baterai domestik tumbuh")).toBeVisible();
});

/** Picks an option in a Radix select (combobox) inside `scope`. */
async function choose(scope, label, option) {
    await scope.getByRole("combobox", { name: label }).click();
    await page.getByRole("option", { name: option }).first().click();
}

const QUADRANTS = ["Ketat · Cepat", "Ketat · Lambat", "Longgar · Cepat", "Longgar · Lambat"];

test("C9. a strategic response is drafted and approved with a reason (G7)", async () => {
    await step("responses");
    await page.getByRole("button", { name: "Respons baru" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByRole("textbox", { name: "Judul" }).fill("Amankan pasokan sel baterai lokal");
    await dialog.getByRole("textbox", { name: "Alasan (wajib untuk persetujuan)" }).fill("Tahan di tiga dari empat masa depan.");
    for (const quadrant of QUADRANTS) {
        await dialog.getByRole("radiogroup", { name: `Kecocokan di ${quadrant}` }).getByRole("radio", { name: quadrant === "Longgar · Lambat" ? "Sebagian" : "Cocok", exact: true }).click();
    }
    await dialog.getByRole("checkbox", { name: /Pasar baterai domestik tumbuh/ }).check();
    await dialog.getByRole("button", { name: "Simpan respons" }).click();
    await expect(dialog).toBeHidden();

    await page.getByRole("button", { name: "Setujui", exact: true }).click();
    await confirmDialog().getByLabel("Alasan (opsional)").fill("Disetujui komite strategi.");
    await confirmDialog().getByRole("button", { name: "Setujui" }).click();
    await expect(confirmDialog()).toBeHidden();
    await expect(page.getByText(/Disetujui oleh/)).toBeVisible();
});

test("C10. an owned early warning indicator tells both axes apart (step 9)", async () => {
    await step("indicators");
    await page.getByRole("button", { name: "Indikator baru" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByRole("textbox", { name: "Nama", exact: true }).fill("Pangsa penjualan kendaraan listrik");
    const directions = { "Ketat · Cepat": "↑ Naik", "Ketat · Lambat": "→ Stabil", "Longgar · Cepat": "↓ Turun", "Longgar · Lambat": "↑ Naik" };
    for (const [quadrant, direction] of Object.entries(directions)) {
        await dialog.getByRole("radiogroup", { name: `Arah di ${quadrant}` }).getByRole("radio", { name: direction, exact: true }).click();
    }
    await dialog.getByRole("textbox", { name: "Metrik" }).fill("Pangsa EV");
    await dialog.getByRole("textbox", { name: "Satuan" }).fill("%");
    await choose(dialog, "Penanggung jawab", "Admin Uji A");
    await choose(dialog, "Operator", /lebih dari|>/i);
    await dialog.getByRole("spinbutton", { name: "Nilai ambang" }).fill("50");
    await dialog.getByRole("button", { name: "Simpan indikator" }).click();
    await expect(dialog).toBeHidden();

    await expect(page.getByText("Pangsa penjualan kendaraan listrik").first()).toBeVisible();
    // Owned and discriminating: step 9 is done, so its gap list is gone.
    await expect(page.getByText("Langkah 9 belum selesai:")).toHaveCount(0);
});

/** Records a numeric observation on the indicator card through the observation dialog. */
async function observe(value) {
    await page.getByRole("button", { name: "Catat observasi" }).first().click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel(/^Nilai/).fill(String(value));
    await expect(dialog.getByText(/melewati ambang|belum melewati ambang/).first()).toBeVisible();
    await dialog.getByRole("button", { name: "Catat observasi" }).click();
    await expect(dialog).toBeHidden();
}

test("E1. an observation below the threshold creates no event", async () => {
    await step("indicators");
    await observe(40);
    await expect(page.getByText(/Ambang terlewati/)).toHaveCount(0);
});

test("E2. a crossing observation creates exactly one event, decided by the system", async () => {
    await observe(65);
    await expect(page.getByText("Ambang terlewati 1×")).toBeVisible();
    await expect(page.getByText(/nilai 65/)).toBeVisible();
});

test("E3. one event per crossing observation, kept after a reload (no duplicates)", async () => {
    await observe(70);
    await expect(page.getByText("Ambang terlewati 2×")).toBeVisible();
    await page.reload();
    await expect(page.getByText("Ambang terlewati 2×")).toBeVisible();
});

test("E4. a threshold crossing flags the strategic response for review", async () => {
    await step("responses");
    await expect(page.getByText("Kondisi yang diandalkan berubah. Tinjau respons ini.")).toBeVisible();
    await expect(page.getByText(/melewati ambang batas \(> 50, nilai 65\)/)).toBeVisible();
    await expect(page.getByRole("button", { name: "Sudah ditinjau, pertahankan" })).toBeVisible();
});

test("C11. reopening the framework withdraws the narrative and response approvals (G6, G7)", async () => {
    await step("framework");
    await page.getByRole("button", { name: "Buka kembali" }).click();
    await confirmDialog().getByRole("button", { name: "Buka kembali" }).click();
    await expect(confirmDialog()).toBeHidden();

    // Steps 5–9 are locked again and flagged; the narratives wait for a new framework approval.
    await step("narratives");
    await expect(page.getByText("Setujui kerangka skenario (langkah 4) dulu sebelum menulis narasi.")).toBeVisible();
    await expect(page.getByText("Ada perubahan di langkah sebelumnya. Tinjau kerangka dan skenario yang terdampak.")).toBeVisible();
    for (const locked of ["6. Narratives Imagination: Terkunci", "8. Strategic Response: Terkunci", "9. Early Warning Signal: Terkunci"]) {
        await expect(page.getByRole("button", { name: locked })).toBeDisabled();
    }
    await step("responses");
    await expect(page.getByText(/Disetujui oleh/)).toHaveCount(0);

    await page.goto(base);
    const log = page.getByRole("region", { name: "Riwayat keputusan" });
    await expect(log.getByText(/G6/).first()).toBeVisible();
    await expect(log.getByText(/G7/).first()).toBeVisible();
    await expect(log.getByText(/Dibatalkan/).first()).toBeVisible();
});

test("B3. the exercise is archived, and the archive is visible in the list", async () => {
    await page.goto(base);
    await page.getByRole("button", { name: "Arsipkan" }).click();
    await confirmDialog().getByRole("button", { name: "Arsipkan" }).click();
    await expect(confirmDialog()).toBeHidden();
    await expect(page.getByRole("button", { name: "Aktifkan kembali" })).toBeVisible();

    await page.goto("/scenario-planning");
    const card = page.getByRole("link", { name: new RegExp(NAME) });
    await expect(card.getByText(/Arsip|Diarsipkan/)).toBeVisible();
});
