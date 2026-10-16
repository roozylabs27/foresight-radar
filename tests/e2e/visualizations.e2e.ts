import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Visualization & Analytical Reporting E2E Flows', () => {
  test('Happy Path: Foresight Radar renders chart canvas and filter controls', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/foresight-radar');
    await expect(browser).toHaveURL('/visualization/foresight-radar');

    // Verify radar header and filter bar
    await expect(screen.getByRole('heading', 'Foresight Radar')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByRole('region', 'Filter analisis')).toBeVisible();
    await expect(screen.getByRole('button', /Periode/)).toBeVisible();
    await expect(screen.getByRole('combobox', 'Dimensi')).toBeVisible();

    // Verify chart container exists
    await expect(screen.getByText(/Horizon 1 \/ 2 \/ 3/)).toBeVisible();
  });

  test('Happy Path: Prioritizing chart renders with matrix visual container', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/prioritizing');
    await expect(browser).toHaveURL('/visualization/prioritizing');

    // Verify prioritizing heading and matrix components
    await expect(screen.getByRole('heading', 'Prioritizing')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByRole('region', 'Filter analisis')).toBeVisible();
  });

  test('Happy Path: Registered List displays status table and segmented info view', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/registered-list');
    await expect(browser).toHaveURL('/visualization/registered-list');

    // Verify page heading and table
    await expect(screen.getByRole('heading', 'Registered List')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Overall')).toBeVisible();
    await expect(screen.getByText('Detail Info')).toBeVisible();

    // Verify export button is present
    await expect(screen.getByRole('button', /Export to Excel/)).toBeVisible();

    // Switch view to Detail Info
    await screen.getByText('Detail Info').tap();
    await expect(screen.getByText(/DIMENSION|ITEM/)).toBeVisible({ timeout: 20000 });
  });

  test('Happy Path: Cross-Impact page shows the matrix and the Vester summary', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/cross-impact');
    await expect(browser).toHaveURL('/visualization/cross-impact');

    await expect(screen.getByRole('heading', 'Cross-Impact')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByRole('heading', 'Matriks Cross-Impact')).toBeVisible({ timeout: 45000 });
    await expect(screen.getByText(/\d+ dari \d+ pasangan dinilai/)).toBeVisible({ timeout: 45000 });
    await expect(screen.getByRole('heading', 'Ringkasan Pengaruh per Driving Force')).toBeVisible({ timeout: 45000 });
  });

  test('Happy Path: Foresight Radar toggles cross-impact links', { session: 'admin' }, async ({ app, screen }) => {
    await app.open('/visualization/foresight-radar');

    await screen.getByRole('button', 'Cross-Impact').tap();
    await expect(screen.getByText(/hubungan cross-impact antar force di radar|Belum ada cross-impact antar force/)).toBeVisible({ timeout: 45000 });
    await expect(screen.getByText('Memperkuat')).toBeVisible();
    await expect(screen.getByText('Menekan')).toBeVisible();
  });

  test('Unhappy & Boundary Path: dimension filter handles switching and reset gracefully', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/foresight-radar');

    // Open dimension selector and pick a filter
    await screen.getByRole('combobox', 'Dimensi').tap();
    await screen.getByRole('option', 'Economy').tap();

    // Verify reset filter button appears when active filters differ from default
    const resetBtn = screen.getByRole('button', /Reset/);
    await expect(resetBtn).toBeVisible({ timeout: 20000 });

    // Tap reset to restore standard view
    await resetBtn.tap();
    await expect(screen.getByText('Semua Dimensi')).toBeVisible({ timeout: 20000 });
  });
});
