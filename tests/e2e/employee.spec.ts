import { expect, test } from '@playwright/test';
import { login, logout } from './helpers';

test('an employee requests leave, the manager approves it, and the employee sees it approved', async ({ page }) => {
    await login(page, 'employee@roster.test');
    await page.goto('/me/time-off');
    await page.getByRole('button', { name: 'Request' }).click();

    // Pick two weekdays early next month with the custom date picker.
    await page.getByRole('button', { name: 'First day' }).click();
    await page.getByRole('button', { name: 'Next month' }).click();
    const days = page.locator('.cal-g button:not([disabled])');
    await days.nth(8).click();
    await page.getByRole('button', { name: 'Last day' }).click();
    await page.locator('.cal-g button:not([disabled])').nth(1).click();
    await expect(page.locator('.quote')).toContainText('working');
    await page.fill('#reason', 'Moving house');
    await page.getByRole('button', { name: 'Send request' }).click();
    await expect(page.locator('.toast')).toContainText('sent for approval');
    await expect(page.locator('.lr', { hasText: 'Pending' })).toHaveCount(1);

    await logout(page);
    await login(page, 'manager@roster.test');
    await page.goto('/inbox');
    const card = page.locator('article.req', { hasText: 'Moving house' });
    await card.getByRole('button', { name: 'Approve' }).click();
    await expect(page.locator('.toast')).toContainText('Approved');

    await logout(page);
    await login(page, 'employee@roster.test');
    await page.goto('/me/time-off');
    await expect(page.locator('.lr', { hasText: 'Pending' })).toHaveCount(0);
    await expect(page.locator('.lr', { hasText: 'Approved' })).toHaveCount(2);
    await page.locator('.bell .ibtn').click();
    await expect(page.locator('.bellpop')).toContainText('Leave request approved');
});

test('an employee reads a payslip, downloads the PDF and switches to Bahasa Indonesia', async ({ page }) => {
    await login(page, 'employee@roster.test');
    await page.goto('/me/pay');
    await page.locator('a.payhero').click();
    await expect(page.locator('.payhero b')).toContainText('Rp');
    const href = await page.getByRole('link', { name: 'Download PDF' }).getAttribute('href');
    const pdf = await page.request.get(href!);
    expect(pdf.headers()['content-type']).toBe('application/pdf');

    await page.goto('/me/profile');
    await page.getByRole('button', { name: 'Bahasa Indonesia' }).click();
    await expect(page.locator('nav.tab')).toContainText('Hari ini');
    await page.goto('/me/pay');
    await expect(page.locator('.hi h1')).toHaveText('Gaji');
    await page.goto('/me/profile');
    await page.getByRole('button', { name: 'English' }).click();
});
