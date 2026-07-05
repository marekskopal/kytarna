import {computed, inject, Injectable, signal} from '@angular/core';
import {Theme} from '@app/models/user';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {StorageService} from '@app/services/storage.service';

const STORAGE_KEY_THEME = 'currentTheme';
const SUPPORTED: readonly Theme[] = ['system', 'light', 'dark'];
const DEFAULT_THEME: Theme = 'system';

export type ResolvedTheme = 'light' | 'dark';

@Injectable({providedIn: 'root'})
export class ThemeService {
    private readonly storage = inject(StorageService);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);

    private readonly current = signal<Theme>(DEFAULT_THEME);
    private readonly resolved = signal<ResolvedTheme>('light');
    private mediaQuery: MediaQueryList | null = null;

    public readonly currentTheme = computed<Theme>(() => this.current());
    public readonly resolvedTheme = computed<ResolvedTheme>(() => this.resolved());
    public readonly supportedThemes = SUPPORTED;

    public init(): void {
        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.mediaQuery.addEventListener('change', this.handleSystemChange);

        const stored = this.storage.get<string>(STORAGE_KEY_THEME);
        const initial = this.normalize(stored) ?? DEFAULT_THEME;
        this.use(initial, {persist: false, sync: false});
    }

    public use(theme: string, options: {persist?: boolean; sync?: boolean} = {}): void {
        const normalized = this.normalize(theme) ?? DEFAULT_THEME;
        this.current.set(normalized);
        this.apply(normalized);

        if (options.persist === true) {
            this.storage.set(STORAGE_KEY_THEME, normalized);
        }

        if (options.sync === true && this.auth.isLoggedIn()) {
            this.currentUserService.update({theme: normalized}).catch(() => {
                // best-effort sync
            });
        }
    }

    public isSupported(theme: string): theme is Theme {
        return (SUPPORTED as readonly string[]).includes(theme);
    }

    private apply(theme: Theme): void {
        const resolved: ResolvedTheme = theme === 'system'
            ? (this.mediaQuery?.matches === true ? 'dark' : 'light')
            : theme;
        this.resolved.set(resolved);

        if (resolved === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }

    private readonly handleSystemChange = (): void => {
        if (this.current() === 'system') {
            this.apply('system');
        }
    };

    private normalize(theme: string | null | undefined): Theme | null {
        if (theme === null || theme === undefined) {
            return null;
        }
        return this.isSupported(theme) ? theme : null;
    }
}
