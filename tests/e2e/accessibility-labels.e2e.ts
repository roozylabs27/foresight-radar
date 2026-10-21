import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Form Label Accessibility (A11Y-001)', () => {
  const attachDomHelper = (browser: any) => {
    browser.$ = async (selector: string) => {
      const exists = await browser.evaluate(
        (sel: string) => Boolean(document.querySelector(sel)),
        selector
      );
      if (!exists) return null;
      return {
        getAttribute: async (attr: string) => {
          return await browser.evaluate(
            ([sel, a]: [string, string]) => {
              const el = document.querySelector(sel);
              return el ? el.getAttribute(a) : null;
            },
            [selector, attr]
          );
        },
      };
    };
  };

  test('Login form inputs correctly match label htmlFor without browser binding errors', async ({ app, screen, browser }) => {
    attachDomHelper(browser);
    await app.open('/login');
    // The DOM helper reads the page once; wait for React to render the form first.
    await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();

    const emailLabel = await browser.$('label[for="email"]');
    expect(emailLabel).not.toBeNull();
    const emailForAttr = await emailLabel.getAttribute('for');

    const emailInput = await browser.$(`#${emailForAttr}`);
    expect(emailInput).not.toBeNull();
    const inputType = await emailInput.getAttribute('type');
    expect(['text', 'email']).toContain(inputType);

    const passwordLabel = await browser.$('label[for="password"]');
    expect(passwordLabel).not.toBeNull();
    const passwordForAttr = await passwordLabel.getAttribute('for');

    const passwordInput = await browser.$(`#${passwordForAttr}`);
    expect(passwordInput).not.toBeNull();
  });

  test('Register form inputs correctly match label htmlFor without browser binding errors', async ({ app, screen, browser }) => {
    attachDomHelper(browser);
    await app.open('/register');
    await expect(screen.getByRole('heading', 'Daftar Akun Baru')).toBeVisible();

    const fields = ['name', 'email', 'password', 'password_confirmation'];
    for (const field of fields) {
      const label = await browser.$(`label[for$="${field}"]`);
      expect(label).not.toBeNull();
      const forAttr = await label.getAttribute('for');
      const input = await browser.$(`#${forAttr}`);
      expect(input).not.toBeNull();
    }
  });
});
