import {HttpClient, provideHttpClient, withInterceptors} from '@angular/common/http';
import {HttpTestingController, provideHttpClientTesting} from '@angular/common/http/testing';
import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {Authentication} from '@app/models/authentication';
import {AuthenticationService} from '@app/services/authentication.service';
import {environment} from '@environments/environment';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {jwtInterceptor} from './jwt.interceptor';

interface AuthStub {
    isLoggedIn: ReturnType<typeof vi.fn>;
    authentication: ReturnType<typeof vi.fn>;
    refreshToken: ReturnType<typeof vi.fn>;
    logout: ReturnType<typeof vi.fn>;
}

function setup(options: {loggedIn: boolean}): {http: HttpClient; httpMock: HttpTestingController; authService: AuthStub} {
    const authentication: Authentication = {accessToken: 'access-token', refreshToken: 'refresh-token', userId: 1};
    const authService: AuthStub = {
        isLoggedIn: vi.fn(() => options.loggedIn),
        authentication: vi.fn(() => options.loggedIn ? authentication : null),
        refreshToken: vi.fn().mockResolvedValue({...authentication, accessToken: 'next-token'}),
        logout: vi.fn(),
    };

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            provideHttpClient(withInterceptors([jwtInterceptor])),
            provideHttpClientTesting(),
            {provide: AuthenticationService, useValue: authService},
        ],
    });

    return {
        http: TestBed.inject(HttpClient),
        httpMock: TestBed.inject(HttpTestingController),
        authService,
    };
}

async function flushMicrotasks(): Promise<void> {
    await new Promise<void>((resolve) => setTimeout(resolve, 0));
}

describe('jwtInterceptor', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    afterEach(() => {
        TestBed.inject(HttpTestingController).verify();
    });

    it('attaches the Authorization header to API requests when logged in', () => {
        const {http, httpMock} = setup({loggedIn: true});

        http.get(`${environment.apiUrl}/projects`).subscribe();

        const req = httpMock.expectOne(`${environment.apiUrl}/projects`);
        expect(req.request.headers.get('Authorization')).toBe('Bearer access-token');
        req.flush({});
    });

    it('does not attach Authorization for non-API URLs', () => {
        const {http, httpMock} = setup({loggedIn: true});

        http.get('/i18n/en.json').subscribe();

        const req = httpMock.expectOne('/i18n/en.json');
        expect(req.request.headers.has('Authorization')).toBe(false);
        req.flush({});
    });

    it('attaches the Authorization header to the MCP OAuth authorize endpoint', () => {
        const {http, httpMock} = setup({loggedIn: true});

        http.post('/mcp/oauth/authorize', {}).subscribe();

        const req = httpMock.expectOne('/mcp/oauth/authorize');
        expect(req.request.headers.get('Authorization')).toBe('Bearer access-token');
        req.flush({});
    });

    it('does not attach Authorization when logged out', () => {
        const {http, httpMock} = setup({loggedIn: false});

        http.get(`${environment.apiUrl}/projects`).subscribe();

        const req = httpMock.expectOne(`${environment.apiUrl}/projects`);
        expect(req.request.headers.has('Authorization')).toBe(false);
        req.flush({});
    });

    it('on 401 it refreshes the token and replays the original request with the new token', async () => {
        const {http, httpMock, authService} = setup({loggedIn: true});

        let observed: unknown = null;
        http.get<{ok: boolean}>(`${environment.apiUrl}/projects`).subscribe((v) => observed = v);

        const initial = httpMock.expectOne(`${environment.apiUrl}/projects`);
        expect(initial.request.headers.get('Authorization')).toBe('Bearer access-token');
        initial.flush({}, {status: 401, statusText: 'Unauthorized'});

        await flushMicrotasks();

        const retry = httpMock.expectOne(`${environment.apiUrl}/projects`);
        expect(retry.request.headers.get('Authorization')).toBe('Bearer access-token');
        retry.flush({ok: true});

        expect(authService.refreshToken).toHaveBeenCalledTimes(1);
        expect(observed).toEqual({ok: true});
    });

    it('deduplicates concurrent refresh attempts across parallel 401s', async () => {
        const {http, httpMock, authService} = setup({loggedIn: true});

        http.get(`${environment.apiUrl}/a`).subscribe();
        http.get(`${environment.apiUrl}/b`).subscribe();

        const reqA = httpMock.expectOne(`${environment.apiUrl}/a`);
        const reqB = httpMock.expectOne(`${environment.apiUrl}/b`);
        reqA.flush({}, {status: 401, statusText: 'Unauthorized'});
        reqB.flush({}, {status: 401, statusText: 'Unauthorized'});

        await flushMicrotasks();

        expect(authService.refreshToken).toHaveBeenCalledTimes(1);

        const retryA = httpMock.expectOne(`${environment.apiUrl}/a`);
        const retryB = httpMock.expectOne(`${environment.apiUrl}/b`);
        retryA.flush({});
        retryB.flush({});
    });

    it('logs the user out and rethrows when the refresh request itself fails', async () => {
        const {http, httpMock, authService} = setup({loggedIn: true});
        authService.refreshToken.mockRejectedValueOnce(new Error('refresh failed'));

        let caught: unknown = null;
        http.get(`${environment.apiUrl}/projects`).subscribe({
            error: (err) => caught = err,
        });

        const initial = httpMock.expectOne(`${environment.apiUrl}/projects`);
        initial.flush({}, {status: 401, statusText: 'Unauthorized'});

        await flushMicrotasks();

        expect(authService.refreshToken).toHaveBeenCalledTimes(1);
        expect(authService.logout).toHaveBeenCalledTimes(1);
        expect(caught).toBeInstanceOf(Error);
    });
});
