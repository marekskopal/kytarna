import {expect, Page} from '@playwright/test';

export class LayoutPage {
    public constructor(private readonly page: Page) {}

    public async expectVisible(): Promise<void> {
        await expect(this.page.locator('.topbar')).toBeVisible();
    }

    public async openWorkspaceSwitcher(): Promise<void> {
        await this.page.locator('.workspace-chip').click();
        await expect(this.page.locator('.workspace-switcher .menu')).toBeVisible();
    }

    public async currentWorkspaceName(): Promise<string> {
        return (await this.page.locator('.workspace-chip .workspace-name').innerText()).trim();
    }

    public async switchTo(workspaceName: string): Promise<void> {
        await this.openWorkspaceSwitcher();
        await this.page.locator('.workspace-switcher .menu')
            .getByRole('menuitem', {name: workspaceName, exact: true})
            .click();
        await expect(this.page).toHaveURL(/\/projects/);
        await expect(this.page.locator('.workspace-chip .workspace-name')).toHaveText(workspaceName);
    }

    public async createWorkspace(name: string): Promise<void> {
        await this.openWorkspaceSwitcher();
        this.page.once('dialog', async (dialog) => {
            await dialog.accept(name);
        });
        await this.page.locator('.workspace-switcher .menu')
            .getByRole('menuitem', {name: '+ New workspace'})
            .click();
        await expect(this.page.locator('.workspace-chip .workspace-name')).toHaveText(name, {timeout: 10_000});
    }

    public async gotoProjects(): Promise<void> {
        await this.page.locator('.nav').getByRole('link', {name: 'Projects', exact: true}).click();
        await expect(this.page).toHaveURL(/\/projects$/);
    }

    public async gotoWorkspaces(): Promise<void> {
        await this.page.locator('.nav').getByRole('link', {name: 'Workspaces', exact: true}).click();
        await expect(this.page).toHaveURL(/\/workspaces/);
    }
}
