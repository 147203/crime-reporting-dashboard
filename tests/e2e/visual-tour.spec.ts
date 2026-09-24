import { test } from '@playwright/test';
import { login } from './support';

// Screens in light and dark themes for design review and Chapter 5 figures.
const pages = [
    { name: 'dashboard', path: '/?from=2024-09-01&to=2026-08-31' },
    { name: 'incidents', path: '/incidents' },
    { name: 'incident-create', path: '/incidents/create' },
    { name: 'duplicates', path: '/duplicates' },
    { name: 'imports', path: '/imports' },
    { name: 'quality', path: '/quality' },
    { name: 'reports', path: '/reports' },
    { name: 'users', path: '/admin/users' },
    { name: 'audit', path: '/admin/audit' },
];

test('visual tour', async ({ page }, info) => {
    await page.goto('/login');
    await page.screenshot({ path: `docs/evidence/${info.project.name}-tour-login.png`, fullPage: true });
    await login(page, 'admin');

    for (const theme of ['light', 'dark']) {
        await page.evaluate((t) => localStorage.setItem('theme', t), theme);
        for (const p of pages) {
            await page.goto(p.path);
            await page.screenshot({ path: `docs/evidence/${info.project.name}-tour-${theme}-${p.name}.png`, fullPage: true });
        }
    }
});
