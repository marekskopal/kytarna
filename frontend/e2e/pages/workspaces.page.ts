import {expect, Locator, Page} from '@playwright/test';

export class WorkspacesPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.goto('workspaces');
        await expect(this.page.locator('.ws-list')).toBeVisible();
    }

    public workspaceItem(name: string): Locator {
        return this.page.locator('.ws-item').filter({hasText: name});
    }

    public async select(name: string): Promise<void> {
        await this.workspaceItem(name).click();
        await expect(this.page.locator('.ws-detail h2')).toHaveText(name);
    }

    public async rename(newName: string): Promise<void> {
        this.page.once('dialog', async (dialog) => {
            await dialog.accept(newName);
        });
        await this.page.getByRole('tab', {name: 'General'}).click();
        await this.page.locator('.general-row').getByRole('button', {name: 'Rename'}).click();
        await expect(this.page.locator('.ws-detail h2')).toHaveText(newName, {timeout: 10_000});
    }

    public async expectCurrent(name: string): Promise<void> {
        await expect(this.workspaceItem(name).locator('.ws-badge')).toHaveText('Current');
    }
}
