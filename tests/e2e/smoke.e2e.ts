import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test('login page loads and displays authentication heading', async ({ app, screen, browser }) => {
  await app.open('/login');
  await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();
  await expect(browser).toHaveURL('/login');
});
