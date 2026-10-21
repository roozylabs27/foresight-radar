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
  test('Dark theme text tokens reach 4.5:1 on background and card', async ({ app, browser }) => {
    await browser.addInitScript(() => localStorage.setItem('foresight-theme', 'dark'));
    await app.open('/login');

    const ratios = await browser.evaluate(() => {
      const probe = document.createElement('span');
      document.body.appendChild(probe);
      const rgb = (name: string) => {
        probe.style.color = `var(${name})`;
        return (getComputedStyle(probe).color.match(/\d+/g) || []).slice(0, 3).map(Number);
      };
      const luminance = ([r, g, b]: number[]) => {
        const lin = (c: number) => {
          const v = c / 255;
          return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
      };
      const ratio = (fg: string, bg: string) => {
        const [a, b] = [luminance(rgb(fg)), luminance(rgb(bg))].sort((x, y) => y - x);
        return (a + 0.05) / (b + 0.05);
      };
      const result: Record<string, number> = {};
      for (const fg of ['--foreground', '--muted-foreground', '--subtle-foreground', '--primary', '--success', '--warning', '--danger']) {
        for (const bg of ['--background', '--card']) result[`${fg} on ${bg}`] = ratio(fg, bg);
      }
      result['--primary-foreground on --primary'] = ratio('--primary-foreground', '--primary');
      probe.remove();
      return { dark: document.documentElement.classList.contains('dark'), result };
    });

    expect(ratios.dark).toBe(true);
    for (const [pair, value] of Object.entries(ratios.result)) {
      expect(value, pair).toBeGreaterThanOrEqual(4.5);
    }
  });
});
