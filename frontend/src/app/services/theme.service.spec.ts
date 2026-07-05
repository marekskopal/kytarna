import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {StorageService} from '@app/services/storage.service';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {ThemeService} from './theme.service';

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
    prefersDark?: boolean;
}

interface Harness {
    service: ThemeService;
    storage: StorageStub;
    auth: AuthStub;
    currentUserService: CurrentUserStub;
    mediaListeners: Array<(event: MediaQueryListEvent) => void>;
    setPrefersDark: (value: boolean) => void;
}

function setup(options: SetupOptions = {}): Harness {
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

    const mediaListeners: Array<(event: MediaQueryListEvent) => void> = [];
    let prefersDark = options.prefersDark === true;
    window.matchMedia = vi.fn().mockImplementation(() => ({
        get matches(): boolean { return prefersDark; },
        media: '(prefers-color-scheme: dark)',
        addEventListener: (_: string, cb: (event: MediaQueryListEvent) => void): void => {
            mediaListeners.push(cb);
        },
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
        onchange: null,
    })) as unknown as typeof window.matchMedia;

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: StorageService, useValue: storage},
            {provide: AuthenticationService, useValue: auth},
            {provide: CurrentUserService, useValue: currentUserService},
        ],
    });

    return {
        service: TestBed.inject(ThemeService),
        storage,
        auth,
        currentUserService,
        mediaListeners,
        setPrefersDark: (value: boolean): void => { prefersDark = value; },
    };
}

describe('ThemeService', () => {
    const originalMatchMedia = window.matchMedia;

    beforeEach(() => {
        TestBed.resetTestingModule();
        document.documentElement.removeAttribute('data-theme');
    });

    afterEach(() => {
        document.documentElement.removeAttribute('data-theme');
        window.matchMedia = originalMatchMedia;
    });

    it('use("light") removes the data-theme attribute', () => {
        const {service} = setup();
        document.documentElement.setAttribute('data-theme', 'dark');
        service.use('light');
        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
        expect(service.resolvedTheme()).toBe('light');
    });

    it('use("dark") sets data-theme="dark" on <html>', () => {
        const {service} = setup();
        service.use('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(service.resolvedTheme()).toBe('dark');
    });

    it('use("system") resolves to dark when prefers-color-scheme is dark', () => {
        const {service} = setup({prefersDark: true});
        service.init();
        service.use('system');
        expect(service.currentTheme()).toBe('system');
        expect(service.resolvedTheme()).toBe('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('use("system") resolves to light when prefers-color-scheme is light', () => {
        const {service} = setup({prefersDark: false});
        service.init();
        service.use('system');
        expect(service.resolvedTheme()).toBe('light');
        expect(document.documentElement.hasAttribute('data-theme')).toBe(false);
    });

    it('use() falls back to "system" when given an unsupported value', () => {
        const {service} = setup();
        service.use('mauve');
        expect(service.currentTheme()).toBe('system');
    });

    it('use() persists to storage only when persist option is true', () => {
        const {service, storage} = setup();
        service.use('dark');
        expect(storage.set).not.toHaveBeenCalled();
        service.use('dark', {persist: true});
        expect(storage.set).toHaveBeenCalledWith('currentTheme', 'dark');
    });

    it('use() syncs to server when sync is true and the user is logged in', () => {
        const {service, currentUserService} = setup({loggedIn: true});
        service.use('dark', {sync: true});
        expect(currentUserService.update).toHaveBeenCalledWith({theme: 'dark'});
    });

    it('use() does not sync to server when the user is logged out', () => {
        const {service, currentUserService} = setup({loggedIn: false});
        service.use('dark', {sync: true});
        expect(currentUserService.update).not.toHaveBeenCalled();
    });

    it('init() reads the stored value', () => {
        const {service} = setup({storedValue: 'dark'});
        service.init();
        expect(service.currentTheme()).toBe('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('init() defaults to "system" and resolves via prefers-color-scheme', () => {
        const {service} = setup({storedValue: null, prefersDark: true});
        service.init();
        expect(service.currentTheme()).toBe('system');
        expect(service.resolvedTheme()).toBe('dark');
    });

    it('reacts to prefers-color-scheme changes when on system theme', () => {
        const harness = setup({storedValue: null, prefersDark: false});
        harness.service.init();
        expect(harness.service.resolvedTheme()).toBe('light');

        harness.setPrefersDark(true);
        harness.mediaListeners.forEach((cb) => { cb({matches: true} as MediaQueryListEvent); });

        expect(harness.service.resolvedTheme()).toBe('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('ignores prefers-color-scheme changes when on an explicit theme', () => {
        const harness = setup({storedValue: 'light', prefersDark: false});
        harness.service.init();

        harness.setPrefersDark(true);
        harness.mediaListeners.forEach((cb) => { cb({matches: true} as MediaQueryListEvent); });

        expect(harness.service.currentTheme()).toBe('light');
        expect(harness.service.resolvedTheme()).toBe('light');
    });
});
