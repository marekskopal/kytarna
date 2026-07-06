import {expect, Locator, Page} from '@playwright/test';

export class BoardPage {
    public constructor(private readonly page: Page) {}

    public async expectVisible(): Promise<void> {
        await expect(this.page.locator('.kanban')).toBeVisible();
    }

    public column(name: string): Locator {
        // Match the column header label exactly so "Learning" doesn't collide with other columns.
        return this.page.locator('.column').filter({
            has: this.page.locator('h3.column-label', {hasText: new RegExp(`^${name}$`)}),
        });
    }

    public lectureCard(name: string): Locator {
        return this.page.locator('.kanban [cdkdrag]').filter({hasText: name});
    }

    public async openNewLecture(): Promise<void> {
        await this.page.getByRole('button', {name: 'New lecture', exact: true}).click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/lectures\/new/);
        await expect(this.page.locator('.lecture-page')).toBeVisible();
    }

    public async openLecture(name: string): Promise<void> {
        await this.lectureCard(name).click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/lectures\/\d+/);
        await expect(this.page.locator('.lecture-page')).toBeVisible();
        // Wait until the lecture has finished loading (edit mode) before interacting —
        // the delete button only renders once lecture() is populated. Saving before this
        // would run the create path and duplicate the lecture.
        await expect(this.page.locator('.page-footer .btn-danger-ghost')).toBeVisible();
    }

    public async expectLectureInColumn(lectureName: string, columnName: string): Promise<void> {
        await expect(this.column(columnName).locator('[cdkdrag]', {hasText: lectureName})).toBeVisible();
    }

    public async expectLectureAbsent(lectureName: string): Promise<void> {
        await expect(this.lectureCard(lectureName)).toHaveCount(0);
    }
}
