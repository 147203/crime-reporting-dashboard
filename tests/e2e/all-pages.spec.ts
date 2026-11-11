import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { login, type Role } from './support';

// TC-BB-16 to TC-BB-20: every page for every role, including detail and edit pages, plus the edit flows and the demo
// sign-in. Each page must load, show its heading, log no browser errors and pass the WCAG 2.1 AA scan; a full-page
// screenshot of each goes to docs/evidence/pages/ (local only) for the report.
async function shot(page: Page, name: string): Promise<void> {
    await page.screenshot({ path: `docs/evidence/pages/${test.info().project.name}-${name}.png`, fullPage: true });
}

async function checkPage(page: Page, path: string, name: string): Promise<void> {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    const response = await page.goto(path);
    expect(response?.status(), `${path} status`).toBeLessThan(400);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const serious = scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes[0]?.target.join(' ')}`);
    expect(serious, `${path} accessibility`).toEqual([]);
    expect(errors, `${path} browser errors`).toEqual([]);
    await shot(page, name);
    page.removeAllListeners('pageerror');
    page.removeAllListeners('console');
}

async function firstHref(page: Page, listPath: string, selector: string): Promise<string> {
    await page.goto(listPath);
    const href = await page.locator(selector).first().getAttribute('href');
    expect(href, `link on ${listPath}`).toBeTruthy();
    return new URL(href ?? '/', 'http://x').pathname;
}

async function reportIncident(page: Page, description: string): Promise<string> {
    await page.goto('/incidents/create');
    await page.getByLabel('Category').selectOption({ label: 'Assault' });
    await page.getByLabel('Sub-county').selectOption({ label: 'Kibra' });
    await page.getByLabel('Ward').selectOption({ label: 'Makina' });
    if (await page.locator('select#station_id').count()) {
        await page.locator('select#station_id').selectOption({ label: 'Kibra Sample Station' });
    }
    await page.getByLabel('Street or landmark').fill('Near the footbridge 12');
    await page.getByLabel('Description').fill(description);
    await page.getByLabel('Victim type').selectOption('individual');
    await page.getByLabel('Number of victims').fill('1');
    await page.getByLabel('Injury', { exact: true }).selectOption('minor');
    await page.getByLabel('Sex', { exact: true }).selectOption('female');
    await page.getByLabel('Age in years').fill('29');
    await expect(page.getByLabel('Age band', { exact: true })).toHaveValue('25_34');
    await page.getByLabel('Relationship to the suspect').selectOption('family_or_partner');
    await page.getByRole('button', { name: 'Save incident' }).click();
    await expect(page.getByText(/was recorded/)).toBeVisible();
    return new URL(page.url()).pathname;
}

const pagesFor: Record<Role, Array<[string, string]>> = {
    admin: [
        ['/', 'dashboard'], ['/incidents', 'incidents'], ['/incidents/create', 'incident-create'], ['/duplicates', 'duplicates'],
        ['/imports', 'imports'], ['/quality', 'quality'], ['/reports', 'reports'], ['/admin/users', 'users'],
        ['/admin/users/create', 'user-create'], ['/admin/reference?tab=categories', 'reference-categories'],
        ['/admin/reference?tab=sub-counties', 'reference-sub-counties'], ['/admin/reference?tab=wards', 'reference-wards'],
        ['/admin/reference?tab=stations', 'reference-stations'], ['/admin/audit', 'audit'], ['/password', 'password'],
    ],
    officer: [['/', 'dashboard'], ['/incidents', 'incidents'], ['/incidents/create', 'incident-create'], ['/reports', 'reports'], ['/password', 'password']],
    analyst: [['/', 'dashboard'], ['/incidents', 'incidents'], ['/duplicates', 'duplicates'], ['/quality', 'quality'], ['/reports', 'reports'], ['/password', 'password']],
};

for (const role of ['admin', 'officer', 'analyst'] as Role[]) {
    test(`TC-BB-16 every ${role} page loads, is accessible and is captured`, async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, role);
        for (const [path, name] of pagesFor[role]) {
            await checkPage(page, path, `${role}-${name}`);
        }
        const incident = await firstHref(page, '/incidents', 'table a[href*="/incidents/"]');
        await checkPage(page, incident, `${role}-incident-show`);
        if (role === 'admin') {
            await checkPage(page, `${incident}/edit`, `${role}-incident-edit`);
            const user = await firstHref(page, '/admin/users', 'table td a[href*="/admin/users/"]');
            await checkPage(page, user, `${role}-user-show`);
            await checkPage(page, `${user}/edit`, `${role}-user-edit`);
        }
    });
}

test('TC-BB-17 report an incident with a victim profile, then edit it and find it by victim', async ({ page }) => {
    await login(page, 'admin');
    const path = await reportIncident(page, 'Victim pushed and slapped during a dispute at home.');
    await expect(page.getByText('29 years (25 to 34)')).toBeVisible();
    await expect(page.getByText('Family member or partner')).toBeVisible();
    await shot(page, 'flow-incident-with-victim');

    await page.goto(`${path}/edit`);
    await page.getByLabel('Victim type').selectOption('business');
    await expect(page.getByLabel('Sex', { exact: true })).toBeDisabled();
    await expect(page.getByText('These details apply only when the victim is an individual person.')).toBeVisible();
    await page.getByLabel('Injury', { exact: true }).selectOption('none');
    await shot(page, 'flow-incident-edit-business');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Business or organisation')).toBeVisible();
    await expect(page.getByText('Relationship to the suspect')).toHaveCount(0);

    await page.goto('/incidents?victim_type=business&victim_injury=none&q=footbridge%2012');
    await expect(page.locator('table a[href$="' + path + '"]')).toHaveCount(1);
});

test('TC-BB-18 an officer edits their own report', async ({ page }) => {
    await login(page, 'officer');
    const path = await reportIncident(page, 'Victim attacked on the way home from work.');
    await page.goto(`${path}/edit`);
    await page.getByLabel('Age in years').fill('');
    await page.getByLabel('Age band', { exact: true }).selectOption('65_plus');
    await page.getByLabel('Sex', { exact: true }).selectOption('male');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('65 and over')).toBeVisible();
    await expect(page.getByText('Male', { exact: true })).toBeVisible();
    await shot(page, 'flow-officer-edited-incident');
});

test('TC-BB-19 an administrator edits a user and a reference item', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Actions for E2E Analyst' }).click();
    await page.getByRole('link', { name: 'Edit', exact: true }).click();
    await page.getByLabel('Full name').fill('E2E Analyst Renamed');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('User E2E Analyst Renamed was updated.')).toBeVisible();

    // Put the name back, so other tests (and the other screen size) see the same data.
    await page.getByRole('button', { name: 'Actions for E2E Analyst Renamed' }).click();
    await page.getByRole('link', { name: 'Edit', exact: true }).click();
    await page.getByLabel('Full name').fill('E2E Analyst');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('User E2E Analyst was updated.')).toBeVisible();

    await page.goto('/admin/reference?tab=categories');
    const rename = async (from: string, to: string) => {
        const row = page.locator('tr', { has: page.locator(`input[name="name"][value="${from}"]`) });
        await row.locator('input[name="name"]').fill(to);
        await row.getByRole('button', { name: 'Save' }).click();
        await expect(page.locator(`input[name="name"][value="${to}"]`)).toHaveCount(1);
    };
    await rename('Other', 'Other offences');
    await shot(page, 'flow-reference-renamed');
    await rename('Other offences', 'Other');
});

test('TC-BB-20 demo sign-in: pick a role, switch, pick another', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByText('Demo only: sign in as a role')).toBeVisible();
    await expect(page.getByText(/In real use every person signs in with their own email and password/)).toBeVisible();
    await shot(page, 'demo-login-panel');

    for (const [label, badge] of [['Administrator', 'Demo: Administrator'], ['Reporting Officer', 'Demo: Reporting Officer'], ['Analyst', 'Demo: Analyst']]) {
        await page.getByRole('button', { name: new RegExp(`Sign in as ${label}`) }).click();
        await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
        await expect(page.getByText(badge)).toBeVisible();
        await shot(page, `demo-signed-in-${label.toLowerCase().replace(' ', '-')}`);
        await page.getByRole('button', { name: /Account menu for/ }).click();
        await page.getByRole('button', { name: 'Switch demo user' }).click();
        await expect(page).toHaveURL(/\/login$/);
    }
});
