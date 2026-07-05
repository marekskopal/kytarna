import {provideHttpClient, withInterceptorsFromDi} from '@angular/common/http';
import {HttpTestingController, provideHttpClientTesting} from '@angular/common/http/testing';
import {provideZonelessChangeDetection, signal} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {User} from '@app/models/user';
import {CurrentUserService} from '@app/services/current-user.service';
import {OnboardingService} from '@app/services/onboarding.service';
import {beforeEach, describe, expect, it} from 'vitest';

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

describe('OnboardingService', () => {
    let service: OnboardingService;
    let httpMock: HttpTestingController;
    let userServiceStub: {currentUser: ReturnType<typeof signal<User | null>>};

    beforeEach(() => {
        userServiceStub = {currentUser: signal<User | null>(makeUser(null))};

        TestBed.configureTestingModule({
            providers: [
                provideZonelessChangeDetection(),
                provideHttpClient(withInterceptorsFromDi()),
                provideHttpClientTesting(),
                {provide: CurrentUserService, useValue: userServiceStub},
            ],
        });

        service = TestBed.inject(OnboardingService);
        httpMock = TestBed.inject(HttpTestingController);
    });

    it('POSTs to /current-user/onboarding-complete and updates the cached user', async () => {
        const updated = makeUser('2026-05-23T10:00:00+00:00');
        const promise = service.complete();

        const req = httpMock.expectOne('/api/current-user/onboarding-complete');
        expect(req.request.method).toBe('POST');
        req.flush(updated);

        await promise;
        expect(userServiceStub.currentUser()?.onboardingCompletedAt).toBe('2026-05-23T10:00:00+00:00');
        httpMock.verify();
    });
});
