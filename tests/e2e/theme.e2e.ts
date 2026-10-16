import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

const isDark = () => document.documentElement.classList.contains('dark');

// Pretend the OS prefers dark (no colour-scheme emulation in the runner) to prove it is ignored.
const preferDark = `(() => {
  const original = window.matchMedia.bind(window);
  window.matchMedia = (query) =>
    query.includes('prefers-color-scheme: dark')
      ? { matches: true, media: query, onchange: null, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}, dispatchEvent() { return false; } }
      : original(query);
})();`;

test.describe('Dark theme (THEME-001)', () => {
  test('Guest switch turns dark mode on and the choice survives a reload', async ({ app, screen, browser }) => {
    await app.open('/login');
    expect(await browser.evaluate(isDark)).toBe(false);

    const toggle = screen.getByRole('switch', 'Mode gelap');
    await expect(toggle).not.toBeChecked();
    await toggle.tap();
    await expect.poll(() => browser.evaluate(isDark)).toBe(true);
    expect(await browser.evaluate(() => localStorage.getItem('foresight-theme'))).toBe('dark');

    await browser.reload();
    await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();
    expect(await browser.evaluate(isDark)).toBe(true);
    expect(await browser.evaluate(() => document.documentElement.style.colorScheme)).toBe('dark');

    await expect(screen.getByRole('switch', 'Mode gelap')).toBeChecked();
    await screen.getByRole('switch', 'Mode gelap').tap();
    await expect.poll(() => browser.evaluate(isDark)).toBe(false);
    expect(await browser.evaluate(() => localStorage.getItem('foresight-theme'))).toBe('light');
  });

  test('Default is light, even when the OS prefers dark', async ({ app, browser }) => {
    await browser.addInitScript(preferDark);
    await app.open('/login');
    expect(await browser.evaluate(() => localStorage.getItem('foresight-theme'))).toBeNull();
    expect(await browser.evaluate(isDark)).toBe(false);
  });

  test('User menu theme choice applies across pages', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    await app.open('/login');
    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();
    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    await screen.getByText('Super Admin').tap();
    await screen.getByRole('menuitemcheckbox', 'Mode gelap').tap();
    await expect.poll(() => browser.evaluate(isDark)).toBe(true);
    // The menu stays open so the switch shows its new state.
    await expect(screen.getByRole('menuitemcheckbox', 'Mode gelap')).toBeChecked();
    await browser.keyboard.press('Escape');

    await screen.getByRole('link', 'Driving Force').tap();
    await expect(browser).toHaveURL('/driving-force');
    expect(await browser.evaluate(isDark)).toBe(true);
  });

  test('Blade error page uses the saved dark theme', async ({ app, screen, browser }) => {
    await browser.addInitScript(() => localStorage.setItem('foresight-theme', 'dark'));
    await app.open('/halaman-yang-tidak-ada');
    await expect(screen.getByText('Halaman Tidak Ditemukan')).toBeVisible();
    expect(await browser.evaluate(isDark)).toBe(true);
    // Tokens resolve to the dark card colour (#0f1a2d), not the old inline styles.
    const card = await browser.evaluate(() => {
      const probe = document.createElement('span');
      probe.style.color = 'var(--card)';
      document.body.appendChild(probe);
      const value = getComputedStyle(probe).color;
      probe.remove();
      return value;
    });
    expect(card).toBe('rgb(15, 26, 45)');
  });
});
