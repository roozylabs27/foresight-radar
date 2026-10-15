import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('WCAG 2.1 AA Color Contrast Remediation (A11Y-002)', () => {
  test('Action buttons and layout secondary subtitles adhere to WCAG AA compliant contrast colors', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    await app.open('/login');
    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    // Sidebar subtitle uses the subtle-foreground token #5f6d84 (rgb(95, 109, 132)), 5.3:1 on white
    const color = await browser.evaluate(() => {
      const spans = Array.from(document.querySelectorAll('span'));
      const el = spans.find((s) => s.textContent?.includes('Strategic Intel'));
      return el ? window.getComputedStyle(el).color : null;
    });
    expect(color).not.toBeNull();
    expect(color).toBe('rgb(95, 109, 132)');

    // Status colours come from design tokens now (the old .custom-green-button and
    // .custom-tag rules went with the antd pages). Both pass 4.5:1 on white and on
    // their -soft backgrounds: success #1f7a3a, warning #a65300.
    const tokens = await browser.evaluate(() => {
      const probe = document.createElement('span');
      document.body.appendChild(probe);
      const read = (name: string) => {
        probe.style.color = `var(${name})`;
        return window.getComputedStyle(probe).color;
      };
      const result = { success: read('--success'), warning: read('--warning') };
      document.body.removeChild(probe);
      return result;
    });
    expect(tokens.success).toBe('rgb(31, 122, 58)');
    expect(tokens.warning).toBe('rgb(166, 83, 0)');
  });
});
