import {HttpErrorResponse, HttpEvent, HttpHandlerFn, HttpRequest} from '@angular/common/http';
import {inject} from '@angular/core';
import {AlertService} from '@app/services/alert.service';
import {environment} from '@environments/environment';
import {Observable, throwError} from 'rxjs';
import {catchError} from 'rxjs/operators';

export function errorInterceptor(req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> {
    const alertService = inject(AlertService);

    if (!req.url.startsWith(environment.apiUrl)) {
        return next(req);
    }

    return next(req).pipe(
        catchError((err: HttpErrorResponse) => {
            if (err.status === 401) {
                return throwError(() => err);
            }
            const message = (err.error && typeof err.error === 'object' && 'message' in err.error)
                ? String(err.error.message)
                : 'An unexpected error occurred.';
            alertService.error(message);
            return throwError(() => err);
        }),
    );
}
