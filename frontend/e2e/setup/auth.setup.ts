import * as fs from 'fs';
import * as path from 'path';

import {test as setup} from '@playwright/test';

import {OnboardingPage} from '../pages/onboarding.page';
import {SignUpPage} from '../pages/sign-up.page';

const AUTH_DIR = path.resolve(__dirname, '../.auth');
const STORAGE_STATE_PATH = path.join(AUTH_DIR, 'user.json');
const CREDENTIALS_PATH = path.join(AUTH_DIR, 'credentials.json');

export const E2E_PASSWORD = process.env['E2E_PASSWORD'] ?? 'Test1234!';

setup('sign up the shared fixture user', async ({page}) => {
    if (!fs.existsSync(AUTH_DIR)) {
        fs.mkdirSync(AUTH_DIR, {recursive: true});
    }

    const runId = Date.now();
    const email = process.env['E2E_USER_EMAIL'] ?? `e2e-${runId}@kytarna.test`;
    const name = process.env['E2E_USER_NAME'] ?? `E2E User ${runId}`;

    const signUp = new SignUpPage(page);
    const onboarding = new OnboardingPage(page);
    await signUp.goto();
    await signUp.signUp(name, email, E2E_PASSWORD);
    await signUp.expectLandedAtOnboarding();
    // Create a workspace (Teacher path) rather than skipping — content specs (courses,
    // lectures) need a workspace to create things in, and it gives the workspace-switching
    // spec a real starting workspace. Creating a workspace completes onboarding.
    await onboarding.createWorkspaceAsTeacher(`E2E Workspace ${runId}`);
    await signUp.expectLandedInsideApp();

    await page.context().storageState({path: STORAGE_STATE_PATH});
    fs.writeFileSync(CREDENTIALS_PATH, JSON.stringify({email, password: E2E_PASSWORD, name}, null, 2));
});
