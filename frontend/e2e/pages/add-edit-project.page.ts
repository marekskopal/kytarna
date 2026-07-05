import {expect, Page} from '@playwright/test';

export class AddEditProjectPage {
    public constructor(private readonly page: Page) {}

    public async fillName(name: string): Promise<void> {
        await this.page.fill('#project-name', name);
    }

    public async fillDescription(description: string): Promise<void> {
        await this.page.fill('#project-description', description);
    }

    public async submit(): Promise<void> {
        await this.page.getByRole('button', {name: 'Save', exact: true}).click();
        await expect(this.page).toHaveURL(/\/projects$/, {timeout: 10_000});
    }
}
