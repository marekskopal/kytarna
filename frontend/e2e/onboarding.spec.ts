import {expect, test} from '@playwright/test';

import {OnboardingPage} from './pages/onboarding.page';
import {SignUpPage} from './pages/sign-up.page';

test.use({storageState: {cookies: [], origins: []}});

test.describe('Onboarding wizard', () => {
    test('sign-up routes into onboarding, project step advances to invites, skip lands at /projects', async ({page}) => {
        const signUp = new SignUpPage(page);
        const onboarding = new OnboardingPage(page);

        const stamp = Date.now();
        const email = `onboarding-${stamp}@ukolio.test`;
        const name = `Onboarding ${stamp}`;

        await signUp.goto();
        await signUp.signUp(name, email, 'Test1234!');
        await onboarding.expectStep(1);

        await onboarding.fillProjectName(`Demo ${stamp}`);
        await onboarding.continueStep();
        await onboarding.expectStep(2);

        await onboarding.skip();
        await expect(page).toHaveURL(/\/projects/, {timeout: 15_000});
    });

    test('completed onboarding is sticky — revisiting /onboarding redirects to /projects', async ({page}) => {
        const signUp = new SignUpPage(page);
        const onboarding = new OnboardingPage(page);

        const stamp = Date.now();
        const email = `onboarding-sticky-${stamp}@ukolio.test`;
        const name = `Sticky ${stamp}`;

        await signUp.goto();
        await signUp.signUp(name, email, 'Test1234!');
        await onboarding.expectStep(1);
        await onboarding.skip();
        await expect(page).toHaveURL(/\/projects/, {timeout: 15_000});

        await page.goto('onboarding/step-1');
        await expect(page).toHaveURL(/\/projects/, {timeout: 15_000});
    });
});
