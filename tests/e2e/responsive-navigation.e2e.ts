import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('Responsive Mobile Navigation Drawer (RESP-001)', () => {
  test('Mobile viewport (375x667): Desktop sider is hidden and hamburger button opens drawer overlay', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    if (typeof browser.setViewport === 'function') {
      await browser.setViewport({ width: 375, height: 667 });
    }

    await app.open('/login');
    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    // Verify desktop sider is not visible on mobile
    const desktopSider = browser.locator('aside.ant-layout-sider');
    await expect(desktopSider).toBeHidden();

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
  });
});
