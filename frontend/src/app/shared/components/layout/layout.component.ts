import {ChangeDetectionStrategy, Component, computed, ElementRef, HostListener, inject, OnInit, signal} from '@angular/core';
import {Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {SearchHit} from '@app/models/search';
import {Locale, Theme, User} from '@app/models/user';
import {Workspace} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LanguageService} from '@app/services/language.service';
import {PermissionsService} from '@app/services/permissions.service';
import {RealtimeService} from '@app/services/realtime.service';
import {ThemeService} from '@app/services/theme.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {NotificationBellComponent} from '@app/shared/components/notification-bell/notification-bell.component';
import {SearchPopoverComponent} from '@app/shared/components/search-popover/search-popover.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-layout',
    standalone: true,
    imports: [BrandLogoComponent, RouterOutlet, RouterLink, RouterLinkActive, TranslatePipe, SearchPopoverComponent, NotificationBellComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './layout.component.html',
    styleUrl: './layout.component.scss',
})
export class LayoutComponent implements OnInit {
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly languageService = inject(LanguageService);
    private readonly themeService = inject(ThemeService);
    private readonly permissionsService = inject(PermissionsService);
    private readonly translate = inject(TranslateService);
    private readonly alertService = inject(AlertService);
    private readonly router = inject(Router);
    private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
    // Instantiate the realtime service so its workspace-id effect is wired up while the user is signed in.
    private readonly _realtime = inject(RealtimeService);

    protected readonly isSystemAdmin = this.permissionsService.isSystemAdmin;

    protected readonly user = signal<User | null>(null);
    protected readonly workspaces = this.workspaceService.workspaces;
    protected readonly currentWorkspaceId = this.workspaceService.currentWorkspaceId;
    protected readonly currentWorkspace = computed<Workspace | null>(() => {
        const id = this.currentWorkspaceId();
        return this.workspaces().find((w) => w.id === id) ?? null;
    });
    protected readonly workspaceInitial = computed<string>(() => {
        const name = this.currentWorkspace()?.name?.trim() ?? '';
        return name.length > 0 ? name.charAt(0).toUpperCase() : '·';
    });
    protected readonly userInitials = computed<string>(() => {
        const u = this.user();
        if (!u) {
            return '·';
        }
        const source = (u.name?.trim() ?? '') || u.email;
        const parts = source.split(/[\s@.]+/).filter((p) => p.length > 0);
        const letters = parts.slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('');
        return letters.length > 0 ? letters : '·';
    });
    protected readonly switcherOpen = signal(false);
    protected readonly userMenuOpen = signal(false);
    protected readonly searchOpen = signal(false);
    protected readonly currentLang = this.languageService.currentLang;
    protected readonly supportedLangs = this.languageService.supportedLangs;
    protected readonly currentTheme = this.themeService.currentTheme;
    protected readonly supportedThemes = this.themeService.supportedThemes;

    public async ngOnInit(): Promise<void> {
        try {
            const user = await this.currentUserService.load();
            this.user.set(user);
            this.workspaceService.currentWorkspaceId.set(user.currentWorkspaceId);
            await this.workspaceService.loadAll();
            await this.workspaceService.loadCurrentMembers();
        } catch {
            // Interceptor handles 401 -> refresh / logout
        }
    }

    @HostListener('document:keydown', ['$event'])
    protected onDocumentKeydown(event: KeyboardEvent): void {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            this.openSearch();
            return;
        }
        if (event.key === 'Escape' && this.searchOpen()) {
            this.closeSearch();
        }
    }

    @HostListener('document:click', ['$event.target'])
    protected onDocumentClick(target: EventTarget | null): void {
        if (!(target instanceof Node)) {
            return;
        }
        if (this.switcherOpen()) {
            const el = this.host.nativeElement.querySelector('.workspace-switcher');
            if (el && !el.contains(target)) {
                this.switcherOpen.set(false);
            }
        }
        if (this.userMenuOpen()) {
            const el = this.host.nativeElement.querySelector('.user-menu');
            if (el && !el.contains(target)) {
                this.userMenuOpen.set(false);
            }
        }
    }

    protected toggleSwitcher(): void {
        this.switcherOpen.update((v) => !v);
        if (this.switcherOpen()) {
            this.userMenuOpen.set(false);
        }
    }

    protected toggleUserMenu(): void {
        this.userMenuOpen.update((v) => !v);
        if (this.userMenuOpen()) {
            this.switcherOpen.set(false);
        }
    }

    protected async setLanguage(lang: Locale): Promise<void> {
        this.userMenuOpen.set(false);
        this.languageService.use(lang, {persist: true, sync: true});
    }

    protected setTheme(theme: Theme): void {
        this.userMenuOpen.set(false);
        this.themeService.use(theme, {persist: true, sync: true});
    }

    protected themeLabelKey(theme: Theme): string {
        return 'app.settings.theme' + theme.charAt(0).toUpperCase() + theme.slice(1);
    }

    protected async switchTo(workspaceId: number): Promise<void> {
        this.switcherOpen.set(false);
        try {
            await this.workspaceService.switchTo(workspaceId);
            await this.router.navigate(['/projects']);
        } catch {
            // error interceptor
        }
    }

    protected async createWorkspace(): Promise<void> {
        this.switcherOpen.set(false);
        const promptText = await this.translate.instant('app.workspaces.createPrompt') as string;
        const name = prompt(promptText);
        if (name === null || name.trim() === '') {
            return;
        }
        try {
            const ws = await this.workspaceService.create(name.trim());
            await this.workspaceService.switchTo(ws.id);
            const message = await this.translate.instant('app.workspaces.created', {name: ws.name}) as string;
            this.alertService.success(message);
            await this.router.navigate(['/projects']);
        } catch {
            // error interceptor
        }
    }

    protected openSearch(): void {
        this.searchOpen.set(true);
        this.switcherOpen.set(false);
        this.userMenuOpen.set(false);
    }

    protected closeSearch(): void {
        this.searchOpen.set(false);
    }

    protected async onSearchHit(hit: SearchHit): Promise<void> {
        this.searchOpen.set(false);
        await this.router.navigate(['/tasks'], {queryParams: {open: hit.code}});
    }

    protected openCommandPalette(): void {
        // Placeholder — wired up later in the agent command palette work.
    }

    protected logout(): void {
        this.userMenuOpen.set(false);
        this.currentUserService.clear();
        this.workspaceService.clear();
        this.auth.logout();
    }

    protected readonly resendingVerification = signal(false);

    protected async resendVerification(): Promise<void> {
        if (this.resendingVerification()) {
            return;
        }
        this.resendingVerification.set(true);
        try {
            await this.auth.resendVerification();
            const message = await this.translate.instant('app.verifyBanner.resent') as string;
            this.alertService.success(message);
        } catch {
            // error interceptor
        } finally {
            this.resendingVerification.set(false);
        }
    }

    protected async refreshUser(): Promise<void> {
        try {
            const user = await this.currentUserService.load();
            this.user.set(user);
        } catch {
            // ignore
        }
    }
}
