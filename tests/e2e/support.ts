import { expect, type Page } from '@playwright/test';

export type Role = 'admin' | 'officer' | 'analyst';

export async function login(page: Page, role: Role): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(`${role}@e2e.test`);
    await page.getByLabel('Password').fill(process.env.E2E_PASSWORD ?? '');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}
