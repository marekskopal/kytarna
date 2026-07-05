import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';
import {BrandLogoComponent} from '@app/shared/components/brand-logo/brand-logo.component';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-reset-password',
    standalone: true,
    imports: [BrandLogoComponent, ReactiveFormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './reset-password.component.html',
    styleUrl: './auth-page.scss',
})
export class ResetPasswordComponent implements OnInit {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);

    protected readonly token = signal<string | null>(null);
    protected readonly saving = signal(false);
    protected readonly mcpUrl = `${window.location.origin}/mcp`;
    protected readonly form = this.fb.nonNullable.group(
        {
            password: ['', [Validators.required, Validators.minLength(8)]],
            confirmPassword: ['', Validators.required],
        },
        {validators: [matchPasswords]},
    );
    protected readonly hasMismatch = computed<boolean>(() => {
        return this.form.errors?.['passwordMismatch'] === true && this.form.controls.confirmPassword.dirty;
    });

    public ngOnInit(): void {
        this.token.set(this.route.snapshot.queryParamMap.get('token'));
    }

    protected async onSubmit(): Promise<void> {
        const token = this.token();
        if (this.form.invalid || token === null) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.confirmPasswordReset(token, this.form.value.password!);
            await this.router.navigateByUrl('/projects');
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }
}

function matchPasswords(control: AbstractControl): ValidationErrors | null {
    const password = control.get('password')?.value;
    const confirm = control.get('confirmPassword')?.value;
    if (password !== '' && confirm !== '' && password !== confirm) {
        return {passwordMismatch: true};
    }
    return null;
}
