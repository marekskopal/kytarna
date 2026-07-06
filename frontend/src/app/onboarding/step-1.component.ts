import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {Router} from '@angular/router';
import {OnboardingStateService} from '@app/onboarding/onboarding-state.service';
import {AlertService} from '@app/services/alert.service';
import {CourseService} from '@app/services/course.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

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
    private readonly courseService = inject(CourseService);
    private readonly state = inject(OnboardingStateService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);

    protected readonly form = this.fb.nonNullable.group({
        name: [this.state.courseName(), Validators.required],
        description: [''],
    });

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid || this.saving()) {
            return;
        }
        this.saving.set(true);
        const name = this.form.value.name!.trim();
        const description = this.form.value.description?.trim() ?? '';
        try {
            const course = await this.courseService.createCourse(name, description === '' ? null : description);
            this.state.courseId.set(course.id);
            this.state.courseName.set(course.name);
            await this.router.navigateByUrl('/onboarding/step-2');
        } catch {
            this.alertService.error(await this.translate.instant('app.onboarding.step1.errorCreate') as string);
        } finally {
            this.saving.set(false);
        }
    }
}
