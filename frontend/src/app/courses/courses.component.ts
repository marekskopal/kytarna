import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Course} from '@app/models/course';
import {AlertService} from '@app/services/alert.service';
import {CourseService} from '@app/services/course.service';
import {PermissionsService} from '@app/services/permissions.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-courses',
    standalone: true,
    imports: [RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './courses.component.html',
    styleUrl: './courses.component.scss',
})
export class CoursesComponent implements OnInit {
    private readonly courseService = inject(CourseService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly permissionsService = inject(PermissionsService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly courses = signal<Course[]>([]);
    protected readonly canManageCourses = computed<boolean>(() =>
        this.permissionsService.canManageCourses(this.workspaceService.currentMembers()),
    );

    public async ngOnInit(): Promise<void> {
        await this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            this.courses.set(await this.courseService.getCourses());
        } finally {
            this.loading.set(false);
        }
    }

    protected async onDelete(course: Course): Promise<void> {
        const confirmMessage = await this.translate.instant('app.courses.deleteConfirm', {name: course.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.courseService.deleteCourse(course.id);
            this.alertService.success(await this.translate.instant('app.courses.deleted') as string);
            this.courses.update((all) => all.filter((p) => p.id !== course.id));
        } catch {
            // error interceptor
        }
    }
}
