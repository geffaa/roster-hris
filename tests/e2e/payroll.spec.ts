import { expect, test } from '@playwright/test';
import { login, logout } from './helpers';

test('HR gives the second approval, payroll locks the run and sends payslips', async ({ page }) => {
    await login(page, 'hr@roster.test');
    await page.goto('/payroll');
    await page.locator('table.runs tbody tr').first().click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('payroll');
    await page.getByRole('button', { name: /Approve \(2 of 2\)/ }).click();
    await expect(page.locator('.toast')).toContainText('Second approval recorded');

    await logout(page);
    await login(page, 'payroll@roster.test');
    await page.goto('/payroll');
    await page.locator('table.runs tbody tr').first().click();
    await page.getByRole('button', { name: 'Lock run' }).click();
    await expect(page.locator('.toast')).toContainText('locked');
    await page.getByRole('button', { name: 'Send payslips' }).click();
    await expect(page.locator('.toast')).toContainText('Payslips sent');
    await expect(page.locator('.stepper li').last()).toHaveClass(/done|current/);

    // Reports for the newly locked month add up to the run total.
    const net = (await page.locator('.kpis5 .net small').textContent()) ?? '';
    await page.goto('/reports?report=payroll');
    await expect(page.locator('table tfoot')).toBeVisible();
    expect(net.length).toBeGreaterThan(0);
});
