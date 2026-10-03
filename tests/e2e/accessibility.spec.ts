import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { login } from './support';

// NFR-08: automated accessibility check (WCAG 2.1 A and AA rules) of every main page, in both themes. Serious and
// critical violations fail the test; the full list is attached to the report for review.
const pages = [
    '/',
    '/incidents',
    '/incidents/create',
    '/duplicates',
    '/imports',
    '/quality',
    '/reports',
    '/admin/users',
    '/admin/users/create',
    '/admin/reference',
    '/admin/audit',
    '/password',
];

for (const theme of ['light', 'dark']) {
    test(`TC-BB-10 accessibility, ${theme} theme`, async ({ page }, info) => {
        test.setTimeout(180_000);
        await page.goto('/login');
        await page.evaluate((t) => localStorage.setItem('theme', t), theme);

        const loginScan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        const problems: string[] = loginScan.violations
            .filter((v) => v.impact === 'serious' || v.impact === 'critical')
            .map((v) => `/login: ${v.id} (${v.impact}) x${v.nodes.length}: ${v.nodes[0]?.target.join(' ')}`);

        await login(page, 'admin');
        for (const path of pages) {
            await page.goto(path);
            const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
            for (const v of results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')) {
                problems.push(`${path}: ${v.id} (${v.impact}) x${v.nodes.length}: ${v.nodes[0]?.target.join(' ')}`);
            }
        }

        await info.attach(`axe-${theme}`, { body: problems.join('\n') || 'none', contentType: 'text/plain' });
        expect(problems, problems.join('\n')).toEqual([]);
    });
}
