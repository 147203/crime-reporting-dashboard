import { expect, type Page } from '@playwright/test';

export type Role = 'admin' | 'officer' | 'analyst';

export async function login(page: Page, role: Role): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(`${role}@e2e.test`);
    await page.locator('#password').fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Log in' }).click();
    // The sign-in page has a heading too, so wait until the browser has actually left it.
    await expect(page).not.toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}
