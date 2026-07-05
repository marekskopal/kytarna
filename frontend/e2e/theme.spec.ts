import {expect, test} from '@playwright/test';

import {LayoutPage} from './pages/layout.page';

async function openUserMenu(page: import('@playwright/test').Page): Promise<void> {
    await page.locator('.user-menu .user-menu-trigger').click();
    await expect(page.locator('.user-menu .menu')).toBeVisible();
}

async function selectTheme(page: import('@playwright/test').Page, label: string): Promise<void> {
    await openUserMenu(page);
    await page.locator('.user-menu .menu')
        .getByRole('menuitem', {name: label, exact: true})
        .click();
    await expect(page.locator('.user-menu .menu')).not.toBeVisible();
}

test.describe('Theme toggle', () => {
    test('dark mode flips data-theme, persists across reload, and Light removes it', async ({page}) => {
        const layout = new LayoutPage(page);
        await page.goto('projects');
        await layout.expectVisible();

        // Force a known starting state.
        await selectTheme(page, 'Light');
        await expect.poll(async () => page.evaluate(() => document.documentElement.getAttribute('data-theme')))
            .toBeNull();

        await selectTheme(page, 'Dark');
        await expect.poll(async () => page.evaluate(() => document.documentElement.getAttribute('data-theme')))
            .toBe('dark');

        // The topbar surface should resolve to the dark palette (#161619).
        const topbarBg = await page.locator('.topbar').evaluate((el) => getComputedStyle(el).backgroundColor);
        expect(topbarBg).toBe('rgb(22, 22, 25)');

        // Reload — the inline pre-bootstrap script should keep dark applied (no light flash).
        await page.reload();
        await expect.poll(async () => page.evaluate(() => document.documentElement.getAttribute('data-theme')))
            .toBe('dark');

        await selectTheme(page, 'Light');
        await expect.poll(async () => page.evaluate(() => document.documentElement.getAttribute('data-theme')))
            .toBeNull();
    });
});
