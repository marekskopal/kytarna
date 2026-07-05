import {expect, Page} from '@playwright/test';

export class TaskDrawerPage {
    public constructor(private readonly page: Page) {}

    public async expectOpen(): Promise<void> {
        await expect(this.page.locator('.drawer')).toBeVisible();
    }

    public async expectClosed(): Promise<void> {
        await expect(this.page.locator('.drawer')).toHaveCount(0);
    }

    public async fillName(name: string): Promise<void> {
        await this.page.fill('#task-name', name);
    }

    public async fillDescription(description: string): Promise<void> {
        await this.page.fill('#task-description', description);
    }

    public async selectStatus(statusName: string): Promise<void> {
        await this.page.locator('.drawer-status-select').selectOption({label: statusName});
    }

    public async save(): Promise<void> {
        await this.page.locator('.drawer-footer .btn-primary').click();
        await this.expectClosed();
    }

    public async delete(): Promise<void> {
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.page.locator('.drawer-footer .btn-danger-ghost').click();
        await this.expectClosed();
    }

    public async cancel(): Promise<void> {
        // The footer has several ghost buttons (Archive); target Cancel by name.
        await this.page.locator('.drawer-footer').getByRole('button', {name: 'Cancel', exact: true}).click();
        await this.expectClosed();
    }
}
