import {expect, Page} from '@playwright/test';

export class TasksPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.locator('.nav').getByRole('link', {name: 'Tasks', exact: true}).click();
        await expect(this.page).toHaveURL(/\/tasks/);
    }

    public async openGridRow(name: string): Promise<void> {
        await this.page.locator('.task-row', {hasText: name}).first().click();
    }
}
