import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {StorageService} from '@app/services/storage.service';
import {TranslateService} from '@ngx-translate/core';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {LanguageService} from './language.service';

interface TranslateStub {
    addLangs: ReturnType<typeof vi.fn>;
    setFallbackLang: ReturnType<typeof vi.fn>;
    use: ReturnType<typeof vi.fn>;
}

interface StorageStub {
    get: ReturnType<typeof vi.fn>;
    set: ReturnType<typeof vi.fn>;
    remove: ReturnType<typeof vi.fn>;
}

interface AuthStub {
    isLoggedIn: ReturnType<typeof vi.fn>;
}

interface CurrentUserStub {
    update: ReturnType<typeof vi.fn>;
}

interface SetupOptions {
    storedValue?: string | null;
    loggedIn?: boolean;
}

interface Harness {
    service: LanguageService;
    translate: TranslateStub;
    storage: StorageStub;
    auth: AuthStub;
    currentUserService: CurrentUserStub;
}

function setup(options: SetupOptions = {}): Harness {
    const translate: TranslateStub = {
        addLangs: vi.fn(),
        setFallbackLang: vi.fn(),
        use: vi.fn(),
    };
    const storage: StorageStub = {
        get: vi.fn(() => options.storedValue ?? null),
        set: vi.fn(),
        remove: vi.fn(),
    };
    const auth: AuthStub = {
        isLoggedIn: vi.fn(() => options.loggedIn === true),
    };
    const currentUserService: CurrentUserStub = {
        update: vi.fn().mockResolvedValue({}),
    };

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: TranslateService, useValue: translate},
            {provide: StorageService, useValue: storage},
            {provide: AuthenticationService, useValue: auth},
            {provide: CurrentUserService, useValue: currentUserService},
        ],
    });

    return {service: TestBed.inject(LanguageService), translate, storage, auth, currentUserService};
}

describe('LanguageService', () => {
    const originalNavigatorLanguage = navigator.language;
    const originalPath = window.location.pathname + window.location.search;

    beforeEach(() => {
        TestBed.resetTestingModule();
        window.history.replaceState({}, '', '/');
    });

    afterEach(() => {
        Object.defineProperty(navigator, 'language', {value: originalNavigatorLanguage, configurable: true});
        window.history.replaceState({}, '', originalPath);
    });

    it('use() normalizes regional locales like "cs-CZ" down to the base code', () => {
        const {service} = setup();
        service.use('cs-CZ');
        expect(service.currentLang()).toBe('cs');
    });

    it('use() falls back to "en" when the locale is unsupported', () => {
        const {service} = setup();
        service.use('fr-FR');
        expect(service.currentLang()).toBe('en');
    });

    it('use() persists to storage only when persist option is true', () => {
        const {service, storage} = setup();
        service.use('cs');
        expect(storage.set).not.toHaveBeenCalled();
        service.use('cs', {persist: true});
        expect(storage.set).toHaveBeenCalledWith('currentLanguage', 'cs');
    });

    it('use() syncs to server when sync is true and the user is logged in', () => {
        const {service, currentUserService} = setup({loggedIn: true});
        service.use('cs', {sync: true});
        expect(currentUserService.update).toHaveBeenCalledWith({locale: 'cs'});
    });

    it('use() does not sync to server when the user is logged out', () => {
        const {service, currentUserService} = setup({loggedIn: false});
        service.use('cs', {sync: true});
        expect(currentUserService.update).not.toHaveBeenCalled();
    });

    it('init() prefers ?lang= query parameter over every other source', () => {
        window.history.replaceState({}, '', '/?lang=cs');
        Object.defineProperty(navigator, 'language', {value: 'en-US', configurable: true});
        const {service, storage} = setup({storedValue: 'en'});

        service.init();

        expect(service.currentLang()).toBe('cs');
        expect(storage.set).toHaveBeenCalledWith('currentLanguage', 'cs');
    });

    it('init() falls back to storage when there is no query parameter', () => {
        Object.defineProperty(navigator, 'language', {value: 'en-US', configurable: true});
        const {service} = setup({storedValue: 'cs'});

        service.init();

        expect(service.currentLang()).toBe('cs');
    });

    it('init() falls back to navigator.language when storage is empty', () => {
        Object.defineProperty(navigator, 'language', {value: 'cs-CZ', configurable: true});
        const {service} = setup({storedValue: null});

        service.init();

        expect(service.currentLang()).toBe('cs');
    });

    it('init() falls back to "en" when nothing matches a supported locale', () => {
        Object.defineProperty(navigator, 'language', {value: 'de-DE', configurable: true});
        const {service} = setup({storedValue: null});

        service.init();

        expect(service.currentLang()).toBe('en');
    });
});
