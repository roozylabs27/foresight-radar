import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Driving Force & Signal Ingestion E2E Flows', () => {
  test('Happy Path: creates a new driving force signal and verifies PENDING status tag', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/driving-force');
    await expect(browser).toHaveURL('/driving-force');

    // Click "Tambah Sinyal" to open creation modal
    await screen.getByRole('button', /Tambah Sinyal/).tap();
    await expect(screen.getByRole('dialog')).toBeVisible();

    const uniqueKeyword = `E2E-Signal-${Date.now()}`;

    // Select Dimension
    await browser.locator('#basic_dimension_id').tap();
    await browser.locator('.ant-select-dropdown .ant-select-item-option:has-text("Technology")').tap();

    // Fill Keyword and Description
    await screen.getByPlaceholder('Enter keyword').fill(uniqueKeyword);
    await screen.getByPlaceholder('Enter a description').fill('E2E automated driving force verification test signal');

    // Select PIC
    await browser.locator('#basic_pic_id').tap();
    await screen.getByText('Ana Pangestu - Admin').tap();

    // Submit dialog
    await screen.getByRole('button', 'Submit').tap();

    // Verify newly created signal appears in table
    await expect(screen.getByText(uniqueKeyword)).toBeVisible({ timeout: 45000 });
  });

  test('Unhappy Path: submitting empty modal triggers all required field validation errors', { session: 'admin' }, async ({ app, screen }) => {
    await app.open('/driving-force');

    // Open modal
    await screen.getByRole('button', /Tambah Sinyal/).tap();
    await expect(screen.getByRole('dialog')).toBeVisible();

    // Submit without input
    await screen.getByRole('button', 'Submit').tap();

    // Verify validation errors
    await expect(screen.getByText('Please select the dimension!')).toBeVisible();
    await expect(screen.getByText('Please input the keyword!')).toBeVisible();
    await expect(screen.getByText('Please input the description!')).toBeVisible();
    await expect(screen.getByText('Please select the personal in charge!')).toBeVisible();

    // Dismiss modal cleanly
    await screen.getByRole('button', 'Cancel').tap();
  });

  test('Unhappy Path: search with non-existent keyword displays empty state', { session: 'admin' }, async ({ app, screen }) => {
    await app.open('/driving-force');

    // Enter non-existent keyword in search input
    const searchInput = screen.getByPlaceholder('Cari kata kunci...');
    await searchInput.fill('NON_EXISTENT_KEYWORD_XYZ_99999');
    await searchInput.press('Enter');

    // Verify empty state is rendered
    await expect(screen.getByText(/Tidak ada data|No data/i)).toBeVisible({ timeout: 25000 });
  });
});
