import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Scenario Matrix E2E Flows', () => {
  // Passes with or without an LLM key in .env. Both axes are seeded forces without ACCEPTED
  // signals, so with a key the generate request is rejected (422) before the LLM is called.
  test('Create a scenario, generate it (template skeleton, or a 422 without evidence when an LLM is set), then delete it', { session: 'admin' }, async ({ app, screen, browser }) => {
    const name = `E2E Skenario ${Date.now()}`;

    await app.open('/scenarios');
    await expect(browser).toHaveURL('/scenarios');
    await expect(screen.getByRole('heading', 'Scenarios')).toBeVisible({ timeout: 45000 });

    await screen.getByRole('button', 'Skenario baru').tap();
    await screen.getByRole('textbox', 'Nama skenario').fill(name);
    await screen.getByRole('textbox', 'Pertanyaan fokus').fill('Bagaimana pasar kendaraan listrik pada 2030?');

    await screen.getByRole('combobox', 'Sumbu A (horizontal)').tap();
    await screen.getByRole('option', /^Euro-5 Plan /).tap();
    await screen.getByRole('combobox', 'Sumbu B (vertikal)').tap();
    await screen.getByRole('option', /^Minimum Wage /).tap();

    await screen.getByRole('textbox', 'Kutub rendah A').fill('Longgar');
    await screen.getByRole('textbox', 'Kutub tinggi A').fill('Ketat');
    await screen.getByRole('textbox', 'Kutub rendah B').fill('Lambat');
    await screen.getByRole('textbox', 'Kutub tinggi B').fill('Cepat');
    await screen.getByRole('button', 'Buat skenario').tap();

    await expect(screen.getByRole('heading', name)).toBeVisible({ timeout: 45000 });
    const mode = screen.getByText(/^(Narasi ditulis oleh .+ dari sinyal ACCEPTED|Tanpa API key: tombol ini membuat kerangka template tanpa AI)/);
    await expect(mode).toBeVisible({ timeout: 45000 });
    const llmAvailable = (await mode.textContent())?.startsWith('Narasi ditulis oleh') ?? false;

    await screen.getByRole('button', 'Buat narasi').tap();
    if (llmAvailable) {
      await expect(screen.getByText('Narasi tidak dibuat.')).toBeVisible({ timeout: 45000 });
      await expect(screen.getByText(/Sumbu A \(Euro-5 Plan\) belum punya sinyal ACCEPTED yang tertaut/)).toBeVisible();
      await expect(screen.getByText(/^Belum ada kuadran\./)).toBeVisible();
    } else {
      await expect(screen.getByText('Ketat · Cepat')).toBeVisible({ timeout: 45000 });
      await expect(screen.getByText('Longgar · Lambat')).toBeVisible();
      await expect(screen.getByText('Kerangka tanpa AI').first()).toBeVisible();
      await expect(screen.getByText(/^Kerangka tanpa AI\. Bayangkan masa depan/).first()).toBeVisible();
    }

    await screen.getByRole('button', 'Hapus').first().tap();
    await screen.getByRole('button', 'Hapus').last().tap();
    await expect(screen.getByRole('heading', name)).not.toBeVisible({ timeout: 45000 });
  });
});
