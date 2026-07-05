import {provideHttpClient, withInterceptorsFromDi} from '@angular/common/http';
import {provideHttpClientTesting} from '@angular/common/http/testing';
import {type Provider, provideZonelessChangeDetection, signal} from '@angular/core';
import {provideRouter} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {of, Subject} from 'rxjs';

export function commonTestProviders(): Provider[] {
    return [
        provideZonelessChangeDetection(),
        provideRouter([]),
        provideHttpClient(withInterceptorsFromDi()),
        provideHttpClientTesting(),
    ];
}

export function provideTranslateStub(): Provider {
    return {provide: TranslateService, useFactory: createTranslateStub};
}

export function createTranslateStub(): Partial<TranslateService> {
    return {
        instant: (key: string | string[]) => key as string,
        // ngx-translate v18: TranslatePipe consumes this signal-returning method.
        translate: (key: string | string[]) => signal(key as string),
        get: (key: string | string[]) => of(key as string),
        stream: (key: string | string[]) => of(key as string),
        use: () => of(undefined as unknown as Record<string, string>),
        addLangs: () => undefined,
        setFallbackLang: () => undefined,
        getLangs: () => [],
        currentLang: 'en',
        fallbackLang: 'en',
        onLangChange: new Subject(),
        onTranslationChange: new Subject(),
        onDefaultLangChange: new Subject(),
        onFallbackLangChange: new Subject(),
    } as Partial<TranslateService>;
}
