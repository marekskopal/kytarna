import {expect, Locator, Page} from '@playwright/test';

export class BoardPage {
    public constructor(private readonly page: Page) {}

    public async expectVisible(): Promise<void> {
        await expect(this.page.locator('.kanban')).toBeVisible();
    }

    public column(name: string): Locator {
        return this.page.locator('.column').filter({has: this.page.locator('.column-title h3', {hasText: name})});
    }

    public lectureCard(name: string): Locator {
        return this.page.locator('.kanban [cdkdrag]').filter({hasText: name});
    }

    public async openNewLecture(): Promise<void> {
        await this.page.getByRole('button', {name: 'New lecture', exact: true}).click();
        await expect(this.page.locator('.drawer')).toBeVisible();
    }

    public async openLecture(name: string): Promise<void> {
        await this.lectureCard(name).click();
        await expect(this.page.locator('.drawer')).toBeVisible();
    }

    public async expectLectureInColumn(lectureName: string, columnName: string): Promise<void> {
        await expect(this.column(columnName).locator('[cdkdrag]', {hasText: lectureName})).toBeVisible();
    }

    public async expectLectureAbsent(lectureName: string): Promise<void> {
        await expect(this.lectureCard(lectureName)).toHaveCount(0);
    }
}
