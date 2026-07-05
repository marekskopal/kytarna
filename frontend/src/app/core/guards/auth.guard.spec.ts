import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {ActivatedRouteSnapshot, Router, RouterStateSnapshot} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {AuthGuard} from './auth.guard';

interface AuthStub {
    isLoggedIn: ReturnType<typeof vi.fn>;
}

interface RouterStub {
    navigate: ReturnType<typeof vi.fn>;
}

function setup(loggedIn: boolean): {guard: AuthGuard; auth: AuthStub; router: RouterStub} {
    const auth: AuthStub = {isLoggedIn: vi.fn(() => loggedIn)};
    const router: RouterStub = {navigate: vi.fn().mockResolvedValue(true)};

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: AuthenticationService, useValue: auth},
            {provide: Router, useValue: router},
        ],
    });

    return {guard: TestBed.inject(AuthGuard), auth, router};
}

function snapshot(url: string): {route: ActivatedRouteSnapshot; state: RouterStateSnapshot} {
    return {
        route: {} as ActivatedRouteSnapshot,
        state: {url} as RouterStateSnapshot,
    };
}

describe('AuthGuard', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    it('returns true and does not redirect when the user is logged in', () => {
        const {guard, router} = setup(true);
        const {route, state} = snapshot('/projects');

        expect(guard.canActivate(route, state)).toBe(true);
        expect(router.navigate).not.toHaveBeenCalled();
    });

    it('returns false and redirects to /login with returnUrl when logged out', () => {
        const {guard, router} = setup(false);
        const {route, state} = snapshot('/projects/42/board');

        expect(guard.canActivate(route, state)).toBe(false);
        expect(router.navigate).toHaveBeenCalledTimes(1);
        expect(router.navigate).toHaveBeenCalledWith(['/login'], {queryParams: {returnUrl: '/projects/42/board'}});
    });
});
