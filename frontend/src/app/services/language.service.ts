import {computed, inject, Injectable, signal} from '@angular/core';
import {Locale} from '@app/models/user';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {StorageService} from '@app/services/storage.service';
import {TranslateService} from '@ngx-translate/core';

const STORAGE_KEY_LANG = 'currentLanguage';
const SUPPORTED: readonly Locale[] = ['en', 'cs'];
const DEFAULT_LANG: Locale = 'en';

@Injectable({providedIn: 'root'})
export class LanguageService {
    private readonly translate = inject(TranslateService);
    private readonly storage = inject(StorageService);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);

    private readonly current = signal<Locale>(DEFAULT_LANG);
    public readonly currentLang = computed<Locale>(() => this.current());
    public readonly supportedLangs = SUPPORTED;

    public init(): void {
        this.translate.addLangs([...SUPPORTED]);
        this.translate.setFallbackLang(DEFAULT_LANG);

        const fromQuery = this.readQueryLang();
        if (fromQuery !== null) {
            this.use(fromQuery, {persist: true, sync: false});
            return;
        }

        const stored = this.storage.get<string>(STORAGE_KEY_LANG);
        const initial = this.normalize(stored) ?? this.normalize(navigator.language) ?? DEFAULT_LANG;
        this.use(initial, {persist: false, sync: false});
    }

    public use(lang: string, options: {persist?: boolean; sync?: boolean} = {}): void {
        const normalized = this.normalize(lang) ?? DEFAULT_LANG;
        this.translate.use(normalized);
        this.current.set(normalized);

        if (options.persist === true) {
            this.storage.set(STORAGE_KEY_LANG, normalized);
        }

        if (options.sync === true && this.auth.isLoggedIn()) {
            this.currentUserService.update({locale: normalized}).catch(() => {
                // best-effort sync
            });
        }
    }

    public isSupported(lang: string): lang is Locale {
        return (SUPPORTED as readonly string[]).includes(lang);
    }

    private normalize(lang: string | null | undefined): Locale | null {
        if (lang === null || lang === undefined) {
            return null;
        }
        const head = lang.slice(0, 2).toLowerCase();
        return this.isSupported(head) ? head : null;
    }

    private readQueryLang(): Locale | null {
        const param = new URLSearchParams(window.location.search).get('lang');
        return this.normalize(param);
    }
}
