import {expect, Locator, Page} from '@playwright/test';

function escapeRegExp(input: string): string {
    return input.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export class CoursesPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.goto('courses');
        await expect(this.page.locator('.page-title')).toBeVisible();
    }

    public async gotoNew(): Promise<void> {
        await this.page.goto('courses/new');
        await expect(this.page).toHaveURL(/\/courses\/new/);
    }

    public courseCard(name: string): Locator {
        // Anchor on the course-name link to avoid substring false positives between
        // e.g. "E2E Course 1" and "E2E Course 1 (renamed)".
        const exactName = new RegExp(`^${escapeRegExp(name)}$`);
        return this.page.locator('.course-card').filter({
            has: this.page.locator('.course-name').filter({hasText: exactName}),
        });
    }

    public async expectCourseVisible(name: string): Promise<void> {
        await expect(this.courseCard(name)).toBeVisible();
    }

    public async expectCourseAbsent(name: string): Promise<void> {
        await expect(this.courseCard(name)).toHaveCount(0);
    }

    public async openBoard(name: string): Promise<void> {
        await this.courseCard(name).getByRole('link', {name: 'Open board', exact: true}).click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/board/);
    }

    public async openWorkflow(name: string): Promise<void> {
        await this.courseCard(name).getByRole('link', {name: 'Workflow', exact: true}).click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/workflow/);
    }

    public async openEdit(name: string): Promise<void> {
        await this.courseCard(name).getByRole('link', {name: 'Edit', exact: true}).click();
        await expect(this.page).toHaveURL(/\/courses\/\d+\/edit/);
    }

    public async deleteCourse(name: string): Promise<void> {
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.courseCard(name).locator('.course-delete').click();
        await this.expectCourseAbsent(name);
    }
}
