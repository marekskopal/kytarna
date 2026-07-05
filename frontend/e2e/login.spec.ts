import {expect, test} from '@playwright/test';

import {LoginPage} from './pages/login.page';
import {readFixtureCredentials} from './support/credentials';

// The error interceptor in src/app/core/interceptors/error.interceptor.ts deliberately
// suppresses toasts on HTTP 401 so the login/refresh flows can present their own UX.

test.use({storageState: {cookies: [], origins: []}});

test.describe('Login', () => {
    test('valid credentials land the user on /projects', async ({page}) => {
        const {email, password} = readFixtureCredentials();
        const login = new LoginPage(page);

        await login.goto();
        await login.login(email, password);

        await expect(page).toHaveURL(/\/projects/, {timeout: 15_000});
        await expect(page.locator('.topbar')).toBeVisible();
    });

    test('invalid credentials keep the user on /login (401 is not redirected away)', async ({page}) => {
        const login = new LoginPage(page);
        await login.goto();
        await login.login('definitely-not-a-user@ukolio.test', 'WrongPass1!');

        // The error interceptor deliberately suppresses toasts for 401 (login/refresh
        // failure flows handle UX themselves). The user-visible signal is that we
        // stay on /login instead of being redirected into the app.
        await login.expectRedirectedToLogin();
        await expect(page).not.toHaveURL(/\/projects/, {timeout: 3_000});
    });

    test('accessing a protected route while logged out redirects to /login', async ({page}) => {
        await page.goto('projects');
        await expect(page).toHaveURL(/\/login/, {timeout: 5_000});
    });
});
