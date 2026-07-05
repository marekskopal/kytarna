import {AfterViewInit, ChangeDetectionStrategy, Component, inject, NgZone, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {LanguageService} from '@app/services/language.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-login',
    standalone: true,
    imports: [BrandLogoComponent, ReactiveFormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './login.component.html',
    styleUrl: './auth-page.scss',
})
export class LoginComponent implements AfterViewInit {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);
    private readonly language = inject(LanguageService);
    private readonly zone = inject(NgZone);

    protected readonly saving = signal(false);
    protected readonly googleLoading = signal(false);
    protected readonly googleEnabled = signal(false);
    protected readonly mcpUrl = `${window.location.origin}/mcp`;
    protected readonly form = this.fb.nonNullable.group({
        email: ['', [Validators.required, Validators.email]],
        password: ['', Validators.required],
    });

    public async ngAfterViewInit(): Promise<void> {
        let googleClientId: string;
        try {
            googleClientId = await this.auth.googleClientId();
        } catch {
            return;
        }

        if (googleClientId === '') {
            return;
        }

        this.googleEnabled.set(true);
        this.initializeGoogleSignIn(googleClientId);
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.login(this.form.value.email!, this.form.value.password!);
            this.router.navigateByUrl(this.returnUrl());
        } catch {
            // error interceptor shows the toast
        } finally {
            this.saving.set(false);
        }
    }

    private initializeGoogleSignIn(googleClientId: string): void {
        const tryRender = (): void => {
            const container = document.getElementById('googleButtonContainer');
            if (window.google?.accounts?.id !== undefined && container !== null) {
                window.google.accounts.id.initialize({
                    client_id: googleClientId,
                    callback: (response) => this.handleGoogleCallback(response),
                });
                window.google.accounts.id.renderButton(container, {
                    type: 'standard',
                    theme: 'outline',
                    size: 'large',
                    text: 'signin_with',
                    shape: 'rectangular',
                    width: container.offsetWidth,
                });
            } else {
                setTimeout(tryRender, 100);
            }
        };

        tryRender();
    }

    private handleGoogleCallback(response: google.accounts.id.CredentialResponse): void {
        this.zone.run(async () => {
            this.googleLoading.set(true);
            try {
                await this.auth.googleLogin(response.credential, this.language.currentLang());
                this.router.navigateByUrl(this.returnUrl());
            } catch {
                // error interceptor shows the toast
            } finally {
                this.googleLoading.set(false);
            }
        });
    }

    private returnUrl(): string {
        return this.route.snapshot.queryParamMap.get('returnUrl') ?? '/projects';
    }
}
