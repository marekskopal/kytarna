import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators} from '@angular/forms';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-settings',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './settings.component.html',
    styleUrl: './settings.component.scss',
})
export class SettingsComponent {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly translate = inject(TranslateService);
    private readonly alertService = inject(AlertService);

    protected readonly saving = signal(false);
    protected readonly exporting = signal(false);
    protected readonly deleting = signal(false);
    protected readonly form = this.fb.nonNullable.group(
        {
            currentPassword: ['', Validators.required],
            newPassword: ['', [Validators.required, Validators.minLength(8)]],
            confirmPassword: ['', Validators.required],
        },
        {validators: [matchPasswords]},
    );
    protected readonly hasMismatch = computed<boolean>(() => {
        return this.form.errors?.['passwordMismatch'] === true && this.form.controls.confirmPassword.dirty;
    });

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.changePassword(this.form.value.currentPassword!, this.form.value.newPassword!);
            const message = await this.translate.instant('app.settings.changePassword.success') as string;
            this.alertService.success(message);
            this.form.reset();
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onExport(): Promise<void> {
        this.exporting.set(true);
        try {
            const blob = await this.currentUserService.exportData();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `kytarna-export-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch {
            // error interceptor
        } finally {
            this.exporting.set(false);
        }
    }

    protected async onDelete(): Promise<void> {
        const message = this.translate.instant('app.settings.dangerZone.deleteConfirm') as string;
        if (!confirm(message)) {
            return;
        }
        this.deleting.set(true);
        try {
            await this.currentUserService.deleteAccount();
            this.currentUserService.clear();
            this.auth.logout();
        } catch {
            // error interceptor surfaces the message (incl. blocking workspaces)
        } finally {
            this.deleting.set(false);
        }
    }
}

function matchPasswords(control: AbstractControl): ValidationErrors | null {
    const password = control.get('newPassword')?.value;
    const confirm = control.get('confirmPassword')?.value;
    if (password !== '' && confirm !== '' && password !== confirm) {
        return {passwordMismatch: true};
    }
    return null;
}
