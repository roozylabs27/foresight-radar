import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('Responsive Mobile Navigation Drawer (RESP-001)', () => {
  test('Mobile viewport (375x667): Desktop sidebar is hidden and hamburger button opens navigation sheet', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    if (typeof browser.setViewport === 'function') {
      await browser.setViewport({ width: 375, height: 667 });
    }

    await app.open('/login');
    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    // Desktop sidebar is not rendered on mobile; navigation lives in the sheet
    const desktopSidebar = browser.locator('aside[aria-label="Navigasi utama"]');
    await expect(desktopSidebar).toBeHidden();

    // Hamburger button toggles mobile navigation drawer
    const hamburgerBtn = screen.getByRole('button', 'Buka menu navigasi');
    await expect(hamburgerBtn).toBeVisible();
    await hamburgerBtn.tap();

    // Verify drawer dialog appears with navigation items
    const drawer = screen.getByRole('dialog');
    await expect(drawer).toBeVisible();
    await expect(screen.getByText('Strategic Intel')).toBeVisible();

    // Verify clicking a navigation link navigates and auto-closes drawer
    await screen.getByText('Driving Force').tap();
    await expect(browser).toHaveURL(/\/driving-force/, { timeout: 30000 });
    await expect(drawer).toBeHidden();
  });
});
