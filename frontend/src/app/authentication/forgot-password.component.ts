import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {RouterLink} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-forgot-password',
    standalone: true,
    imports: [BrandLogoComponent, ReactiveFormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './forgot-password.component.html',
    styleUrl: './auth-page.scss',
})
export class ForgotPasswordComponent {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);

    protected readonly saving = signal(false);
    protected readonly sent = signal(false);
    protected readonly mcpUrl = `${window.location.origin}/mcp`;
    protected readonly form = this.fb.nonNullable.group({
        email: ['', [Validators.required, Validators.email]],
    });

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.requestPasswordReset(this.form.value.email!);
            this.sent.set(true);
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }
}
