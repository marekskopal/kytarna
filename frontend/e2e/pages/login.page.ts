import {expect, Page} from '@playwright/test';

export class LoginPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.goto('login');
    }

    public async login(email: string, password: string): Promise<void> {
        await this.page.fill('#login-email', email);
        await this.page.fill('#login-password', password);
        await this.page.getByRole('button', {name: 'Sign in'}).click();
    }

    public async expectLoginError(): Promise<void> {
        await expect(this.page.locator('.toast.toast--error').first()).toBeVisible({timeout: 7_000});
    }

    public async expectRedirectedToLogin(): Promise<void> {
        await expect(this.page).toHaveURL(/\/login/);
    }
}
