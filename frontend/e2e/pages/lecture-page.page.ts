import {expect, Page} from '@playwright/test';

// Lectures are edited on a full page (LecturePageComponent), not a drawer.
export class LecturePage {
    public constructor(private readonly page: Page) {}

    public async expectOpen(): Promise<void> {
        await expect(this.page.locator('.lecture-page')).toBeVisible();
    }

    public async fillName(name: string): Promise<void> {
        await this.page.fill('#lecture-name', name);
    }

    public async fillDescription(description: string): Promise<void> {
        await this.page.fill('#lecture-description', description);
    }

    // Status is a radiogroup of buttons, not a <select>. Labels: To Learn / Learning / Mastered.
    public async selectStatus(statusLabel: string): Promise<void> {
        await this.page.locator('.status-picker').getByRole('radio', {name: statusLabel, exact: true}).click();
    }

    // Persist the form. Waits for the create/update mutation to complete. On create the page
    // then navigates to the freshly-created lecture; on update it stays put.
    public async save(): Promise<void> {
        const saved = this.page.waitForResponse(
            (r) => r.url().includes('/api/') && ['POST', 'PUT'].includes(r.request().method()) && r.ok(),
        );
        await this.page.locator('.page-footer .btn-primary').click();
        await saved;
    }

    public async delete(): Promise<void> {
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.page.locator('.page-footer .btn-danger-ghost').click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/board/);
    }

    public async backToBoard(): Promise<void> {
        await this.page.locator('.hero-back').click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/board/);
    }
}
