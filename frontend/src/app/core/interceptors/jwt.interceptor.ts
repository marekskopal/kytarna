import {HttpErrorResponse, HttpEvent, HttpHandlerFn, HttpRequest} from '@angular/common/http';
import {inject} from '@angular/core';
import {Authentication} from '@app/models/authentication';
import {AuthenticationService} from '@app/services/authentication.service';
import {environment} from '@environments/environment';
import {catchError, from, Observable, shareReplay, switchMap, tap, throwError} from 'rxjs';

const refreshTokenUrl = `${environment.apiUrl}/authentication/refresh-token`;
const authenticatedNonApiUrls = ['/mcp/oauth/authorize'];

let refreshTokenObservable: Observable<Authentication> | null = null;

export function jwtInterceptor(req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> {
    const authService = inject(AuthenticationService);

    req = addAuthHeader(req, authService);

    return next(req).pipe(
        catchError((err: HttpErrorResponse) => {
            if (err.status === 401 && authService.isLoggedIn() && req.url !== refreshTokenUrl) {
                return handleTokenRefresh(req, next, authService);
            }
            return throwError(() => err);
        }),
    );
}

function addAuthHeader(req: HttpRequest<unknown>, authService: AuthenticationService): HttpRequest<unknown> {
    if (!authService.isLoggedIn() || !requiresAuthHeader(req.url)) {
        return req;
    }
    return req.clone({
        setHeaders: {Authorization: `Bearer ${authService.authentication()?.accessToken}`},
    });
}

function requiresAuthHeader(url: string): boolean {
    return url.startsWith(environment.apiUrl) || authenticatedNonApiUrls.includes(url);
}

function handleTokenRefresh(
    req: HttpRequest<unknown>,
    next: HttpHandlerFn,
    authService: AuthenticationService,
): Observable<HttpEvent<unknown>> {
    if (refreshTokenObservable === null) {
        refreshTokenObservable = from(authService.refreshToken()).pipe(
            tap({
                next: () => { refreshTokenObservable = null; },
                error: () => { refreshTokenObservable = null; },
            }),
            shareReplay(1),
        );
    }
    return refreshTokenObservable.pipe(
        switchMap(() => next(addAuthHeader(req, authService))),
        catchError((err) => {
            authService.logout();
            return throwError(() => err);
        }),
    );
}
