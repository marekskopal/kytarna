import {expect, Page} from '@playwright/test';

export class OnboardingPage {
    public constructor(private readonly page: Page) {}

    public async expectStep(step: 1 | 2 | 3): Promise<void> {
        await expect(this.page).toHaveURL(new RegExp(`/onboarding/step-${step}`), {timeout: 15_000});
    }

    // Step 1 offers two paths: create your own workspace (Teacher) or join one (Student).
    public async chooseTeacher(): Promise<void> {
        await this.page.locator('.onboarding-tile').filter({hasText: 'Create my workspace'}).click();
        await expect(this.page.locator('#ob-ws-name')).toBeVisible();
    }

    public async fillWorkspaceName(name: string): Promise<void> {
        await this.page.fill('#ob-ws-name', name);
    }

    public async submitCreateWorkspace(): Promise<void> {
        await this.page.getByRole('button', {name: 'Create workspace', exact: true}).click();
    }

    // Convenience: run the whole Teacher path. Creating a workspace completes onboarding
    // and lands the user at /courses.
    public async createWorkspaceAsTeacher(name: string): Promise<void> {
        await this.chooseTeacher();
        await this.fillWorkspaceName(name);
        await this.submitCreateWorkspace();
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
