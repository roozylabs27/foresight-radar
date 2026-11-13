import { test } from '@e2e-dev/web';
import { expect } from 'e2e';

// PAD-2 (2026-11-13): /scenarios is a read-only archive of stand-alone scenarios. New scenarios are
// built inside an exercise (Scenario Planning, steps 4–6); that flow is covered by
// tests/Feature/Journeys/ForesightExerciseJourneyTest.php over HTTP.
test.describe('Scenario archive', () => {
  test('The old Scenarios page is read-only and points to Scenario Planning', { session: 'admin' }, async ({ app, screen, browser }) => {
    await app.open('/scenarios');
    await expect(browser).toHaveURL('/scenarios');
    await expect(screen.getByRole('heading', 'Scenarios')).toBeVisible({ timeout: 45000 });

    await expect(screen.getByText(/^Arsip: skenario lepas di halaman ini hanya bisa dilihat/)).toBeVisible();
    await expect(screen.getByRole('link', 'Scenario Planning')).toBeVisible();
    await expect(screen.getByRole('button', 'Skenario baru')).not.toBeVisible();
    await expect(screen.getByRole('button', 'Buat narasi')).not.toBeVisible();
  });
});
