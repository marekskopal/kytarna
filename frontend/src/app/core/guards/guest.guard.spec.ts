import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {Router} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {GuestGuard} from './guest.guard';

interface AuthStub {
    isLoggedIn: ReturnType<typeof vi.fn>;
}

interface RouterStub {
    navigate: ReturnType<typeof vi.fn>;
}

function setup(loggedIn: boolean): {guard: GuestGuard; auth: AuthStub; router: RouterStub} {
    const auth: AuthStub = {isLoggedIn: vi.fn(() => loggedIn)};
    const router: RouterStub = {navigate: vi.fn().mockResolvedValue(true)};

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: AuthenticationService, useValue: auth},
            {provide: Router, useValue: router},
        ],
    });

    return {guard: TestBed.inject(GuestGuard), auth, router};
}

describe('GuestGuard', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    it('returns true and does not redirect when the user is logged out', () => {
        const {guard, router} = setup(false);

        expect(guard.canActivate()).toBe(true);
        expect(router.navigate).not.toHaveBeenCalled();
    });

    it('returns false and redirects to /projects when the user is logged in', () => {
        const {guard, router} = setup(true);

        expect(guard.canActivate()).toBe(false);
        expect(router.navigate).toHaveBeenCalledTimes(1);
        expect(router.navigate).toHaveBeenCalledWith(['/projects']);
    });
});
