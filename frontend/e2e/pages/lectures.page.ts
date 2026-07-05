import {expect, Page} from '@playwright/test';

export class LecturesPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.locator('.nav').getByRole('link', {name: 'Lectures', exact: true}).click();
        await expect(this.page).toHaveURL(/\/lectures/);
    }

    public async openGridRow(name: string): Promise<void> {
        await this.page.locator('.lecture-row', {hasText: name}).first().click();
    }
}
