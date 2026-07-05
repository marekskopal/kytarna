import {HttpEvent, HttpHandlerFn, HttpRequest} from '@angular/common/http';
import {inject} from '@angular/core';
import {RealtimeOriginService} from '@app/services/realtime-origin.service';
import {environment} from '@environments/environment';
import {Observable} from 'rxjs';

export function realtimeOriginInterceptor(
    req: HttpRequest<unknown>,
    next: HttpHandlerFn,
): Observable<HttpEvent<unknown>> {
    if (!req.url.startsWith(environment.apiUrl)) {
        return next(req);
    }
    const origin = inject(RealtimeOriginService);
    return next(req.clone({setHeaders: {'X-Origin-Client-Id': origin.id}}));
}
