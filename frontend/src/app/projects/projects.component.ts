import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Project} from '@app/models/project';
import {AlertService} from '@app/services/alert.service';
import {PermissionsService} from '@app/services/permissions.service';
import {ProjectService} from '@app/services/project.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-projects',
    standalone: true,
    imports: [RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './projects.component.html',
    styleUrl: './projects.component.scss',
})
export class ProjectsComponent implements OnInit {
    private readonly projectService = inject(ProjectService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly permissionsService = inject(PermissionsService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly projects = signal<Project[]>([]);
    protected readonly canManageProjects = computed<boolean>(() =>
        this.permissionsService.canManageProjects(this.workspaceService.currentMembers()),
    );

    public async ngOnInit(): Promise<void> {
        await this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            this.projects.set(await this.projectService.getProjects());
        } finally {
            this.loading.set(false);
        }
    }

    protected async onDelete(project: Project): Promise<void> {
        const confirmMessage = await this.translate.instant('app.projects.deleteConfirm', {name: project.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.projectService.deleteProject(project.id);
            this.alertService.success(await this.translate.instant('app.projects.deleted') as string);
            this.projects.update((all) => all.filter((p) => p.id !== project.id));
        } catch {
            // error interceptor
        }
    }
}
