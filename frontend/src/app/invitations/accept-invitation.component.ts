import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {Invitation} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-accept-invitation',
    standalone: true,
    imports: [RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './accept-invitation.component.html',
    styleUrl: './accept-invitation.component.scss',
})
export class AcceptInvitationComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly accepting = signal(false);
    protected readonly invitation = signal<Invitation | null>(null);
    protected readonly error = signal<string | null>(null);
    protected readonly token = signal<string | null>(null);
    protected readonly isLoggedIn = this.auth.isLoggedIn;

    public async ngOnInit(): Promise<void> {
        const token = this.route.snapshot.queryParamMap.get('token');
        this.token.set(token);
        if (token === null || token === '') {
            this.error.set(await this.translate.instant('app.invitation.missingToken') as string);
            this.loading.set(false);
            return;
        }

        try {
            this.invitation.set(await this.workspaceService.lookupInvitation(token));
        } catch {
            this.error.set(await this.translate.instant('app.invitation.notFound') as string);
        } finally {
            this.loading.set(false);
        }
    }

    protected async accept(): Promise<void> {
        const token = this.token();
        if (token === null) {
            return;
        }
        this.accepting.set(true);
        try {
            const invitation = await this.workspaceService.acceptInvitation(token);
            this.alertService.success(
                await this.translate.instant('app.invitation.joined', {workspace: invitation.workspaceName}) as string,
            );
            await this.currentUserService.load();
            await this.workspaceService.loadAll();
            this.workspaceService.currentWorkspaceId.set(invitation.workspaceId);
            await this.router.navigate(['/projects']);
        } catch (e: unknown) {
            this.error.set(e instanceof Error ? e.message : await this.translate.instant('app.invitation.notFound') as string);
        } finally {
            this.accepting.set(false);
        }
    }

    protected goToSignUp(): void {
        const token = this.token();
        const invitation = this.invitation();
        const params = new URLSearchParams();
        if (token !== null) {
            params.set('invitation', token);
        }
        if (invitation !== null) {
            params.set('email', invitation.email);
        }
        void this.router.navigateByUrl(`/sign-up?${params.toString()}`);
    }

    protected goToLogin(): void {
        const token = this.token();
        const params = new URLSearchParams();
        if (token !== null) {
            params.set('invitation', token);
        }
        void this.router.navigateByUrl(`/login?${params.toString()}`);
    }
}
