import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('Authentication & Access Control E2E Flows', () => {
  test('Happy Path: super admin logs in successfully and reaches the radar dashboard', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    await app.open('/login');

    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    // Verify redirected to visualization radar or dashboard
    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });
    await expect(screen.getByRole('heading', 'Foresight Radar')).toBeVisible();
    await expect(screen.getByText('Super Admin')).toBeVisible();
  });

  test('Unhappy Path: invalid password is rejected and displays error alert', async ({ app, screen, browser }) => {
    await app.open('/login');

    await screen.getByPlaceholder('analis@perusahaan.com').fill('superadmin@foresight.com');
    await screen.getByPlaceholder('Masukkan kata sandi').fill('wrongpassword1234');
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    // Verify client stays on login and displays server error alert
    await expect(browser).toHaveURL('/login');
    await expect(screen.getByRole('alert')).toBeVisible({ timeout: 45000 });
  });

  test('Unhappy Path: empty form submission triggers required field validation errors', async ({ app, screen, browser }) => {
    await app.open('/login');

    // Click submit without filling inputs
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    // Verify Ant Design form validation messages appear
    await expect(screen.getByText('Harap masukkan email kerja Anda!')).toBeVisible();
    await expect(screen.getByText('Harap masukkan kata sandi Anda!')).toBeVisible();
    await expect(browser).toHaveURL('/login');
  });

  test('Unhappy Path: malformed email format triggers client-side validation', async ({ app, screen, browser }) => {
    await app.open('/login');

    await screen.getByPlaceholder('analis@perusahaan.com').fill('not-an-email');
    await screen.getByPlaceholder('Masukkan kata sandi').fill('secretpassword');
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    // Verify format error message
    await expect(screen.getByText('Format email tidak valid!')).toBeVisible();
    await expect(browser).toHaveURL('/login');
  });

  test('Security Boundary: unauthenticated visit to protected route redirects to login', async ({ app, browser, screen }) => {
    // Attempt direct access to driving-force
    await app.open('/driving-force');

    // Expect redirect to login page
    await expect(browser).toHaveURL('/login');
    await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();
  });

  test('Unhappy Path: registration validation catches empty submissions and mismatches', async ({ app, screen, browser }) => {
    await app.open('/register');

    // Verify registration heading
    await expect(screen.getByRole('heading', 'Daftar Akun Baru')).toBeVisible();

    // Submit empty registration form
    await screen.getByRole('button', 'Daftar Akun').tap();
    await expect(screen.getByText('Harap masukkan nama lengkap Anda!')).toBeVisible();
    await expect(screen.getByText('Harap masukkan email kerja Anda!')).toBeVisible();
    await expect(screen.getByText('Harap masukkan kata sandi!')).toBeVisible();

    // Fill mismatched confirmation password
    await screen.getByPlaceholder('Nama lengkap').fill('Test User');
    await screen.getByPlaceholder('analis@perusahaan.com').fill('testuser@example.com');
    await screen.getByPlaceholder('Minimal 8 karakter').fill('password123');
    await screen.getByPlaceholder('Ulangi kata sandi').fill('mismatch321');
    await screen.getByRole('button', 'Daftar Akun').tap();

    await expect(screen.getByText('Konfirmasi kata sandi tidak cocok!')).toBeVisible();
  });

  test('Happy Path: user logout terminates session and returns to login page', async ({ app, screen, browser }) => {
    const admin = credentials.user('admin');
    await app.open('/login');

    await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
    await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
    await screen.getByRole('button', 'Masuk ke Platform').tap();

    await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });

    // Click user dropdown menu to reveal logout
    await screen.getByText('Super Admin').tap();
    await screen.getByText('Keluar (Logout)').tap();

    // Verify redirected back to /login
    await expect(browser).toHaveURL('/login', { timeout: 45000 });
    await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();
  });
});
