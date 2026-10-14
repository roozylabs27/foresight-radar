import { test } from '@e2e-dev/web';
import { expect, credentials } from 'e2e';

test.describe('Executive Reasoning & Foundation Metrics E2E Flows', () => {
  test('Happy Path: Dashboard renders Executive Overview by default with gauges and EDA charts', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/dashboard');
    await expect(browser).toHaveURL('/dashboard');

    // 1. Verify Executive Overview Tab and Heading
    await expect(screen.getByRole('heading', 'Dashboard')).toBeVisible({ timeout: 45000 });
    await expect(screen.getByRole('tab', 'Executive Overview')).toBeVisible({ timeout: 45000 });

    // 2. Verify Posture and Gauge cards (wait for async reasoning-data to resolve)
    await expect(screen.getByText(/Postur Kesiapan Strategis:/)).toBeVisible({ timeout: 90000 });
    await expect(screen.getByText('Growth Index')).toBeVisible({ timeout: 45000 });
    await expect(screen.getByText('Resilience Index')).toBeVisible({ timeout: 45000 });

    // 3. Verify Top Critical Forces section
    await expect(screen.getByText(/Top Driving Forces Kritis \(Perhatian BoD\)/)).toBeVisible({ timeout: 45000 });

    // 4. Verify EDA charts
    await expect(screen.getByText('Distribusi Frekuensi per Dimensi STEEP')).toBeVisible({ timeout: 45000 });
    await expect(screen.getByText('Tren Pertumbuhan Driving Forces Bulanan')).toBeVisible({ timeout: 45000 });
  });

  test('Navigation & Tab Switch: User can switch between Executive Overview, Prioritizing, Radar, and Registered List', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/dashboard');

    // Switch to Prioritizing tab
    await screen.getByRole('tab', 'Prioritizing').tap();
    await expect(screen.getByText('Filter Analisis:')).toBeVisible({ timeout: 45000 });

    // Switch to Foresight Radar tab
    await screen.getByRole('tab', 'Foresight Radar').tap();
    await expect(screen.getByText(/Short Term \(0 - 1 yr\)/)).toBeVisible({ timeout: 90000 });

    // Switch back to Executive Overview tab
    await screen.getByRole('tab', 'Executive Overview').tap();
    await expect(screen.getByRole('tab', 'Executive Overview')).toBeVisible({ timeout: 45000 });
  });
});
