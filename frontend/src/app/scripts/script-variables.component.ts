import {ChangeDetectionStrategy, Component, effect, inject, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {RouterLink} from '@angular/router';
import {ScriptVariable} from '@app/models/script';
import {AlertService} from '@app/services/alert.service';
import {ScriptService} from '@app/services/script.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-script-variables',
    standalone: true,
    imports: [ReactiveFormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './script-variables.component.html',
    styleUrl: './script-variables.component.scss',
})
export class ScriptVariablesComponent {
    private readonly fb = inject(FormBuilder);
    private readonly scriptService = inject(ScriptService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly variables = signal<ScriptVariable[]>([]);
    protected readonly loading = signal(true);
    protected readonly saving = signal(false);
    protected readonly editingExisting = signal(false);

    protected readonly form = this.fb.nonNullable.group({
        key: ['', [Validators.required, Validators.maxLength(128)]],
        value: ['', Validators.required],
        isSecret: [false],
    });

    public constructor() {
        effect(() => {
            const workspaceId = this.workspaceService.currentWorkspaceId();
            if (workspaceId === null) {
                return;
            }
            void this.load(workspaceId);
        });
    }

    protected edit(variable: ScriptVariable): void {
        this.editingExisting.set(true);
        this.form.reset({key: variable.key, value: variable.isSecret ? '' : (variable.value ?? ''), isSecret: variable.isSecret});
    }

    protected resetForm(): void {
        this.editingExisting.set(false);
        this.form.reset({key: '', value: '', isSecret: false});
    }

    protected async save(): Promise<void> {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        const workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            return;
        }
        this.saving.set(true);
        try {
            await this.scriptService.upsertVariable(workspaceId, {
                key: this.form.controls.key.value.trim(),
                value: this.form.controls.value.value,
                isSecret: this.form.controls.isSecret.value,
            });
            this.alertService.success(this.translate.instant('app.scripts.variables.saved') as string);
            this.resetForm();
            await this.load(workspaceId);
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.saving.set(false);
        }
    }

    protected async remove(variable: ScriptVariable): Promise<void> {
        const workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            return;
        }
        const message = this.translate.instant('app.scripts.variables.deleteConfirm', {key: variable.key}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.scriptService.deleteVariable(workspaceId, variable.id);
            this.variables.update((variables) => variables.filter((v) => v.id !== variable.id));
        } catch {
            // error interceptor surfaces the message
        }
    }

    private async load(workspaceId: number): Promise<void> {
        this.loading.set(true);
        try {
            this.variables.set(await this.scriptService.listVariables(workspaceId));
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.loading.set(false);
        }
    }
}
