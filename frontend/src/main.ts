import {provideHttpClient, withInterceptors, withXhr} from '@angular/common/http';
import {enableProdMode, provideZonelessChangeDetection, SecurityContext} from '@angular/core';
import {bootstrapApplication} from '@angular/platform-browser';
import {provideRouter} from '@angular/router';
import {AppComponent} from '@app/app.component';
import {appRoutes} from '@app/app-routes';
import {errorInterceptor} from '@app/core/interceptors/error.interceptor';
import {jwtInterceptor} from '@app/core/interceptors/jwt.interceptor';
import {realtimeOriginInterceptor} from '@app/core/interceptors/realtime-origin.interceptor';
import {environment} from '@environments/environment';
import {provideTranslateService} from '@ngx-translate/core';
import {provideTranslateHttpLoader} from '@ngx-translate/http-loader';
import {provideMarkdown, SANITIZE} from 'ngx-markdown';

if (environment.production) {
    enableProdMode();
}

bootstrapApplication(AppComponent, {
    providers: [
        provideRouter(appRoutes),
        provideHttpClient(withXhr(), withInterceptors([realtimeOriginInterceptor, jwtInterceptor, errorInterceptor])),
        provideTranslateService({
            loader: provideTranslateHttpLoader({
                prefix: environment.i18nPath,
                suffix: `.json?v=${environment.i18nVersion}`,
            }),
        }),
        provideMarkdown({sanitize: {provide: SANITIZE, useValue: SecurityContext.HTML}}),
        provideZonelessChangeDetection(),
    ],
}).catch((err: unknown) => console.error(err));
