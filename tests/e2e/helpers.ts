import { type Page } from '@playwright/test';
import { execSync } from 'node:child_process';

/** Current TOTP for a demo user, read from the e2e database (only possible because this is a local test database). */
function otp(email: string): string {
    const php = `echo app(PragmaRX\\\\Google2FA\\\\Google2FA::class)->getCurrentOtp(App\\\\Models\\\\User::where('email','${email}')->first()->two_factor_secret);`;
    return execSync(`php artisan tinker --execute="${php}"`, { env: { ...process.env, DB_DATABASE: 'roster_e2e' } }).toString().trim();
}

export async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', 'password');
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    if (/two-factor\/(setup|challenge)/.test(page.url())) {
        await page.fill('#code', otp(email));
        await page.click('button[type=submit]');
        await page.waitForURL((u) => !/two-factor\/(setup|challenge)/.test(u.pathname));
    }
}

export async function logout(page: Page) {
    await page.context().clearCookies();
}
