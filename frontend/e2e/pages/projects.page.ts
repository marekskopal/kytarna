import {expect, Locator, Page} from '@playwright/test';

function escapeRegExp(input: string): string {
    return input.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export class ProjectsPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.goto('projects');
        await expect(this.page.locator('.page-title')).toBeVisible();
    }

    public async gotoNew(): Promise<void> {
        await this.page.goto('projects/new');
        await expect(this.page).toHaveURL(/\/projects\/new/);
    }

    public projectCard(name: string): Locator {
        // Anchor on the project-name link to avoid substring false positives between
        // e.g. "E2E Project 1" and "E2E Project 1 (renamed)".
        const exactName = new RegExp(`^${escapeRegExp(name)}$`);
        return this.page.locator('.project-card').filter({
            has: this.page.locator('.project-name').filter({hasText: exactName}),
        });
    }

    public async expectProjectVisible(name: string): Promise<void> {
        await expect(this.projectCard(name)).toBeVisible();
    }

    public async expectProjectAbsent(name: string): Promise<void> {
        await expect(this.projectCard(name)).toHaveCount(0);
    }

    public async openBoard(name: string): Promise<void> {
        await this.projectCard(name).getByRole('link', {name: 'Open board', exact: true}).click();
        await expect(this.page).toHaveURL(/\/projects\/\d+\/board/);
    }

    public async openWorkflow(name: string): Promise<void> {
        await this.projectCard(name).getByRole('link', {name: 'Workflow', exact: true}).click();
        await expect(this.page).toHaveURL(/\/projects\/\d+\/workflow/);
    }

    public async openEdit(name: string): Promise<void> {
        await this.projectCard(name).getByRole('link', {name: 'Edit', exact: true}).click();
        await expect(this.page).toHaveURL(/\/projects\/\d+\/edit/);
    }

    public async deleteProject(name: string): Promise<void> {
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.projectCard(name).locator('.project-delete').click();
        await this.expectProjectAbsent(name);
    }
}
