import {expect, Locator, Page} from '@playwright/test';

export class BoardPage {
    public constructor(private readonly page: Page) {}

    public async expectVisible(): Promise<void> {
        await expect(this.page.locator('.kanban')).toBeVisible();
    }

    public column(name: string): Locator {
        return this.page.locator('.column').filter({has: this.page.locator('.column-title h3', {hasText: name})});
    }

    public taskCard(name: string): Locator {
        return this.page.locator('.kanban [cdkdrag]').filter({hasText: name});
    }

    public async openNewTask(): Promise<void> {
        await this.page.getByRole('button', {name: 'New task', exact: true}).click();
        await expect(this.page.locator('.drawer')).toBeVisible();
    }

    public async openTask(name: string): Promise<void> {
        await this.taskCard(name).click();
        await expect(this.page.locator('.drawer')).toBeVisible();
    }

    public async expectTaskInColumn(taskName: string, columnName: string): Promise<void> {
        await expect(this.column(columnName).locator('[cdkdrag]', {hasText: taskName})).toBeVisible();
    }

    public async expectTaskAbsent(taskName: string): Promise<void> {
        await expect(this.taskCard(taskName)).toHaveCount(0);
    }
}
