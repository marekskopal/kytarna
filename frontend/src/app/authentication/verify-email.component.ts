import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';

type VerifyState = 'pending' | 'verifying' | 'success' | 'invalid';

@Component({
    selector: 'uk-verify-email',
    standalone: true,
    imports: [BrandLogoComponent, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './verify-email.component.html',
    styleUrl: './auth-page.scss',
})
export class VerifyEmailComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);

    protected readonly state = signal<VerifyState>('pending');
    protected readonly isLoggedIn = this.auth.isLoggedIn;
    protected readonly mcpUrl = `${window.location.origin}/mcp`;

    public async ngOnInit(): Promise<void> {
        const token = this.route.snapshot.queryParamMap.get('token');
        if (token === null || token === '') {
            this.state.set('invalid');
            return;
        }

        this.state.set('verifying');
        try {
            await this.auth.verifyEmail(token);
            this.state.set('success');
            if (this.auth.isLoggedIn()) {
                try {
                    await this.currentUserService.load();
                } catch {
                    // best-effort refresh; ignored
                }
            }
        } catch {
            this.state.set('invalid');
        }
    }
}
