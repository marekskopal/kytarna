import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AlertService} from '@app/services/alert.service';
import {ProjectService} from '@app/services/project.service';
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
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);
    protected readonly id = signal<number | null>(null);

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
        const project = await this.projectService.getProject(id);
        this.form.patchValue({name: project.name, description: project.description ?? ''});
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
}
