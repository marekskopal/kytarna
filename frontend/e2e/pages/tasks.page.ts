import {expect, Locator, Page} from '@playwright/test';

type ViewName = 'List' | 'Calendar' | 'Timeline';

export class TasksPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.locator('.nav').getByRole('link', {name: 'Tasks', exact: true}).click();
        await expect(this.page).toHaveURL(/\/tasks/);
    }

    public async switchView(view: ViewName): Promise<void> {
        await this.page.locator('.view-switch').getByRole('button', {name: view, exact: true}).click();
    }

    // ── Calendar ──
    public async expectCalendarVisible(): Promise<void> {
        await expect(this.page.locator('.cal')).toBeVisible();
    }

    public calendarChip(name: string): Locator {
        return this.page.locator('.cal-chip', {hasText: name});
    }

    /** The day-number text of the calendar cell containing the named chip. */
    public calendarChipDay(name: string): Locator {
        return this.page.locator('.cal-cell', {has: this.page.locator('.cal-chip', {hasText: name})})
            .locator('.cal-cell-day');
    }

    public async calendarNext(): Promise<void> {
        await this.page.locator('.cal-toolbar .cal-nav-btn').nth(1).click();
    }

    public async calendarToday(): Promise<void> {
        await this.page.locator('.cal-toolbar').getByRole('button', {name: 'Today', exact: true}).click();
    }

    // ── Timeline ──
    public async expectTimelineVisible(): Promise<void> {
        await expect(this.page.locator('.tl')).toBeVisible();
    }

    public timelineGroup(projectName: string): Locator {
        return this.page.locator('.tl-group-name', {hasText: projectName});
    }

    public timelineBar(name: string): Locator {
        return this.page.locator('.tl-bar', {hasText: name});
    }

    public timelineTrack(): Locator {
        return this.page.locator('.tl-track');
    }

    // ── List ──
    public async openGridRow(name: string): Promise<void> {
        await this.page.locator('.task-row', {hasText: name}).first().click();
    }
}
