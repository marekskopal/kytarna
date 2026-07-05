import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {OAuthService} from '@app/services/oauth.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-oauth-authorize',
    standalone: true,
    imports: [BrandLogoComponent, ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './oauth-authorize.component.html',
    styleUrl: '../authentication/auth-page.scss',
})
export class OAuthAuthorizeComponent implements OnInit {
    private readonly fb = inject(FormBuilder);
    private readonly route = inject(ActivatedRoute);
    private readonly auth = inject(AuthenticationService);
    private readonly oauth = inject(OAuthService);

    protected readonly isLoggedIn = this.auth.isLoggedIn;
    protected readonly clientName = signal<string>('');
    protected readonly saving = signal(false);
    protected readonly authorizing = signal(false);
    protected readonly errorKey = signal<string>('');
    protected readonly mcpUrl = `${window.location.origin}/mcp`;

    protected readonly form = this.fb.nonNullable.group({
        email: ['', [Validators.required, Validators.email]],
        password: ['', Validators.required],
    });

    private clientId = '';
    private redirectUri = '';
    private codeChallenge = '';
    private codeChallengeMethod = '';
    private state = '';

    public ngOnInit(): void {
        const params = this.route.snapshot.queryParamMap;
        this.clientId = params.get('client_id') ?? '';
        this.redirectUri = params.get('redirect_uri') ?? '';
        this.codeChallenge = params.get('code_challenge') ?? '';
        this.codeChallengeMethod = params.get('code_challenge_method') ?? '';
        this.state = params.get('state') ?? '';

        if (params.get('response_type') !== 'code' || this.clientId === '' || this.redirectUri === '') {
            this.errorKey.set('app.auth.oauth.invalidRequest');
            return;
        }

        void this.loadClientInfo();
    }

    private async loadClientInfo(): Promise<void> {
        try {
            const info = await this.oauth.getClientInfo(this.clientId);
            this.clientName.set(info.clientName);
        } catch {
            this.errorKey.set('app.auth.oauth.unknownClient');
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.login(this.form.value.email!, this.form.value.password!);
            await this.performAuthorize();
        } catch {
            // error interceptor shows the toast
        } finally {
            this.saving.set(false);
        }
    }

    protected async onAuthorize(): Promise<void> {
        this.authorizing.set(true);
        try {
            await this.performAuthorize();
        } catch {
            // error interceptor shows the toast
        } finally {
            this.authorizing.set(false);
        }
    }

    private async performAuthorize(): Promise<void> {
        const response = await this.oauth.authorize({
            clientId: this.clientId,
            redirectUri: this.redirectUri,
            codeChallenge: this.codeChallenge,
            codeChallengeMethod: this.codeChallengeMethod,
            state: this.state,
        });

        const params = new URLSearchParams({code: response.code});
        if (response.state !== '') {
            params.set('state', response.state);
        }

        // The backend only ever hands back a redirect_uri it validated at registration, but this
        // value flows straight into a navigation — guard the sink so a non-http(s) scheme (e.g.
        // javascript:) can never execute in the app origin even if that invariant regressed.
        let target: URL;
        try {
            target = new URL(response.redirectUri);
        } catch {
            this.errorKey.set('app.auth.oauth.invalidRequest');
            return;
        }
        if (target.protocol !== 'https:' && target.protocol !== 'http:') {
            this.errorKey.set('app.auth.oauth.invalidRequest');
            return;
        }

        window.location.href = `${response.redirectUri}?${params.toString()}`;
    }
}
