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

    // Verify secondary subtitle color is #595959 (rgb(89, 89, 89))
    const color = await browser.evaluate(() => {
      const spans = Array.from(document.querySelectorAll('span'));
      const el = spans.find((s) => s.textContent?.includes('Strategic Intel'));
      return el ? window.getComputedStyle(el).color : null;
    });
    expect(color).not.toBeNull();
    expect(color).toBe('rgb(89, 89, 89)');

    // Verify .custom-green-button has compliant dark green background (#2e7d32 = rgb(46, 125, 50))
    const greenBtnStyle = await browser.evaluate(() => {
      const dummy = document.createElement('button');
      dummy.className = 'custom-green-button';
      document.body.appendChild(dummy);
      const bg = window.getComputedStyle(dummy).backgroundColor;
      document.body.removeChild(dummy);
      return bg;
    });
    expect(greenBtnStyle).toBe('rgb(46, 125, 50)');

    // Verify .custom-tag hover background rule (#b25900)
    const tagHoverBg = await browser.evaluate(() => {
      for (const sheet of Array.from(document.styleSheets)) {
        try {
          for (const rule of Array.from(sheet.cssRules || [])) {
            if ((rule as CSSStyleRule).selectorText === '.custom-tag:hover') {
              return (rule as CSSStyleRule).style.background;
            }
          }
        } catch {
          // ignore cross-origin sheet errors
        }
      }
      return null;
    });
    expect(tagHoverBg).not.toBeNull();
    expect(tagHoverBg).toBeDefined();
    expect(tagHoverBg!.includes('#b25900') || tagHoverBg!.includes('rgb(178, 89, 0)')).toBe(true);
  });
});
