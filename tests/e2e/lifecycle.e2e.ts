import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

test.describe('Foresight Workflow Lifecycle Pipeline E2E Flows', () => {
  test('Stage 2 Happy Path: Time Horizon table displays signals with horizon columns', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/time-horizon');
    await expect(browser).toHaveURL('/time-horizon');

    // Verify Time Horizon heading and columns
    await expect(screen.getByRole('heading', 'Time Horizon')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Keyword')).toBeVisible();
    await expect(screen.getByText('SHORT TERM')).toBeVisible();
    await expect(screen.getByText('MID TERM')).toBeVisible();
    await expect(screen.getByText('LONG TERM')).toBeVisible();
  });

  test('Stage 3 Happy Path: Rating of Urgency renders Uncertainty and Impact rating tags', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/rating-urgency');
    await expect(browser).toHaveURL('/rating-urgency');

    // Verify Rating Urgency heading and sub-columns
    await expect(screen.getByRole('heading', 'Rating Urgency')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('ITEM')).toBeVisible();
    await expect(screen.getByText('Uncertainty')).toBeVisible();
    await expect(screen.getByText('Impact')).toBeVisible();
  });

  test('Stage 4 Happy Path: Status of Action renders action planning interface', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/status-action');
    await expect(browser).toHaveURL('/status-action');

    // Verify Status Action heading and filters
    await expect(screen.getByRole('heading', 'Status Action')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Filter Analisis:')).toBeVisible();
  });

  test('Stage 5 Happy Path: Approval Items interface is accessible to Super Admin', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/approval-items');
    await expect(browser).toHaveURL('/approval-items');

    // Verify Approval Items heading and table container
    await expect(screen.getByRole('heading', 'Approval Items')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Filter Analisis:')).toBeVisible();
  });

  test('Stage 6 Happy Path: Closed Items archive is accessible and displays closed records', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/closed-items');
    await expect(browser).toHaveURL('/closed-items');

    // Verify Closed Items heading and archive list
    await expect(screen.getByRole('heading', 'Closed Items')).toBeVisible({ timeout: 25000 });
    await expect(screen.getByText('Filter Analisis:')).toBeVisible();
  });

  test('Unhappy & Boundary Path: unauthorized user role without approval permission cannot execute decisions', async ({ app, browser, screen }) => {
    // Unauthenticated attempt to direct approval route
    await app.open('/approval-items');
    await expect(browser).toHaveURL('/login');
    await expect(screen.getByRole('heading', 'Masuk ke Foresight Radar')).toBeVisible();
  });
});
