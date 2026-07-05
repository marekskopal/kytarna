import {provideZonelessChangeDetection, signal} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {Router} from '@angular/router';
import {User} from '@app/models/user';
import {CurrentUserService} from '@app/services/current-user.service';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {OnboardingGuard} from './onboarding.guard';

interface UserServiceStub {
    currentUser: ReturnType<typeof signal<User | null>>;
    load: ReturnType<typeof vi.fn>;
}

interface RouterStub {
    navigate: ReturnType<typeof vi.fn>;
}

function makeUser(onboardingCompletedAt: string | null): User {
    return {
        id: 1,
        email: 'me@example.com',
        name: 'Me',
        locale: 'en',
        currentWorkspaceId: 7,
        systemRole: 'User',
        emailVerified: true,
        onboardingCompletedAt,
    };
}

function setup(user: User | null): {guard: OnboardingGuard; router: RouterStub} {
    const currentUser = signal<User | null>(user);
    const userService: UserServiceStub = {
        currentUser,
        load: vi.fn().mockResolvedValue(user ?? makeUser(null)),
    };
    const router: RouterStub = {navigate: vi.fn().mockResolvedValue(true)};

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: CurrentUserService, useValue: userService},
            {provide: Router, useValue: router},
        ],
    });

    return {guard: TestBed.inject(OnboardingGuard), router};
}

describe('OnboardingGuard', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    it('allows entry when onboarding is not yet completed', async () => {
        const {guard, router} = setup(makeUser(null));

        await expect(guard.canActivate()).resolves.toBe(true);
        expect(router.navigate).not.toHaveBeenCalled();
    });

    it('redirects to /projects when onboarding is already completed', async () => {
        const {guard, router} = setup(makeUser('2026-05-23T10:00:00+00:00'));

        await expect(guard.canActivate()).resolves.toBe(false);
        expect(router.navigate).toHaveBeenCalledWith(['/projects']);
    });
});
