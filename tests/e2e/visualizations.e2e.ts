import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Visualization & Analytical Reporting E2E Flows', () => {
  test('Happy Path: Foresight Radar renders chart canvas and filter controls', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/foresight-radar');
    await expect(browser).toHaveURL('/visualization/foresight-radar');

    // Verify radar header and filter bar
    await expect(screen.getByRole('heading', 'Foresight Radar')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Filter Analisis:')).toBeVisible();
    await expect(screen.getByText('Periode:')).toBeVisible();
    await expect(screen.getByText('Dimensi:')).toBeVisible();

    // Verify chart container exists
    await expect(screen.getByText(/Horizon 1 \/ 2 \/ 3/)).toBeVisible();
  });

  test('Happy Path: Prioritizing chart renders with matrix visual container', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/prioritizing');
    await expect(browser).toHaveURL('/visualization/prioritizing');

    // Verify prioritizing heading and matrix components
    await expect(screen.getByRole('heading', 'Prioritizing')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Filter Analisis:')).toBeVisible();
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

  test('Unhappy & Boundary Path: dimension filter handles switching and reset gracefully', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/visualization/foresight-radar');

    // Open dimension selector and pick a filter
    const dimCombobox = screen.getByRole('combobox');
    await dimCombobox.tap();
    await browser.locator('.ant-select-item-option:has-text("Economy")').tap();

    // Verify reset filter button appears when active filters differ from default
    const resetBtn = screen.getByRole('button', /Reset/);
    await expect(resetBtn).toBeVisible({ timeout: 20000 });

    // Tap reset to restore standard view
    await resetBtn.tap();
    await expect(screen.getByText('Semua Dimensi')).toBeVisible({ timeout: 20000 });
  });
});
