import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router} from '@angular/router';
import {WorkspaceRole} from '@app/models/workspace';
import {OnboardingStateService} from '@app/onboarding/onboarding-state.service';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

type InviteFormGroup = FormGroup<{
    email: FormControl<string>;
    role: FormControl<WorkspaceRole>;
}>;

@Component({
    selector: 'uk-onboarding-step-2',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './step-2.component.html',
    styleUrl: './step-shared.scss',
})
export class OnboardingStep2Component {
    private readonly fb = inject(FormBuilder);
    private readonly router = inject(Router);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly state = inject(OnboardingStateService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);

    protected readonly form = this.fb.nonNullable.group({
        invites: this.fb.array<InviteFormGroup>([this.createRow()]),
    });

    protected get rows(): FormArray<InviteFormGroup> {
        return this.form.controls.invites;
    }

    protected addRow(): void {
        this.rows.push(this.createRow());
    }

    protected removeRow(index: number): void {
        if (this.rows.length === 1) {
            return;
        }
        this.rows.removeAt(index);
    }

    protected async onSubmit(): Promise<void> {
        if (this.saving()) {
            return;
        }

        const filled = this.rows.controls.filter((row) => row.value.email!.trim() !== '');
        const invalid = filled.filter((row) => row.invalid);
        if (invalid.length > 0) {
            invalid.forEach((row) => row.markAllAsTouched());
            return;
        }

        if (filled.length === 0) {
            await this.router.navigateByUrl('/onboarding/step-3');
            return;
        }

        const workspaceId = this.currentUserService.currentUser()?.currentWorkspaceId
            ?? (await this.currentUserService.load()).currentWorkspaceId;
        if (workspaceId === null) {
            this.alertService.error(await this.translate.instant('app.onboarding.step2.errorNoWorkspace') as string);
            return;
        }

        this.saving.set(true);
        const results = await Promise.allSettled(filled.map((row) =>
            this.workspaceService.createInvitation(workspaceId, row.value.email!.trim(), row.value.role!),
        ));

        const ok = results.filter((r) => r.status === 'fulfilled').length;
        const failed = results.length - ok;
        this.state.invitesSent.update((n) => n + ok);

        if (failed > 0) {
            this.alertService.error(
                await this.translate.instant('app.onboarding.step2.errorSomeFailed', {count: failed}) as string,
            );
        }

        this.saving.set(false);
        await this.router.navigateByUrl('/onboarding/step-3');
    }

    protected async onSkip(): Promise<void> {
        await this.router.navigateByUrl('/onboarding/step-3');
    }

    private createRow(): InviteFormGroup {
        return this.fb.nonNullable.group({
            email: this.fb.nonNullable.control('', [Validators.email]),
            role: this.fb.nonNullable.control<WorkspaceRole>('Member'),
        });
    }
}
