import { expect, test, type Page } from '@playwright/test';
import { login } from './support';

// TC-BB-01 to TC-BB-08: the eight usability tasks of docs/05, driven through a real browser (black-box: inputs and
// visible outputs only). Screenshots go to docs/evidence/ as run evidence for Chapter 5.

async function evidence(page: Page, name: string): Promise<void> {
    await page.screenshot({ path: `docs/evidence/${test.info().project.name}-${name}.png`, fullPage: true });
}

async function openMenuIfCollapsed(page: Page): Promise<void> {
    const menu = page.getByRole('button', { name: 'Open menu' });
    if (await menu.isVisible()) {
        await menu.click();
    }
}

test('TC-BB-01 log in and TC-BB-08 log out', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/login$/);
    await login(page, 'officer');
    await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    await evidence(page, 'BB-01-dashboard-after-login');

    await page.getByRole('button', { name: /Account menu for E2E Reporting Officer/ }).click();
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/incidents');
    await expect(page).toHaveURL(/\/login$/);
});

test('TC-BB-02 report a new incident and TC-BB-03 correct a validation error', async ({ page }) => {
    await login(page, 'officer');
    await openMenuIfCollapsed(page);
    await page.getByRole('link', { name: 'Report incident' }).first().click();
    await expect(page.getByRole('heading', { name: 'Report an incident' })).toBeVisible();

    await page.getByLabel('Category').selectOption({ label: 'Theft' });
    await page.getByLabel('Sub-county').selectOption({ label: 'Kibra' });
    await page.getByLabel('Ward').selectOption({ label: 'Makina' });
    await page.getByLabel('Street or landmark').fill('Near the footbridge by the market');
    await page.getByLabel('Description').fill('Phone snatched. Owner reachable on 0712 345 678.');
    await page.getByRole('button', { name: 'Save incident' }).click();

    await expect(page.getByText(/appears to contain a phone number/)).toBeVisible();
    await evidence(page, 'BB-03-validation-error');

    await page.getByLabel('Description').fill('Phone snatched near the footbridge; suspect fled on foot.');
    await page.getByRole('button', { name: 'Save incident' }).click();
    await expect(page.getByText(/was recorded/)).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(/^NRB-\d{4}-\d{6}$/);
    await evidence(page, 'BB-02-incident-recorded');
});

test('TC-BB-04 find a previous record using filters', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/incidents');
    await page.getByLabel('Category').selectOption({ label: 'Burglary' });
    await page.getByLabel('Status').selectOption({ label: 'Closed' });
    await page.getByRole('button', { name: 'Apply' }).click();

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible();
    await expect(page.getByRole('heading', { name: /incidents?$/ })).toBeVisible();
    for (const status of await rows.locator('td:last-child').allInnerTexts()) {
        expect(status.trim()).toBe('Closed');
    }
    await rows.first().getByRole('link').first().click();
    await expect(page.getByText('Burglary', { exact: false }).first()).toBeVisible();
    await evidence(page, 'BB-04-filtered-record');
});

test('TC-BB-05 review a suspected duplicate', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/duplicates');
    const first = page.getByRole('heading', { name: /Suspected duplicate NRB-/ }).first();
    await expect(first).toBeVisible();
    const title = (await first.innerText()).replace('Suspected duplicate ', '');
    await evidence(page, 'BB-05-duplicate-queue');

    await page.getByRole('button', { name: 'Confirm duplicate' }).first().click();
    await expect(page.getByText(`${title} marked as Confirmed duplicate.`)).toBeVisible();
});

test('TC-BB-06 answer questions from the dashboard', async ({ page }) => {
    await login(page, 'analyst');
    await page.getByRole('link', { name: 'Last 24 months' }).click();

    await expect(page.getByRole('heading', { name: 'Categories (A2)' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Hotspot wards (A8)' })).toBeVisible();
    await expect(page.locator('canvas').first()).toBeVisible();

    // Question 1: which category is highest? Read it from the table alternative (accessible without the chart).
    const categories = page.locator('section.card').filter({ has: page.getByRole('heading', { name: 'Categories (A2)' }) });
    await categories.getByRole('button', { name: 'Table' }).click();
    await expect(categories.locator('tbody tr').first()).toBeVisible();

    // Question 2: which ward is flagged as a hotspot?
    await expect(page.locator('.hotspot-row').first()).toBeVisible();
    await evidence(page, 'BB-06-dashboard');

    await page.locator('[data-theme-toggle]').first().click();
    await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
    await evidence(page, 'BB-06-dashboard-dark');
});

test('TC-BB-07 generate and export a report', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/reports');
    await expect(page.getByText('incidents match these filters')).toBeVisible();
    await evidence(page, 'BB-07-reports');

    const csv = page.waitForEvent('download');
    await page.getByRole('link', { name: /CSV of the/ }).click();
    expect((await csv).suggestedFilename()).toMatch(/^incidents-\d{8}-to-\d{8}\.csv$/);

    const pdf = page.waitForEvent('download');
    await page.getByRole('link', { name: /PDF summary/ }).click();
    expect((await pdf).suggestedFilename()).toMatch(/\.pdf$/);
});

test('TC-BB-09 an officer cannot open administration pages', async ({ page }) => {
    await login(page, 'officer');
    const response = await page.goto('/admin/users');
    expect(response?.status()).toBe(403);
});
