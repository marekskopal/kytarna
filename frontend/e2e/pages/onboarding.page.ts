import {expect, Page} from '@playwright/test';

export class OnboardingPage {
    public constructor(private readonly page: Page) {}

    public async expectStep(step: 1 | 2 | 3): Promise<void> {
        await expect(this.page).toHaveURL(new RegExp(`/onboarding/step-${step}`), {timeout: 15_000});
    }

    public async fillProjectName(name: string): Promise<void> {
        await this.page.fill('#ob-name', name);
    }

    public async continueStep(): Promise<void> {
        await this.page.getByRole('button', {name: 'Continue'}).click();
    }

    public async skip(): Promise<void> {
        await this.page.getByRole('button', {name: 'Skip for now'}).click();
    }

    public async skipInvites(): Promise<void> {
        await this.page.getByRole('button', {name: 'Skip invites'}).click();
    }

    public async finish(): Promise<void> {
        await this.page.getByRole('button', {name: 'Open workspace'}).click();
    }
}
