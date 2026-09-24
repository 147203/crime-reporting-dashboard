import { test } from '@playwright/test';
import { login } from './support';

// Screenshots of the main screens in light and dark themes, for design review and Chapter 5 evidence.
const pages = [
    { name: 'dashboard', path: '/' },
    { name: 'incidents', path: '/incidents' },
    { name: 'incident-create', path: '/incidents/create' },
    { name: 'users', path: '/admin/users' },
    { name: 'reference', path: '/admin/reference?tab=wards' },
    { name: 'audit', path: '/admin/audit' },
];

test('visual tour', async ({ page }, info) => {
    await page.goto('/login');
    await page.screenshot({ path: `test-results/tour/${info.project.name}-login.png`, fullPage: true });
    await login(page, 'admin');

    for (const theme of ['light', 'dark']) {
        await page.evaluate((t) => localStorage.setItem('theme', t), theme);
        for (const p of pages) {
            await page.goto(p.path);
            await page.screenshot({ path: `test-results/tour/${info.project.name}-${theme}-${p.name}.png`, fullPage: true });
        }
    }
});
