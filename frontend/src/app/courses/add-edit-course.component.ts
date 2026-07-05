import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AlertService} from '@app/services/alert.service';
import {CourseService} from '@app/services/course.service';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-add-edit-course',
    standalone: true,
    imports: [ReactiveFormsModule, RouterLink, TranslatePipe, MarkdownEditorComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './add-edit-course.component.html',
    styleUrl: './add-edit-course.component.scss',
})
export class AddEditCourseComponent implements OnInit {
    private readonly fb = inject(FormBuilder);
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly courseService = inject(CourseService);
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
        const course = await this.courseService.getCourse(id);
        this.form.patchValue({name: course.name, description: course.description ?? ''});
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
                await this.courseService.createCourse(name, description ?? null);
                this.alertService.success(await this.translate.instant('app.courses.created') as string);
            } else {
                await this.courseService.updateCourse(id, name, description ?? null);
                this.alertService.success(await this.translate.instant('app.courses.updated') as string);
            }
            this.router.navigateByUrl('/courses');
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }
}
