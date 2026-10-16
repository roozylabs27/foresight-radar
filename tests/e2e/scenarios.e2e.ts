import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Scenario Matrix E2E Flows', () => {
  test('Without an API key: create a scenario, build the template skeleton, then delete it', { session: 'admin' }, async ({ app, screen, browser }) => {
    const name = `E2E Skenario ${Date.now()}`;

    await app.open('/scenarios');
    await expect(browser).toHaveURL('/scenarios');
    await expect(screen.getByRole('heading', 'Scenarios')).toBeVisible({ timeout: 45000 });

    await screen.getByRole('button', 'Skenario baru').tap();
    await screen.getByRole('textbox', 'Nama skenario').fill(name);
    await screen.getByRole('textbox', 'Pertanyaan fokus').fill('Bagaimana pasar kendaraan listrik pada 2030?');

    await screen.getByRole('combobox', 'Sumbu A (horizontal)').tap();
    await screen.getByRole('option').first().tap();
    await screen.getByRole('combobox', 'Sumbu B (vertikal)').tap();
    await screen.getByRole('option').nth(1).tap();

    await screen.getByRole('textbox', 'Kutub rendah A').fill('Longgar');
    await screen.getByRole('textbox', 'Kutub tinggi A').fill('Ketat');
    await screen.getByRole('textbox', 'Kutub rendah B').fill('Lambat');
    await screen.getByRole('textbox', 'Kutub tinggi B').fill('Cepat');
    await screen.getByRole('button', 'Buat skenario').tap();

    await expect(screen.getByRole('heading', name)).toBeVisible({ timeout: 45000 });
    await expect(screen.getByText(/Tanpa API key: tombol ini membuat kerangka template tanpa AI/)).toBeVisible();

    await screen.getByRole('button', 'Buat narasi').tap();
    await expect(screen.getByText('Ketat · Cepat')).toBeVisible({ timeout: 45000 });
    await expect(screen.getByText('Longgar · Lambat')).toBeVisible();
    await expect(screen.getByText('Kerangka tanpa AI').first()).toBeVisible();
    await expect(screen.getByText(/^Kerangka tanpa AI\. Bayangkan masa depan/).first()).toBeVisible();

    await screen.getByRole('button', 'Hapus').first().tap();
    await screen.getByRole('button', 'Hapus').last().tap();
    await expect(screen.getByRole('heading', name)).not.toBeVisible({ timeout: 45000 });
  });
});
