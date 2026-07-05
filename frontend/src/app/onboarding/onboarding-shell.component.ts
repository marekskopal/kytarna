import {ChangeDetectionStrategy, Component, computed, inject, OnDestroy, OnInit, signal} from '@angular/core';
import {NavigationEnd, Router, RouterOutlet} from '@angular/router';
import {OnboardingStateService} from '@app/onboarding/onboarding-state.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {OnboardingService} from '@app/services/onboarding.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';
import {filter, Subscription} from 'rxjs';

@Component({
    selector: 'uk-onboarding-shell',
    standalone: true,
    imports: [BrandLogoComponent, RouterOutlet, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './onboarding-shell.component.html',
    styleUrl: './onboarding-shell.component.scss',
})
export class OnboardingShellComponent implements OnInit, OnDestroy {
    private readonly router = inject(Router);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly onboardingService = inject(OnboardingService);
    protected readonly state = inject(OnboardingStateService);

    private routerSub: Subscription | null = null;

    protected readonly currentStep = signal(this.deriveStep(this.router.url));
    protected readonly skipping = signal(false);

    protected readonly userEmail = computed(() => this.currentUserService.currentUser()?.email ?? '');
    protected readonly workspaceName = computed(() => {
        const wsId = this.workspaceService.currentWorkspaceId();
        if (wsId === null) {
            return '';
        }
        return this.workspaceService.workspaces().find((w) => w.id === wsId)?.name ?? '';
    });

    public ngOnInit(): void {
        this.routerSub = this.router.events
            .pipe(filter((e): e is NavigationEnd => e instanceof NavigationEnd))
            .subscribe((e) => this.currentStep.set(this.deriveStep(e.urlAfterRedirects)));
    }

    public ngOnDestroy(): void {
        this.routerSub?.unsubscribe();
        this.state.reset();
    }

    protected stepStateClass(step: number): string {
        const current = this.currentStep();
        if (step < current) {
            return 'is-done';
        }
        if (step === current) {
            return 'is-active';
        }
        return 'is-pending';
    }

    protected progressPillClass(step: number): string {
        const current = this.currentStep();
        if (step === current) {
            return 'is-active';
        }
        if (step < current) {
            return 'is-done';
        }
        return 'is-pending';
    }

    protected async onSkip(): Promise<void> {
        if (this.skipping()) {
            return;
        }
        this.skipping.set(true);
        try {
            await this.onboardingService.complete();
            await this.router.navigateByUrl('/projects');
        } finally {
            this.skipping.set(false);
        }
    }

    protected onSignOut(): void {
        this.auth.logout();
    }

    private deriveStep(url: string): number {
        if (url.includes('/onboarding/step-3')) {
            return 3;
        }
        if (url.includes('/onboarding/step-2')) {
            return 2;
        }
        return 1;
    }
}
