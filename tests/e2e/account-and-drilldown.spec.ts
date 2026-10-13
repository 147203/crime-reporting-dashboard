import { expect, test, type Page } from '@playwright/test';
import { login } from './support';

// TC-BB-11 to TC-BB-14: account safeguards, password visibility, dashboard drill-down and Excel export, driven
// through a real browser. Screenshots go to docs/evidence/ as run evidence for Chapter 5.

async function evidence(page: Page, name: string, mask: string[] = []): Promise<void> {
    await page.screenshot({
        path: `docs/evidence/${test.info().project.name}-${name}.png`,
        fullPage: true,
        mask: mask.map((selector) => page.locator(selector)),
    });
}

test('TC-BB-11 a generated temporary password is shown once, works, and must be changed', async ({ page }, info) => {
    const email = `generated-${info.project.name}@e2e.test`;
    await login(page, 'admin');
    await page.goto('/admin/users/create');
    await page.getByLabel('Full name').fill('Generated Password User');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Role').selectOption('analyst');
    await expect(page.getByLabel(/Generate a secure temporary password/)).toBeChecked();
    await page.getByRole('button', { name: 'Create user' }).click();

    const shown = page.locator('#generated-password');
    await expect(shown).toBeVisible();
    await expect(page.getByText('will not be shown again')).toBeVisible();
    const password = (await shown.textContent())?.trim() ?? '';
    expect(password).toHaveLength(16);
    await evidence(page, 'BB-11-generated-password', ['#generated-password']);

    await page.reload();
    await expect(page.locator('#generated-password')).toHaveCount(0);

    await page.getByRole('button', { name: /Account menu for/ }).click();
    await page.getByRole('button', { name: 'Log out' }).click();
    await page.getByLabel('Email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByRole('heading', { name: 'Change password', level: 1 })).toBeVisible();
    await expect(page.getByText('Choose your own password before you continue.')).toBeVisible();
});

test('TC-BB-12 the eye button shows and hides the password', async ({ page }) => {
    await page.goto('/login');
    const field = page.locator('#password');
    const eye = page.getByRole('button', { name: 'Show password' });
    await field.fill('not-a-real-password');
    await expect(field).toHaveAttribute('type', 'password');

    await eye.click();
    await expect(field).toHaveAttribute('type', 'text');
    await expect(page.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true');
    await evidence(page, 'BB-12-password-shown', ['#password']);

    await page.getByRole('button', { name: 'Hide password' }).press('Enter');
    await expect(field).toHaveAttribute('type', 'password');
});

test('TC-BB-13 a dashboard value opens the records behind it', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/?from=2024-09-01&to=2026-08-31');
    const link = page.locator('a[href*="countable=1"]').first();
    const href = await link.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href ?? '/');
    await expect(page.getByRole('heading', { name: 'Incidents', level: 1 })).toBeVisible();
    await expect(page.getByText('From the dashboard')).toBeVisible();
    await evidence(page, 'BB-13-drill-down-list');
});

test('TC-BB-14 export the matching records as an Excel workbook', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/reports');
    const xlsx = page.waitForEvent('download');
    await page.getByRole('link', { name: /Excel workbook of the/ }).click();
    expect((await xlsx).suggestedFilename()).toMatch(/^incidents-\d{8}-to-\d{8}\.xlsx$/);
});

test('TC-BB-15 the Actions menu views and deletes an unused account, always after confirmation', async ({ page }, info) => {
    const email = `delete-me-${info.project.name}@e2e.test`;
    await login(page, 'admin');
    await page.goto('/admin/users/create');
    await page.getByLabel('Full name').fill('Delete Me');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Role').selectOption('analyst');
    await page.getByRole('button', { name: 'Create user' }).click();

    // View from the Actions menu: the detail page itself has no Delete button.
    await page.getByRole('button', { name: 'Actions for Delete Me' }).click();
    await page.getByRole('link', { name: 'View', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Delete Me', level: 1 })).toBeVisible();
    await expect(page.getByText('This account has never been used')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Delete' })).toHaveCount(0);

    // Delete from the Actions menu always asks first; cancelling keeps the account.
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Actions for Delete Me' }).click();
    await evidence(page, 'BB-15-user-actions-menu');
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByText('Delete the account of Delete Me? This cannot be undone.')).toBeVisible();
    await expect(page.locator('#confirmModal')).toHaveClass(/show/);
    await page.waitForTimeout(400); // let the dialog's fade-in finish before the evidence screenshot
    await evidence(page, 'BB-15-user-delete-confirm');
    await page.locator('#confirmModal').getByRole('button', { name: 'Cancel' }).click();
    await expect(page.getByRole('link', { name: 'Delete Me', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Actions for Delete Me' }).click();
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.locator('[data-confirm-accept]').click();
    await expect(page).toHaveURL(/\/admin\/users$/);
    await expect(page.getByText('User Delete Me was deleted.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Delete Me', exact: true })).toHaveCount(0);

    // The signed-in administrator's own account: Delete is shown disabled with the reason.
    await page.getByRole('button', { name: 'Actions for E2E Administrator' }).click();
    await expect(page.getByText('Delete (your own account)')).toBeVisible();
});
