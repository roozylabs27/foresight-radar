import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.setup('authenticate as admin', { sessions: ['admin'] }, async ({ app, screen, session, browser }) => {
  const admin = credentials.user('admin');
  await app.open('/login');
  await screen.getByPlaceholder('analis@perusahaan.com').fill(admin.username);
  await screen.getByPlaceholder('Masukkan kata sandi').fill(admin.password);
  await screen.getByRole('button', 'Masuk ke Platform').tap();
  await expect(browser).toHaveURL(/\/visualization\/foresight-radar|\/dashboard/, { timeout: 45000 });
  await session.save('admin');
});
