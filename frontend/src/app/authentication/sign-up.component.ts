import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router, RouterLink} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {LanguageService} from '@app/services/language.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-sign-up',
    standalone: true,
    imports: [BrandLogoComponent, ReactiveFormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './sign-up.component.html',
    styleUrl: './auth-page.scss',
})
export class SignUpComponent {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly router = inject(Router);
    private readonly languageService = inject(LanguageService);

    protected readonly saving = signal(false);
    protected readonly mcpUrl = `${window.location.origin}/mcp`;
    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        email: ['', [Validators.required, Validators.email]],
        password: ['', [Validators.required, Validators.minLength(8)]],
    });

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.signUp(
                this.form.value.email!,
                this.form.value.password!,
                this.form.value.name!,
                this.languageService.currentLang(),
            );
            this.router.navigateByUrl('/onboarding/step-1');
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }
}
