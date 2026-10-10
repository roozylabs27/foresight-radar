import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Error Boundary & Exception Handling E2E Flows', () => {
  test('Unhappy Path: navigating to a non-existent URL renders custom 404 error page', async ({ app, screen }) => {
    await app.open('/system-route-not-found-404-test');

    // Verify custom 404 page components
    await expect(screen.getByText('Halaman Tidak Ditemukan')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText(/Kode Status HTTP:\s*404/)).toBeVisible();

    // Verify safety navigation links are present
    await expect(screen.getByRole('link', 'Halaman Sebelumnya')).toBeVisible();
    await expect(screen.getByRole('link', 'Halaman Utama')).toBeVisible();
  });

  test('Unhappy Path: unauthenticated user accessing protected management route is guarded', async ({ app, browser, screen }) => {
    // Attempting direct visit to user management without session
    await app.open('/user-management/user');

    // Should be redirected to /login
    await expect(browser).toHaveURL('/login');
    await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();
  });
});
