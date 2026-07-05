import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {Field, ProjectField} from '@app/models/field';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {PermissionsService} from '@app/services/permissions.service';
import {ProjectService} from '@app/services/project.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-add-edit-project',
    standalone: true,
    imports: [ReactiveFormsModule, RouterLink, TranslatePipe, MarkdownEditorComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './add-edit-project.component.html',
    styleUrl: './add-edit-project.component.scss',
})
export class AddEditProjectComponent implements OnInit {
    private readonly fb = inject(FormBuilder);
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly projectService = inject(ProjectService);
    private readonly fieldService = inject(FieldService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly permissionsService = inject(PermissionsService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);
    protected readonly id = signal<number | null>(null);

    protected readonly workspaceFields = signal<Field[]>([]);
    protected readonly attachedFieldIds = signal<number[]>([]);
    protected readonly fieldsSaving = signal(false);
    protected readonly canManageProjects = signal(false);

    protected readonly attachedFields = computed<Field[]>(() => {
        const byId = new Map(this.workspaceFields().map((f) => [f.id, f]));
        return this.attachedFieldIds()
            .map((id) => byId.get(id))
            .filter((f): f is Field => f !== undefined);
    });

    protected readonly availableFields = computed<Field[]>(() => {
        const attached = new Set(this.attachedFieldIds());
        return this.workspaceFields().filter((f) => !attached.has(f.id));
    });

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
    });

    public async ngOnInit(): Promise<void> {
        const idParam = this.route.snapshot.paramMap.get('id');
        if (idParam === null) {
            return;
        }
        const id = Number(idParam);
        this.id.set(id);
        await this.loadAll(id);
    }

    private async loadAll(projectId: number): Promise<void> {
        const project = await this.projectService.getProject(projectId);
        this.form.patchValue({name: project.name, description: project.description ?? ''});

        const user = this.currentUserService.currentUser() ?? await this.currentUserService.load();
        const workspaceId = user.currentWorkspaceId;
        if (workspaceId === null) {
            return;
        }

        const [fields, projectFields, members] = await Promise.all([
            this.fieldService.listWorkspaceFields(workspaceId).catch(() => [] as Field[]),
            this.fieldService.listProjectFields(projectId).catch(() => [] as ProjectField[]),
            this.workspaceService.getMembers(workspaceId).catch(() => []),
        ]);
        this.workspaceFields.set(fields);
        this.attachedFieldIds.set(projectFields.sort((a, b) => a.position - b.position).map((pf) => pf.fieldId));
        this.canManageProjects.set(this.permissionsService.canManageProjects(members));
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const name = this.form.value.name!;
        const description = this.form.value.description?.trim() ? this.form.value.description : null;

        try {
            const id = this.id();
            if (id === null) {
                await this.projectService.createProject(name, description ?? null);
                this.alertService.success(await this.translate.instant('app.projects.created') as string);
            } else {
                await this.projectService.updateProject(id, name, description ?? null);
                this.alertService.success(await this.translate.instant('app.projects.updated') as string);
            }
            this.router.navigateByUrl('/projects');
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected attachField(field: Field): void {
        this.attachedFieldIds.update((ids) => (ids.includes(field.id) ? ids : [...ids, field.id]));
    }

    protected detachField(field: Field): void {
        this.attachedFieldIds.update((ids) => ids.filter((id) => id !== field.id));
    }

    protected moveAttachedUp(index: number): void {
        if (index === 0) return;
        this.attachedFieldIds.update((ids) => {
            const next = [...ids];
            [next[index - 1], next[index]] = [next[index], next[index - 1]];
            return next;
        });
    }

    protected moveAttachedDown(index: number): void {
        this.attachedFieldIds.update((ids) => {
            if (index >= ids.length - 1) return ids;
            const next = [...ids];
            [next[index + 1], next[index]] = [next[index], next[index + 1]];
            return next;
        });
    }

    protected async saveAttachedFields(): Promise<void> {
        const id = this.id();
        if (id === null || !this.canManageProjects()) {
            return;
        }
        this.fieldsSaving.set(true);
        try {
            const updated = await this.fieldService.setProjectFields(id, this.attachedFieldIds());
            this.attachedFieldIds.set(updated.sort((a, b) => a.position - b.position).map((pf) => pf.fieldId));
            this.alertService.success(await this.translate.instant('app.projectFields.saved') as string);
        } catch {
            // error interceptor
        } finally {
            this.fieldsSaving.set(false);
        }
    }
}
