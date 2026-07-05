import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router} from '@angular/router';
import {
    OnboardingStateService,
    TemplateColumn,
    WORKFLOW_TEMPLATES,
    WorkflowTemplate,
} from '@app/onboarding/onboarding-state.service';
import {AlertService} from '@app/services/alert.service';
import {BoardService} from '@app/services/board.service';
import {ProjectService} from '@app/services/project.service';
import {StatusService} from '@app/services/status.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

interface WorkflowOption {
    id: WorkflowTemplate;
    columns: TemplateColumn[];
}

@Component({
    selector: 'uk-onboarding-step-1',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './step-1.component.html',
    styleUrl: './step-shared.scss',
})
export class OnboardingStep1Component {
    private readonly fb = inject(FormBuilder);
    private readonly router = inject(Router);
    private readonly projectService = inject(ProjectService);
    private readonly boardService = inject(BoardService);
    private readonly statusService = inject(StatusService);
    private readonly state = inject(OnboardingStateService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);
    protected readonly selectedTemplate = signal<WorkflowTemplate>('kanban');

    protected readonly workflows: WorkflowOption[] = [
        {id: 'kanban', columns: WORKFLOW_TEMPLATES.kanban},
        {id: 'engineering', columns: WORKFLOW_TEMPLATES.engineering},
        {id: 'simple', columns: WORKFLOW_TEMPLATES.simple},
        {id: 'blank', columns: WORKFLOW_TEMPLATES.blank},
    ];

    protected readonly form = this.fb.nonNullable.group({
        name: [this.state.projectName(), Validators.required],
        description: [''],
    });

    protected selectTemplate(id: WorkflowTemplate): void {
        this.selectedTemplate.set(id);
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid || this.saving()) {
            return;
        }
        this.saving.set(true);
        const name = this.form.value.name!.trim();
        const description = this.form.value.description?.trim() ?? '';
        try {
            const project = await this.projectService.createProject(name, description === '' ? null : description);
            this.state.projectId.set(project.id);
            this.state.projectName.set(project.name);

            const template = this.selectedTemplate();
            if (template !== 'kanban') {
                await this.applyTemplate(project.id, WORKFLOW_TEMPLATES[template]);
            }

            await this.router.navigateByUrl('/onboarding/step-2');
        } catch {
            this.alertService.error(await this.translate.instant('app.onboarding.step1.errorCreate') as string);
        } finally {
            this.saving.set(false);
        }
    }

    private async applyTemplate(projectId: number, columns: TemplateColumn[]): Promise<void> {
        const board = await this.boardService.getBoard(projectId);
        const workflowId = board.workflow.id;

        for (const status of board.statuses) {
            await this.statusService.deleteStatus(status.id);
        }
        for (let i = 0; i < columns.length; i++) {
            const col = columns[i];
            await this.statusService.createStatus(workflowId, {
                name: col.name,
                color: col.color,
                type: col.type,
                position: i,
            });
        }
    }
}
